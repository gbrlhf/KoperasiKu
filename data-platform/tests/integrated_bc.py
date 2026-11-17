"""TEST ONLY: migrated B/C tables -> read-only snapshot -> Spark/MinIO -> D mart.

Run Laravel migrations on a dedicated picd_test_* database first. No text-table mocks.
"""
import csv
import json
import os
import sys
import uuid
from datetime import datetime, timedelta
from decimal import Decimal
from pathlib import Path
from zoneinfo import ZoneInfo

sys.path.insert(0, str(Path(__file__).parents[1]))
import boto3
import psycopg
from psycopg import sql
from etl.run_pipeline import main as pipeline
from etl.reconcile import compare
from etl.forecast_feed import export
from etl.source import extract_snapshot, SourceContractError


def key(label):
    return str(uuid.uuid5(uuid.NAMESPACE_URL, 'koperadata/test-only/' + label))


def insert(db, table, **record):
    db.execute(sql.SQL('INSERT INTO public.{} ({}) VALUES ({})').format(
        sql.Identifier(table), sql.SQL(',').join(map(sql.Identifier, record)),
        sql.SQL(',').join(sql.Placeholder() for _ in record)), list(record.values()))


def main():
    dsn = os.environ['PICD_TEST_DSN']
    today = datetime.now(ZoneInfo('Asia/Jakarta')).date()
    start = today - timedelta(days=8)
    with psycopg.connect(dsn) as db:
        if not db.info.dbname.startswith('picd_test_'):
            raise ValueError('Dedicated test database required')
        assert db.execute("SELECT data_type FROM information_schema.columns WHERE table_name='payments' AND column_name='id'").fetchone()[0] == 'uuid'
        assert db.execute("SELECT count(*) FROM migrations").fetchone()[0] >= 8
        # Safe only in this explicitly isolated synthetic database.
        tables = ['receivable_payment_allocations', 'payments', 'receivables', 'order_items',
                  'orders', 'inventory_balances', 'operating_expenses', 'products',
                  'members', 'categories', 'suppliers', 'import_batches']
        db.execute(sql.SQL('TRUNCATE {} CASCADE').format(sql.SQL(',').join(sql.Identifier('public', t) for t in tables)))
        insert(db, 'members', id=key('member'), employee_number='TEST-ONLY-001', member_number='TEST-001', full_name='SYNTHETIC DO NOT USE')
        insert(db, 'categories', id=key('category'), name='Pangan TEST ONLY')
        insert(db, 'suppliers', id=key('supplier'), code='TEST-SUPPLIER', name='TEST ONLY', lead_time_days=3)
        insert(db, 'products', id=key('product'), category_id=key('category'), supplier_id=key('supplier'),
               sku='PICD-TEST-BERAS', name='Beras TEST ONLY', selling_price=100000, current_cost=70000)
        for label, status in [('import', 'COMMITTED'), ('preview', 'PREVIEW_READY')]:
            insert(db, 'import_batches', id=key(label), uploaded_by=key('actor'), file_name='TEST-ONLY.csv',
                   file_path='TEST-ONLY.csv', checksum=label, import_type='SALES_HISTORY', status=status)
        for label, status, batch in [('sale', 'COMPLETED', 'import'), ('preview-sale', 'COMPLETED', 'preview'), ('checkout', 'PENDING_PICKUP', None)]:
            insert(db, 'orders', id=key(label), member_id=key('member'), channel='POS', status=status,
                   payment_choice='CREDIT', completed_at=f'{start} 03:00:00' if status == 'COMPLETED' else None,
                   import_batch_id=key(batch) if batch else None)
            insert(db, 'order_items', id=key(label+'-item'), order_id=key(label), product_id=key('product'),
                   quantity=2, unit_price_snapshot=100000, hpp_snapshot=70000)
        insert(db, 'inventory_balances', product_id=key('product'), on_hand=98, reserved=2)
        insert(db, 'receivables', id=key('invoice'), order_id=key('sale'), member_id=key('member'),
               original_amount=200000, outstanding_amount=125000, due_date=start+timedelta(days=7), created_at=f'{start} 03:00:00')
        for label, status in [('payment', 'VERIFIED'), ('pending-payment', 'PENDING_VERIFICATION')]:
            insert(db, 'payments', id=key(label), member_id=key('member'), amount=75000, method='BANK_TRANSFER',
                   status=status, verified_at=f'{start+timedelta(days=4)} 04:00:00' if status == 'VERIFIED' else None)
            insert(db, 'receivable_payment_allocations', id=key(label+'-allocation'), payment_id=key(label),
                   receivable_id=key('invoice'), allocated_amount=75000)
        insert(db, 'operating_expenses', id=key('expense'), created_by=key('actor'), expense_date=today,
               amount=10000, category='TEST ONLY', description='Synthetic expense')
    source_dsn = dsn + " options='-c role=picd_source_reader'"
    warehouse_dsn = dsn + " options='-c role=picd_warehouse_writer'"
    with psycopg.connect(source_dsn) as source:
        try:
            source.execute('UPDATE public.orders SET status=status WHERE false')
        except psycopg.errors.InsufficientPrivilege:
            source.rollback()
        else:
            raise AssertionError('Source reader can write OLTP')
    os.environ.update(PICD_SOURCE_DSN=source_dsn, PICD_WAREHOUSE_DSN=warehouse_dsn,
                      PICD_OUTPUT_DIR='/tmp/picd-integrated', PICD_SPARK_MASTER='local[2]',
                      PICD_STOCK_CUTOFF=str(today-timedelta(days=1)))
    snapshot = Path('/tmp/picd-adapter-test')
    extract_snapshot(source_dsn, snapshot, os.environ['PICD_PSEUDONYM_SECRET'])
    product = json.loads((snapshot/'products.jsonl').read_text().splitlines()[0])
    assert product['category'] == 'Pangan TEST ONLY' and product['lead_time_days'] == 3
    member_text = (snapshot/'members.jsonl').read_text()
    assert key('member') not in member_text and 'SYNTHETIC' not in member_text
    for _ in range(2):
        assert pipeline([]) == 0
    with psycopg.connect(source_dsn) as source, psycopg.connect(warehouse_dsn) as mart:
        results = compare(source, mart, start, today)
        assert all(r['match'] for r in results.values()), results
        assert Decimal(results['sales_revenue']['mart']) == 200000
        assert Decimal(results['cash_receipts']['mart']) == 75000
        assert Decimal(results['outstanding']['mart']) == 125000
        assert mart.execute('SELECT before_stock_cutoff FROM analytics.fact_sales').fetchone() == (True,)
        assert mart.execute('SELECT COUNT(*) FROM analytics.fact_sales').fetchone()[0] == 1
        assert mart.execute('SELECT available FROM analytics.fact_stock_daily WHERE snapshot_date=%s', (today,)).fetchone()[0] == 96
        run_id, quality = mart.execute("SELECT run_id,data_quality FROM analytics.etl_runs WHERE status='SUCCEEDED' ORDER BY finished_at DESC LIMIT 1").fetchone()
        assert quality['changed_rows'] == 0
        assert source.execute('SELECT on_hand,reserved FROM inventory_balances').fetchone() == (98, 2)
    client = boto3.client('s3', endpoint_url=os.environ['PICD_MINIO_ENDPOINT'],
                          aws_access_key_id=os.environ['PICD_MINIO_ACCESS_KEY'], aws_secret_access_key=os.environ['PICD_MINIO_SECRET_KEY'])
    objects = [obj for page in client.get_paginator('list_objects_v2').paginate(
        Bucket=os.environ['PICD_MINIO_BUCKET'], Prefix=f'picd/runs/{run_id}/') for obj in page.get('Contents', [])]
    for layer in ['raw', 'validated', 'transformed']:
        assert any(f'/{layer}/' in obj['Key'] and obj['Key'].endswith('.parquet') for obj in objects)
    feed = Path('/tmp/forecast-bc.csv')
    assert export(warehouse_dsn, start, today, feed) == 9
    rows = list(csv.DictReader(feed.open()))
    assert sum(int(r['units_sold']) for r in rows) == 2
    assert sum(int(r['units_sold']) == 0 for r in rows) == 8
    assert all(r['lead_time_days'] == '3' for r in rows)
    report = {'status': 'PASS', 'date': str(today), 'source_schema': 'actual Laravel B/C/D migrations',
              'source_write_denied': True, 'member_pseudonymized': True, 'category_supplier_adapter': True,
              'reconciliation': results, 'minio_objects': len(objects), 'rerun_changed_rows': 0,
              'committed_import_only': True, 'cutoff_classified': True, 'inventory_unchanged': True,
              'forecast_days': 9, 'zero_sale_days': 8, 'run_id': str(run_id)}
    Path('/project/docs/PIC_D/integrated_bc_results.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report, indent=2))


if __name__ == '__main__':
    main()
