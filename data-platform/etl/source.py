"""Consistent read-only PostgreSQL snapshot to temporary JSONL, never uploaded raw.

Source columns intentionally form a PIC B/C integration contract. Optional aliases only
cover naming differences; required columns cannot be silently manufactured.
"""
import json
from datetime import date, datetime
from decimal import Decimal
from pathlib import Path
from .domain import pseudonym

# canonical field -> accepted OLTP names; [] means nullable optional.
SPECS = {
 'orders': {'required': {'id':['id'], 'member_id':['member_id'], 'status':['status'],
                          'completed_at':['completed_at'], 'payment_choice':['payment_choice','payment_method'],
                          'channel':['channel']},
            'optional': {'import_batch_id':['import_batch_id']}},
 'order_items': {'required': {'id':['id'], 'order_id':['order_id'], 'product_id':['product_id'],
                                'quantity':['quantity'], 'unit_price_snapshot':['unit_price_snapshot'],
                                'hpp_snapshot':['hpp_snapshot']}, 'optional': {}},
 'payments': {'required': {'id':['id'], 'amount':['amount'], 'status':['status'],
                           'verified_at':['verified_at'], 'order_id':['order_id']},
              'optional': {'method':['method'], 'member_id':['member_id']}},
 'receivables': {'required': {'id':['id'], 'order_id':['order_id'], 'member_id':['member_id'],
                              'original_amount':['original_amount'], 'outstanding_amount':['outstanding_amount'],
                              'due_date':['due_date'], 'created_at':['created_at']}, 'optional': {}},
 'receivable_payment_allocations': {'required': {'id':['id'], 'payment_id':['payment_id'],
                                                'receivable_id':['receivable_id'], 'allocated_amount':['allocated_amount']}, 'optional': {}},
 'products': {'required': {'id':['id'], 'sku':['sku']},
              'optional': {'name':['name'], 'category':['category','category_name'],
                           'lead_time_days':['lead_time_days']}},
 'inventory_balances': {'required': {'product_id':['product_id'], 'on_hand':['on_hand'],
                                      'reserved':['reserved']}, 'optional': {}},
 'members': {'required': {'id':['id']}, 'optional': {}},
 'operating_expenses': {'required': {'id':['id'], 'amount':['amount'],
                                    'expense_date':['expense_date','date','incurred_at']},
                        'optional': {'category':['category','type'], 'status':['status']}},
 'import_batches': {'required': {'id':['id'], 'status':['status']}, 'optional': {}}
}

class SourceContractError(RuntimeError):
    pass


def json_default(value):
    if isinstance(value, (datetime,date)):return value.isoformat()
    if isinstance(value,Decimal):return str(value)
    from uuid import UUID
    if isinstance(value, UUID): return str(value)
    raise TypeError(f'Cannot encode {type(value).__name__}')


def optional_expression(table, canonical, aliases, catalog):
    """Adapt C's normalized master data without changing OLTP or leaking PII."""
    from psycopg import sql
    cols = catalog.get(table, set())
    source = next((a for a in aliases if a in cols), None)
    if source:
        return sql.Identifier('src', source)
    relations = {'category': ('category_id', 'categories', 'name'),
                 'lead_time_days': ('supplier_id', 'suppliers', 'lead_time_days')}
    if table == 'products' and canonical in relations:
        fk, relation, field = relations[canonical]
        if fk in cols:
            if not {'id', field}.issubset(catalog.get(relation, set())):
                raise SourceContractError(f'products.{fk} requires readable {relation}(id,{field})')
            return sql.SQL('(SELECT r.{} FROM public.{} r WHERE r.id = src.{})').format(
                sql.Identifier(field), sql.Identifier(relation), sql.Identifier(fk))
    return sql.SQL('NULL')


def extract_snapshot(dsn: str, output: Path, secret: str):
    import psycopg
    from psycopg import sql
    output.mkdir(parents=True, exist_ok=True)
    counts = {}
    # Keep a single stable snapshot spanning orders, payments, allocations and balances.
    with psycopg.connect(dsn, autocommit=True) as conn:
        conn.execute('BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY')
        try:
            conn.execute("SET LOCAL TIME ZONE 'UTC'")
            observed_at = conn.execute('SELECT statement_timestamp(), txid_current_snapshot()').fetchone()[0]
            from .preflight import inspect_source
            contract = inspect_source(conn)
            if not contract['compatible']:
                raise SourceContractError(json.dumps(contract['issues']))
            present = {r[0] for r in conn.execute("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname='public'")}
            catalog = {}
            for table, column in conn.execute("SELECT table_name,column_name FROM information_schema.columns WHERE table_schema='public'"):
                catalog.setdefault(table, set()).add(column)
            for table, spec in SPECS.items():
                if table == 'import_batches' and table not in present:
                    # import_batches is only optional if orders has no historical import IDs;
                    # otherwise validate below after reading orders metadata.
                    continue
                if table not in present:
                    raise SourceContractError(f'PIC B/C must provide public.{table} before live ETL')
                cols = {r[0] for r in conn.execute('SELECT column_name FROM information_schema.columns '
                          'WHERE table_schema=%s AND table_name=%s', ('public',table))}
                selected = []
                for canonical, aliases in spec['required'].items():
                    source = next((a for a in aliases if a in cols), None)
                    if not source:
                        raise SourceContractError(f'public.{table} missing mandatory column {canonical} (accepted {aliases})')
                    selected.append(sql.SQL('{} AS {}').format(sql.Identifier('src', source),sql.Identifier(canonical)))
                for canonical, aliases in spec['optional'].items():
                    selected.append(sql.SQL('{} AS {}').format(
                        optional_expression(table, canonical, aliases, catalog), sql.Identifier(canonical)))
                query = sql.SQL('SELECT {} FROM public.{} src').format(sql.SQL(', ').join(selected),sql.Identifier(table))
                count=0
                path=output / (table+'.jsonl')
                with conn.cursor(name='picd_stream_'+table) as cur, path.open('w',encoding='utf-8') as writer:
                    cur.execute(query)
                    columns=[desc.name for desc in cur.description]
                    for block in iter(lambda:cur.fetchmany(2000), []):
                        for record in block:
                            row = dict(zip(columns, record))
                            if table == 'members':
                                row['id'] = pseudonym(row['id'], secret)
                            if row.get('member_id') is not None:
                                row['member_id'] = pseudonym(row['member_id'], secret)
                            writer.write(json.dumps(row,default=json_default,ensure_ascii=False)+'\n')
                        count+=len(block)
                counts[table]=count
            if 'import_batches' not in present:
                # Fail if orders reference any imports but committed state cannot be verified.
                if any(json.loads(line).get('import_batch_id') for line in (output/'orders.jsonl').open(encoding='utf-8')):
                    raise SourceContractError('orders.import_batch_id used but import_batches table is absent')
                (output/'import_batches.jsonl').write_text('',encoding='utf-8')
                counts['import_batches']=0
            conn.execute('COMMIT')
        except Exception:
            conn.execute('ROLLBACK')
            raise
    metadata = {'observed_at': observed_at.isoformat(), 'source_rows': counts,
                'member_ids_pseudonymized': True, 'naive_timestamps_timezone': 'UTC'}
    (output / 'snapshot.json').write_text(json.dumps(metadata), encoding='utf-8')
    return counts, observed_at
