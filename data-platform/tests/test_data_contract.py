"""Contract presence tests, without external PostgreSQL or Spark service."""
import unittest
import json
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]

class ContractTests(unittest.TestCase):
    def test_schema_and_required_tables(self):
        text=(ROOT/'data-platform/analytics/schema.sql').read_text()
        for table in ['fact_sales','fact_cash_receipts','fact_stock_daily',
                      'fact_receivables_daily','fact_credit_issuance','etl_runs']:
            self.assertIn('analytics.'+table,text)
        self.assertIn('CHECK(on_hand >= 0)',text)

    def test_no_source_oltp_dml_in_writer(self):
        code=(ROOT/'data-platform/etl/warehouse_load.py').read_text()
        self.assertNotIn('INSERT INTO public.',code)
        self.assertNotIn('DELETE FROM public.',code)
        self.assertIn('pg_advisory_xact_lock',code)

    def test_fact_schemas_are_valid_json(self):
        for name in ['fact_sales','fact_receivables_daily','forecast_input']:
            spec=json.loads((ROOT/f'contracts/data-schemas/{name}.json').read_text())
            self.assertEqual(spec['type'],'object')
            self.assertGreater(len(spec['required']),0)

if __name__=='__main__':unittest.main()
