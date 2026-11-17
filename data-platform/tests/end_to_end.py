"""Full isolated PostgreSQL -> Spark -> MinIO -> mart acceptance test."""
import csv
import json
import os
import sys
import uuid
from datetime import date, datetime, timedelta
from pathlib import Path
from zoneinfo import ZoneInfo
sys.path.insert(0,str(Path(__file__).parents[1]))
import boto3
import psycopg
from psycopg import sql
from etl.source import SPECS
from etl.run_pipeline import main as run_pipeline
from etl.reconcile import compare
from etl.forecast_feed import export
from source_fixture import source_fixture

def main():
    dsn = os.environ['PICD_TEST_DSN']
    today = datetime.now(ZoneInfo('Asia/Jakarta')).date()
    shift = today - date(2026,11,24)
    fixture = source_fixture()
    for records in fixture.values():
        for row in records:
            for key in ('completed_at','created_at','verified_at','due_date'):
                if row.get(key):
                    row[key] = (date.fromisoformat(row[key][:10])+shift).isoformat()+row[key][10:]
    with psycopg.connect(dsn) as db:
        name = db.execute('SELECT current_database()').fetchone()[0]
        if not name.startswith('picd_test_'): raise ValueError('Dedicated test database required')
        for table,spec in SPECS.items():
            columns = [*spec['required'],*spec['optional']]
            db.execute(sql.SQL('CREATE TABLE IF NOT EXISTS public.{} ({})').format(sql.Identifier(table),
                sql.SQL(',').join(sql.SQL('{} text').format(sql.Identifier(column)) for column in columns)))
            db.execute(sql.SQL('TRUNCATE public.{}').format(sql.Identifier(table)))
            for record in fixture[table]:
                db.execute(sql.SQL('INSERT INTO public.{} ({}) VALUES ({})').format(sql.Identifier(table),
                    sql.SQL(',').join(map(sql.Identifier,columns)),sql.SQL(',').join(sql.Placeholder() for column in columns)),
                    [record.get(column) for column in columns])
        db.execute(Path(__file__).parents[1].joinpath('analytics/schema.sql').read_text())
    os.environ['PICD_SOURCE_DSN'] = dsn
    os.environ['PICD_WAREHOUSE_DSN'] = dsn
    os.environ['PICD_OUTPUT_DIR'] = '/tmp/picd-e2e'
    os.environ['PICD_SPARK_MASTER'] = 'local[2]'
    assert run_pipeline([]) == 0
    with psycopg.connect(dsn) as source, psycopg.connect(dsn) as mart:
        results = compare(source,mart,today-timedelta(days=8),today)
        assert all(row['match'] for row in results.values()),results
        row = mart.execute("SELECT run_id FROM analytics.etl_runs WHERE status='SUCCEEDED' ORDER BY finished_at DESC LIMIT 1").fetchone()
        run_id = str(row[0])
    client = boto3.client('s3',endpoint_url=os.environ['PICD_MINIO_ENDPOINT'],
        aws_access_key_id=os.environ['PICD_MINIO_ACCESS_KEY'],aws_secret_access_key=os.environ['PICD_MINIO_SECRET_KEY'])
    bucket = os.environ['PICD_MINIO_BUCKET']
    objects = client.list_objects_v2(Bucket=bucket,Prefix=f'picd/runs/{run_id}/')['Contents']
    keys = [row['Key'] for row in objects]
    for layer in ('raw','validated','transformed'):
        assert any(f'/{layer}/' in key and key.endswith('.parquet') for key in keys)
    feed = Path('/tmp/forecast-input.csv')
    assert export(dsn,today-timedelta(days=8),today,feed)==9
    rows = list(csv.DictReader(feed.open()))
    assert sum(int(row['units_sold']) for row in rows)==2
    assert sum(int(row['units_sold'])==0 for row in rows)==8
    report = {'run_id':run_id,'date':str(today),'reconciliation':results,
        'minio_objects':len(objects),'parquet_layers':['raw','validated','transformed'],
        'forecast_days':9,'zero_sale_days':8,'status':'PASS'}
    Path('/project/docs/PIC_D/end_to_end_results.json').write_text(json.dumps(report,indent=2))
    print(json.dumps(report,indent=2))
if __name__ == '__main__': main()
