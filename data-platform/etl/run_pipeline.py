"""CLI: cd data-platform && python -m etl.run_pipeline --as-of 2026-11-16

Requires existing PIC B/C PostgreSQL source schema and MinIO; never manufactures
financial transactions. See docs/PIC_D/INSTALL_WINDOWS.md.
"""
import argparse
import os
import logging
import shutil
import sys
import uuid
from datetime import date, datetime
from zoneinfo import ZoneInfo
from pathlib import Path

from .config import Settings

LOG=logging.getLogger('picd.etl')


def publish_parquet(frames, run_dir, settings, run_id):
    import boto3
    out_dir=run_dir/'parquet'
    partitions={
        'fact_sales':'local_date', 'fact_cash_receipts':'local_date',
        'fact_credit_issuance':'local_date', 'fact_operating_expenses':'local_date',
        'fact_stock_daily':'snapshot_date', 'fact_receivables_daily':'snapshot_date',
        'fact_receivable_aging_daily':'snapshot_date',
    }
    for table,frame in frames.items():
        writer=frame.write.mode('overwrite').option('compression','snappy')
        if table.split('/')[-1] in partitions:
            writer=writer.partitionBy(partitions[table.split('/')[-1]])
        writer.parquet(str(out_dir/table))
    s3=boto3.client('s3',endpoint_url=settings.minio_endpoint,
                    aws_access_key_id=settings.minio_access_key,
                    aws_secret_access_key=settings.minio_secret_key,
                    region_name='us-east-1')
    try:
        s3.head_bucket(Bucket=settings.minio_bucket)
    except s3.exceptions.ClientError as error:
        if error.response['Error']['Code'] not in {'404', 'NoSuchBucket', 'NotFound'}:
            raise
        s3.create_bucket(Bucket=settings.minio_bucket)
    for file in sorted(out_dir.rglob('*')):
        if file.is_file() and not file.name.startswith('.'):
            key='picd/runs/'+str(run_id)+'/'+str(file.relative_to(out_dir)).replace('\\','/')
            s3.upload_file(str(file), settings.minio_bucket, key)
    return str(out_dir)


def main(argv=None):
    parser=argparse.ArgumentParser(description='KOPERADATA PIC D ETL: read OLTP; publish Parquet + analytics')
    parser.add_argument('--as-of',default=datetime.now(ZoneInfo('Asia/Jakarta')).date().isoformat(),help='Business snapshot date YYYY-MM-DD (Asia/Jakarta)')
    parser.add_argument('--keep-local',action='store_true',help='Keep local source JSONL (sensitive!) for troubleshooting')
    args=parser.parse_args(argv)
    try:
        asof=date.fromisoformat(args.as_of)
        if asof != datetime.now(ZoneInfo('Asia/Jakarta')).date():
            raise ValueError('Inventory source is current-only: --as-of must be today Asia/Jakarta; historical stock backfills need a dated source snapshot')
        settings=Settings.from_env()
    except ValueError as e:
        parser.error(str(e))
    logging.basicConfig(level=logging.INFO,format='%(asctime)s %(levelname)s %(message)s')
    run_id=str(uuid.uuid4())
    run_dir=settings.output_dir/run_id
    source_dir=run_dir/'source-local-private'
    spark=None
    from .source import extract_snapshot
    from .transform import load_sources,build_frames
    from .warehouse_load import insert_run,publish,fail_run
    try:
        insert_run(settings.warehouse_dsn,run_id)
        counts, observed_at=extract_snapshot(settings.source_dsn,source_dir,settings.pseudonym_secret)
        from pyspark.sql import SparkSession
        spark=SparkSession.builder.master(settings.spark_master).appName('KOPERADATA-PICD-v1.4')\
           .config('spark.sql.session.timeZone','UTC').config('spark.sql.shuffle.partitions','4').getOrCreate()
        spark.sparkContext.setLogLevel('WARN')
        load_sources(spark,source_dir)
        cutoff = date.fromisoformat(os.environ['PICD_STOCK_CUTOFF']) if os.getenv('PICD_STOCK_CUTOFF') else None
        frames,quality=build_frames(spark,asof,history_cutoff=cutoff)
        quality['snapshot_observed_at'] = observed_at.isoformat()
        quality['lake_prefix'] = 'picd/runs/' + run_id + '/'
        # An explicit action before publication protects against lazy transform errors.
        for label,frame in frames.items():
            frames[label] = frame.cache()
            LOG.info('%s output rows=%d',label,frame.count())
        # Publish immutable versioned files first; ETL status points only to succeeded mart.
        from .source import SPECS
        lake_frames = {f'raw/{table}': spark.table('src_' + table) for table in SPECS}
        lake_frames.update({f'validated/{table}': spark.table('src_' + table) for table in SPECS})
        lake_frames.update({f'transformed/{table}': frame for table, frame in frames.items()})
        parquet_path=publish_parquet(lake_frames,run_dir,settings,run_id)
        out_count=publish(settings.warehouse_dsn,run_id,frames,asof,sum(counts.values()),quality)
        LOG.info('SUCCESS %s rows=%d minio_bucket=%s parquet=%s',run_id,out_count,
                 settings.minio_bucket,parquet_path)
        return 0
    except Exception as exc:
        LOG.exception('ETL FAILED %s: %s',run_id,exc)
        try:fail_run(settings.warehouse_dsn,run_id,exc)
        except Exception:LOG.exception('Could not mark ETL failed; inspect analytics.etl_runs')
        return 1
    finally:
        if spark:spark.stop()
        if not args.keep_local:
            shutil.rmtree(source_dir,ignore_errors=True)


if __name__=='__main__':sys.exit(main())
