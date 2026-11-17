"""Executable Spark/PostgreSQL regression on an explicitly isolated test database."""
import json
import os
import tempfile
import uuid
from datetime import date, datetime, timezone
from decimal import Decimal
from pathlib import Path
import sys
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from pyspark.sql import SparkSession, functions as F
import psycopg
from etl.source import SPECS
from etl.transform import load_sources, build_frames
from etl.warehouse_load import insert_run, publish, fail_run

def main():
    dsn = os.environ['PICD_TEST_DSN']
    with psycopg.connect(dsn) as db:
        name = db.execute('select current_database()').fetchone()[0]
        if not name.startswith('picd_test_'):
            raise ValueError('Use a dedicated picd_test_ database')
        db.execute(Path(__file__).parents[1].joinpath('analytics/schema.sql').read_text())
    spark = SparkSession.builder.master('local[2]').appName('PICD integration').config('spark.sql.session.timeZone','UTC').config('spark.sql.shuffle.partitions','2').getOrCreate()
    spark.sparkContext.setLogLevel('ERROR')
    try:
        with tempfile.TemporaryDirectory() as folder:
            source = {table: [] for table in SPECS}
            source['orders'] = [dict(id='o1',member_id='pseudo1',status='COMPLETED',completed_at='2026-11-16T03:00:00Z',payment_choice='CREDIT',channel='POS',import_batch_id=None)]
            source['order_items'] = [dict(id='i1',order_id='o1',product_id='p1',quantity='2',unit_price_snapshot='100000',hpp_snapshot='70000')]
            source['products'] = [dict(id='p1',sku='BERAS',name='Beras',category='Pangan',lead_time_days='3')]
            source['members'] = [dict(id='pseudo1')]
            source['inventory_balances'] = [dict(product_id='p1',on_hand='98',reserved='0')]
            source['receivables'] = [dict(id='r1',order_id='o1',member_id='pseudo1',original_amount='200000',outstanding_amount='125000',due_date='2026-11-23',created_at='2026-11-16T03:00:00Z')]
            source['payments'] = [dict(id='pay1',amount='75000',status='VERIFIED',verified_at='2026-11-20T04:00:00Z',order_id=None,method='CASH',member_id='pseudo1')]
            source['receivable_payment_allocations'] = [dict(id='a1',payment_id='pay1',receivable_id='r1',allocated_amount='75000')]
            for table, records in source.items():
                Path(folder,table+'.jsonl').write_text(''.join(json.dumps(row)+'\n' for row in records))
            load_sources(spark,Path(folder))
            frames, quality = build_frames(spark,date(2026,11,24))
            for key in frames:
                frames[key] = frames[key].cache()
            assert frames['fact_sales'].first()['revenue'] == Decimal('200000')
            assert frames['fact_cash_receipts'].first()['receivable_amount'] == Decimal('75000')
            ar = {str(row['snapshot_date']): row for row in frames['fact_receivables_daily'].collect()}
            assert ar['2026-11-16']['outstanding_amount'] == Decimal('200000')
            assert ar['2026-11-20']['outstanding_amount'] == Decimal('125000')
            assert ar['2026-11-23']['overdue_amount'] == Decimal('0')
            assert ar['2026-11-24']['overdue_amount'] == Decimal('125000')
            quality['snapshot_observed_at'] = datetime.now(timezone.utc).isoformat()
            for iteration in range(2):
                run_id = str(uuid.uuid4())
                insert_run(dsn,run_id)
                publish(dsn,run_id,frames,date(2026,11,24),sum(map(len,source.values())),quality)
            with psycopg.connect(dsn) as db:
                assert db.execute('select count(*),sum(revenue) from analytics.fact_sales').fetchone() == (1,Decimal('200000'))
                assert db.execute('select data_quality from analytics.etl_runs where run_id=%s',(run_id,)).fetchone()[0]['changed_rows'] == 0
            # A constraint failure after dimension loading must roll back all writes.
            broken = dict(frames)
            broken['dim_product'] = frames['dim_product'].withColumn('name', F.lit('must roll back'))
            broken['fact_sales'] = frames['fact_sales'].withColumn('quantity', F.lit(-1))
            rollback_id = str(uuid.uuid4())
            insert_run(dsn, rollback_id)
            try:
                publish(dsn, rollback_id, broken, date(2026,11,24), 1, quality)
                raise AssertionError('Invalid fact was published')
            except psycopg.errors.CheckViolation as error:
                fail_run(dsn, rollback_id, error)
            with psycopg.connect(dsn) as db:
                assert db.execute("SELECT name FROM analytics.dim_product WHERE product_key='p1'").fetchone()[0] == 'Beras'
                assert db.execute('SELECT SUM(quantity) FROM analytics.fact_sales').fetchone()[0] == 2
            # A later correction updates the same natural key without duplication.
            corrected = dict(frames)
            corrected['fact_sales'] = frames['fact_sales'].withColumn('cogs', F.lit('130000.00').cast('decimal(18,2)'))
            correction_id = str(uuid.uuid4())
            insert_run(dsn, correction_id)
            publish(dsn, correction_id, corrected, date(2026,11,24), 1, quality)
            with psycopg.connect(dsn) as db:
                assert db.execute('SELECT COUNT(*),SUM(cogs) FROM analytics.fact_sales').fetchone() == (1, Decimal('130000'))
            failed_id = str(uuid.uuid4())
            insert_run(dsn,failed_id)
            fail_run(dsn,failed_id,ValueError('controlled failure'))
            with psycopg.connect(dsn) as db:
                assert db.execute('select sum(revenue) from analytics.fact_sales').fetchone()[0] == Decimal('200000')
            print('PASS Spark financial history, aging boundary, PostgreSQL publication, identical rerun, transactional rollback, late correction, failure preservation')
    finally:
        spark.stop()
if __name__ == '__main__': main()
