"""Spark source checks that fail the run before any mart publication."""
from pyspark.sql import functions as F


class DataQualityError(ValueError):
    def __init__(self, message, rejected_rows):
        super().__init__(message)
        self.rejected_rows = rejected_rows


def assert_unique(frame, keys, table):
    from functools import reduce
    if frame.filter(reduce(lambda a, b: a | b, [F.col(key).isNull() for key in keys])).limit(1).count():
        raise ValueError(f'{table}: null natural key')
    if frame.groupBy(*keys).count().where(F.col('count')>1).limit(1).count():
        raise ValueError(f'{table}: duplicate natural key ({keys})')


def validate_sources(spark):
    keys={
        'orders':['id'], 'order_items':['id'], 'payments':['id'],
        'receivables':['id'], 'products':['id'], 'inventory_balances':['product_id'],
        'members':['id'], 'operating_expenses':['id'],
        'receivable_payment_allocations':['id'], 'import_batches':['id'],
    }
    for table, pk in keys.items():
        assert_unique(spark.table('src_'+table),pk,table)
    # At most one invoice per order under v1.4.
    assert_unique(spark.table('src_receivables'),['order_id'],'receivables.order_id')
    assert_unique(spark.table('src_receivable_payment_allocations'),
                  ['payment_id', 'receivable_id'], 'receivable_payment_allocations')
    allocated=spark.table('src_receivable_payment_allocations')\
        .select('payment_id',F.col('allocated_amount').cast('decimal(18,2)').alias('amount'))
    if allocated.where(F.col('amount').isNull() | (F.col('amount')<=0)).limit(1).count():
        raise ValueError('Allocations must be positive and numeric')
    payment=spark.table('src_payments').select(F.col('id').alias('payment_id'),
                                                 F.col('amount').cast('decimal(18,2)').alias('paid'))
    invalid=allocated.groupBy('payment_id').agg(F.sum('amount').alias('allocated'))\
        .join(payment,'payment_id','left')\
        .filter(F.col('paid').isNull() | (F.col('allocated')>F.col('paid')))
    if invalid.limit(1).count():
        raise ValueError('Receivable allocations exceed / lack matching source payment')
