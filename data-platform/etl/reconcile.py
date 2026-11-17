"""Independent OLTP versus mart reconciliation, Jakarta periods and end-date balances."""
import argparse
import json
import os
from datetime import date
from pathlib import Path
import psycopg
from dotenv import load_dotenv


def compare(source, mart, start, end):
    source.execute("SET LOCAL TIME ZONE 'UTC'")
    mart.execute("SET LOCAL TIME ZONE 'UTC'")
    batch_table = source.execute("SELECT to_regclass('public.import_batches')").fetchone()[0]
    eligible = "o.status='COMPLETED'"
    if batch_table:
        eligible += " AND (to_jsonb(o)->>'import_batch_id' IS NULL OR EXISTS (SELECT 1 FROM public.import_batches b WHERE b.id::text=to_jsonb(o)->>'import_batch_id' AND b.status='COMMITTED'))"
    day = "(o.completed_at::timestamptz AT TIME ZONE 'Asia/Jakarta')::date"
    payment_day = "(p.verified_at::timestamptz AT TIME ZONE 'Asia/Jakarta')::date"
    queries = {
        'sales_revenue': (f'SELECT COALESCE(SUM(i.quantity::numeric*i.unit_price_snapshot::numeric),0) FROM public.orders o JOIN public.order_items i ON i.order_id=o.id WHERE {eligible} AND {day} BETWEEN %s AND %s', (start,end)),
        'cogs': (f'SELECT CASE WHEN COUNT(*) FILTER (WHERE i.hpp_snapshot IS NULL)>0 THEN NULL ELSE COALESCE(SUM(i.quantity::numeric*i.hpp_snapshot::numeric),0) END FROM public.orders o JOIN public.order_items i ON i.order_id=o.id WHERE {eligible} AND {day} BETWEEN %s AND %s', (start,end)),
        'cash_receipts': (f"SELECT COALESCE(SUM(p.amount::numeric),0) FROM public.payments p WHERE p.status='VERIFIED' AND {payment_day} BETWEEN %s AND %s", (start,end)),
        'credit_issuance': (f'SELECT COALESCE(SUM(r.original_amount::numeric),0) FROM public.receivables r JOIN public.orders o ON o.id=r.order_id WHERE {eligible} AND {day} BETWEEN %s AND %s', (start,end)),
    }
    balances = f'''WITH balances AS (
        SELECT r.due_date::date, r.original_amount::numeric - COALESCE((
            SELECT SUM(a.allocated_amount::numeric) FROM public.receivable_payment_allocations a
            JOIN public.payments p ON p.id=a.payment_id
            WHERE a.receivable_id=r.id AND p.status='VERIFIED' AND {payment_day}<=%s),0) AS amount
        FROM public.receivables r JOIN public.orders o ON o.id=r.order_id
        WHERE {eligible} AND {day}<=%s)
        SELECT COALESCE(SUM(amount),0), COALESCE(SUM(amount) FILTER(WHERE due_date<%s),0) FROM balances'''
    outstanding, overdue = source.execute(balances,(end,end,end)).fetchone()
    mart_queries = {
        'sales_revenue': 'SELECT COALESCE(SUM(revenue),0) FROM analytics.fact_sales WHERE local_date BETWEEN %s AND %s',
        'cogs': 'SELECT CASE WHEN COUNT(*) FILTER(WHERE cogs IS NULL)>0 THEN NULL ELSE COALESCE(SUM(cogs),0) END FROM analytics.fact_sales WHERE local_date BETWEEN %s AND %s',
        'cash_receipts': 'SELECT COALESCE(SUM(verified_amount),0) FROM analytics.fact_cash_receipts WHERE local_date BETWEEN %s AND %s',
        'credit_issuance': 'SELECT COALESCE(SUM(original_amount),0) FROM analytics.fact_credit_issuance WHERE local_date BETWEEN %s AND %s',
    }
    pairs = {}
    for metric, (query, parameters) in queries.items():
        pairs[metric] = (source.execute(query,parameters).fetchone()[0], mart.execute(mart_queries[metric],(start,end)).fetchone()[0])
    mart_balances = mart.execute('SELECT COALESCE(SUM(outstanding_amount),0),COALESCE(SUM(overdue_amount),0) FROM analytics.fact_receivables_daily WHERE snapshot_date=%s',(end,)).fetchone()
    coverage = mart.execute("SELECT data_quality FROM analytics.etl_runs WHERE status='SUCCEEDED' ORDER BY finished_at DESC LIMIT 1").fetchone()
    quality = coverage[0] if coverage else {}
    covered = quality.get('snapshot_start','9999') <= str(end) <= quality.get('snapshot_end','0000')
    pairs['outstanding'] = (outstanding, mart_balances[0] if covered else None)
    pairs['overdue'] = (overdue, mart_balances[1] if covered else None)
    return {key: {'source':None if a is None else str(a), 'mart':None if b is None else str(b), 'match':a==b} for key,(a,b) in pairs.items()}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--start',required=True,type=date.fromisoformat)
    parser.add_argument('--end',required=True,type=date.fromisoformat)
    parser.add_argument('--output')
    args = parser.parse_args()
    if args.start > args.end: parser.error('Start must precede end')
    load_dotenv(Path(__file__).parents[1]/'.env')
    with psycopg.connect(os.environ['PICD_SOURCE_DSN']) as source, psycopg.connect(os.environ['PICD_WAREHOUSE_DSN']) as mart:
        source.execute('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY')
        mart.execute('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY')
        result = compare(source,mart,args.start,args.end)
    payload = json.dumps(result,indent=2)
    print(payload)
    if args.output: Path(args.output).write_text(payload,encoding='utf-8')
    return 0 if all(item['match'] for item in result.values()) else 1

if __name__ == '__main__': raise SystemExit(main())
