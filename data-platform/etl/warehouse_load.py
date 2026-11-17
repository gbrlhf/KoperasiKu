"""Atomic incremental mart publication from a full consistent source extract.

Diff against natural keys, refresh changed history and remove disappeared facts.
The transaction preserves the last successful mart on every error. A source
watermark prevents an older concurrent snapshot from overwriting newer results.
"""
import json
from datetime import date, datetime

TABLE_COLUMNS = {
    'dim_date': ['date_key', 'calendar_date', 'iso_week', 'month', 'year'],
    'dim_product': ['product_key', 'sku', 'name', 'category', 'lead_time_days'],
    'dim_member': ['member_key', 'department'],
    'fact_sales': ['order_item_key', 'order_id', 'date_key', 'local_date', 'completed_at', 'product_key',
        'member_key', 'quantity', 'revenue', 'cogs', 'cogs_complete', 'channel', 'payment_choice', 'is_historical', 'before_stock_cutoff'],
    'fact_cash_receipts': ['payment_id', 'date_key', 'local_date', 'verified_at', 'verified_amount',
        'method', 'source_type', 'advance_amount', 'sale_amount', 'receivable_amount'],
    'fact_operating_expenses': ['expense_id', 'date_key', 'local_date', 'amount', 'category'],
    'fact_credit_issuance': ['order_id', 'date_key', 'local_date', 'original_amount'],
    'fact_stock_daily': ['snapshot_key', 'snapshot_date', 'date_key', 'product_key', 'on_hand', 'reserved', 'available'],
    'fact_receivables_daily': ['snapshot_key', 'snapshot_date', 'date_key', 'member_key', 'outstanding_amount',
        'overdue_amount', 'open_invoices'],
    'fact_receivable_aging_daily': ['snapshot_key', 'snapshot_date', 'date_key', 'receivable_key', 'member_key',
        'due_date', 'outstanding_amount', 'days_overdue']
}
PKS = {'dim_date': ['date_key'], 'dim_product': ['product_key'], 'dim_member': ['member_key'],
    'fact_sales': ['order_item_key'], 'fact_cash_receipts': ['payment_id'],
    'fact_operating_expenses': ['expense_id'], 'fact_credit_issuance': ['order_id'],
    'fact_stock_daily': ['snapshot_key'], 'fact_receivables_daily': ['snapshot_key'],
    'fact_receivable_aging_daily': ['snapshot_key']}


def insert_run(dsn, run_id):
    import psycopg
    with psycopg.connect(dsn) as conn:
        conn.execute('INSERT INTO analytics.etl_runs(run_id,status) VALUES (%s,%s)', (run_id, 'RUNNING'))


def fail_run(dsn, run_id, error):
    import psycopg
    with psycopg.connect(dsn) as conn:
        # Record the exception category and a bounded contract message, never a DSN.
        message = type(error).__name__
        if isinstance(error, ValueError):
            message += ': ' + str(error)[:1000]
        conn.execute("UPDATE analytics.etl_runs SET status='FAILED', finished_at=now(), "
            'error_message=%s, rejected_rows=%s WHERE run_id=%s AND status=%s',
            (message, getattr(error, 'rejected_rows', 0), run_id, 'RUNNING'))


def publish(dsn, run_id, frames, asof: date, source_rows: int, quality: dict):
    import psycopg
    from psycopg import sql
    watermark = datetime.fromisoformat(quality['snapshot_observed_at'])
    changed_rows = 0
    output_rows = 0
    with psycopg.connect(dsn) as conn:
        with conn.transaction():
            conn.execute('SELECT pg_advisory_xact_lock(20261116,4)')
            previous = conn.execute("SELECT MAX(watermark) FROM analytics.etl_runs WHERE status='SUCCEEDED'").fetchone()[0]
            if previous and previous > watermark:
                raise ValueError('A newer source snapshot was already published; retry extraction')
            for table, columns in TABLE_COLUMNS.items():
                temporary = 'staging_' + table
                col_sql = sql.SQL(', ').join(map(sql.Identifier, columns))
                conn.execute(sql.SQL('CREATE TEMP TABLE {} (LIKE analytics.{} INCLUDING DEFAULTS) ON COMMIT DROP')
                    .format(sql.Identifier(temporary), sql.Identifier(table)))
                with conn.cursor() as cursor, cursor.copy(sql.SQL('COPY {} ({}) FROM STDIN').format(
                    sql.Identifier(temporary), col_sql)) as copy:
                    for row in frames[table].select(*columns).toLocalIterator():
                        copy.write_row(tuple(row[column] for column in columns))
                        output_rows += 1
                keys = PKS[table]
                matching = sql.SQL(' AND ').join(sql.SQL('old.{} = incoming.{}').format(
                    sql.Identifier(key), sql.Identifier(key)) for key in keys)
                # Full source extract permits deletion detection. Only snapshot ranges
                # actually reconstructed this run are reconciled.
                if table.startswith('fact_'):
                    parameters = []
                    scope = sql.SQL('TRUE')
                    if table == 'fact_stock_daily':
                        scope = sql.SQL('old.snapshot_date = %s')
                        parameters = [asof]
                    elif table in {'fact_receivables_daily', 'fact_receivable_aging_daily'}:
                        scope = sql.SQL('old.snapshot_date BETWEEN %s AND %s')
                        parameters = [quality['snapshot_start'], asof]
                    deleted = conn.execute(sql.SQL('DELETE FROM analytics.{} old WHERE {} AND NOT EXISTS '
                        '(SELECT 1 FROM {} incoming WHERE {})').format(
                        sql.Identifier(table), scope, sql.Identifier(temporary), matching), parameters)
                    changed_rows += deleted.rowcount
                values = [column for column in columns if column not in keys]
                updates = sql.SQL(', ').join(sql.SQL('{} = excluded.{}').format(
                    sql.Identifier(column), sql.Identifier(column)) for column in values)
                differences = sql.SQL(' OR ').join(sql.SQL('current.{} IS DISTINCT FROM excluded.{}').format(
                    sql.Identifier(column), sql.Identifier(column)) for column in values)
                result = conn.execute(sql.SQL('INSERT INTO analytics.{} AS current ({}) SELECT {} FROM {} '
                    'WHERE TRUE ON CONFLICT ({}) DO UPDATE SET {} WHERE {}').format(
                    sql.Identifier(table), col_sql, col_sql, sql.Identifier(temporary),
                    sql.SQL(', ').join(map(sql.Identifier, keys)), updates, differences))
                changed_rows += result.rowcount
            quality = {**quality, 'changed_rows': changed_rows}
            conn.execute("UPDATE analytics.etl_runs SET status='SUCCEEDED', finished_at=now(), "
                'watermark=%s,source_rows=%s,output_rows=%s,rejected_rows=0,data_quality=%s::jsonb WHERE run_id=%s',
                (watermark, source_rows, output_rows, json.dumps(quality), run_id))
    return output_rows
