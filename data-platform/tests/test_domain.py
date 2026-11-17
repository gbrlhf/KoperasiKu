"""PIC D reference cases D-T01..D-T08, stdlib only (no Spark required)."""
import sys
import unittest
from pathlib import Path
from datetime import date
from decimal import Decimal
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from etl.domain import money, sales_line,verified_cash,outstanding_asof,aging_bucket,due_date,pseudonym

PICKUP='2026-11-16T03:00:00+00:00'  # 10:00 Asia/Jakarta
PAYDAY='2026-11-20T02:00:00+00:00'

class PicDReferenceTests(unittest.TestCase):
    def setUp(self):
        self.order={'id':'o1','status':'COMPLETED','completed_at':PICKUP}
        self.item={'id':'i1','quantity':2,'unit_price_snapshot':'100000.00','hpp_snapshot':'70000.00'}
        self.receivable={'id':'r1','created_at':PICKUP,'original_amount':'200000.00'}
        self.payment={'id':'p1','status':'VERIFIED','amount':'75000.00','verified_at':PAYDAY}
        self.alloc={'receivable_id':'r1','payment_id':'p1','allocated_amount':'75000.00'}

    def test_DT01_completed_credit_sale_separate_from_cash(self):
        fact=sales_line(self.order,self.item)
        self.assertEqual(fact['revenue'],Decimal('200000.00'))
        self.assertEqual(fact['cogs'],Decimal('140000.00'))
        self.assertEqual(fact['local_date'],date(2026,11,16))
        self.assertEqual(outstanding_asof(self.receivable,[],{},date(2026,11,16)),Decimal('200000.00'))
        self.assertIsNone(verified_cash({'status':'PENDING_VERIFICATION'}))

    def test_DT02_installment_cash_not_new_sale(self):
        before=sales_line(self.order,self.item)['revenue']
        cash=verified_cash(self.payment)
        remaining=outstanding_asof(self.receivable,[self.alloc],{'p1':self.payment},date(2026,11,20))
        self.assertEqual(cash['amount'],Decimal('75000.00'))
        self.assertEqual(remaining,Decimal('125000.00'))
        self.assertEqual(sales_line(self.order,self.item)['revenue'],before)

    def test_DT03_checkout_only_is_not_sale(self):
        order={**self.order,'status':'PENDING_PICKUP','completed_at':None}
        self.assertIsNone(sales_line(order,self.item))

    def test_DT04_time_zone_boundaries(self):
        self.assertEqual(due_date(PICKUP),date(2026,11,23))
        self.assertEqual(due_date('2026-11-16T18:00:00Z'),date(2026,11,24))

    def test_DT05_snapshot_not_summable(self):
        day1=outstanding_asof(self.receivable,[],{},date(2026,11,16))
        day2=outstanding_asof(self.receivable,[self.alloc],{'p1':self.payment},date(2026,11,20))
        self.assertEqual(day2,Decimal('125000.00'))
        self.assertNotEqual(day1+day2,day2)  # never SUM balance across dates

    def test_DT06_verified_only(self):
        pending={**self.payment,'status':'PENDING_VERIFICATION'}
        self.assertIsNone(verified_cash(pending))
        self.assertEqual(outstanding_asof(self.receivable,[self.alloc],{'p1':pending},
                                        date(2026,11,20)),Decimal('200000.00'))

    def test_DT07_idempotent_primary_keys_concept(self):
        rows=[sales_line(self.order,self.item),sales_line(self.order,self.item)]
        self.assertEqual(len({r['order_item_key'] for r in rows}),1)

    def test_DT08_overdue_aging(self):
        self.assertEqual(aging_bucket(date(2026,11,23),date(2026,11,23)),'current')
        self.assertEqual(aging_bucket(date(2026,11,23),date(2026,11,24)),'1_30')
        self.assertEqual(aging_bucket(date(2026,11,23),date(2027,3,1)),'gt_90')

    def test_incomplete_hpp_never_guessed(self):
        fact=sales_line(self.order,{**self.item,'hpp_snapshot':None})
        self.assertFalse(fact['cogs_complete'])
        self.assertIsNone(fact['cogs'])

    def test_no_negative_or_zero_payment(self):
        with self.assertRaises(ValueError):verified_cash({**self.payment,'amount':'0'})

    def test_member_pseudonyms_are_stable_not_plain_id(self):
        key=pseudonym('EMP-123','secret-with-a-strong-random-value-123456')
        self.assertEqual(len(key),64)
        self.assertEqual(key,pseudonym('EMP-123','secret-with-a-strong-random-value-123456'))
        self.assertNotIn('EMP-123',key)

    def test_overpayment_is_rejected(self):
        excess={**self.alloc,'allocated_amount':'200001'}
        with self.assertRaises(ValueError):
            outstanding_asof(self.receivable,[excess],{'p1':self.payment},date(2026,11,20))

if __name__=='__main__':unittest.main()
