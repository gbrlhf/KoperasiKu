"""Read-only source contract audit. Run python -m etl.preflight before integration."""
import argparse
import json
import os
from .source import SPECS


def inspect_source(connection):
    columns = {}
    for table, column in connection.execute(
        "SELECT table_name,column_name FROM information_schema.columns WHERE table_schema='public'"
    ):
        columns.setdefault(table, set()).add(column)
    issues = []
    mapping = {}
    for table, spec in SPECS.items():
        if table == 'import_batches' and table not in columns:
            continue
        if table not in columns:
            issues.append({'table': table, 'code': 'MISSING_TABLE'})
            continue
        mapping[table] = {}
        for field, aliases in spec['required'].items():
            source = next((name for name in aliases if name in columns[table]), None)
            if source is None:
                issues.append({'table': table, 'field': field, 'code': 'MISSING_REQUIRED_COLUMN'})
            else:
                mapping[table][field] = source
    from .source import optional_expression, SourceContractError
    for field, aliases in SPECS['products']['optional'].items():
        try:
            optional_expression('products', field, aliases, columns)
        except SourceContractError as error:
            issues.append({'table': 'products', 'field': field, 'code': 'MISSING_RELATION', 'detail': str(error)})
    return {'compatible': not issues, 'issues': issues, 'mapping': mapping,
            'timestamp_contract': 'UTC for timezone-naive values; verified_at must be an immutable verification event'}


def main():
    import psycopg
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', help='Optional JSON audit file')
    args = parser.parse_args()
    from dotenv import load_dotenv
    from pathlib import Path
    load_dotenv(Path(__file__).parents[1] / '.env')
    dsn = os.getenv('PICD_SOURCE_DSN')
    if not dsn:
        parser.error('Set PICD_SOURCE_DSN to a read-only PostgreSQL connection')
    with psycopg.connect(dsn) as conn:
        conn.execute('SET TRANSACTION READ ONLY')
        report = inspect_source(conn)
    payload = json.dumps(report, indent=2)
    if args.output:
        from pathlib import Path
        Path(args.output).write_text(payload, encoding='utf-8')
    print(payload)
    return 0 if report['compatible'] else 1


if __name__ == '__main__':
    raise SystemExit(main())
