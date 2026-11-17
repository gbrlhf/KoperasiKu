"""Spark facts at source grain, with Jakarta dates and exact decimal money."""
from datetime import date
from pyspark.sql import functions as F, types as T
from .source import SPECS

MONEY = T.DecimalType(18, 2)


def load_sources(spark, snapshot_dir):
    for table, spec in SPECS.items():
        fields = [*spec['required'], *spec['optional']]
        frame = spark.read.schema(T.StructType([T.StructField(k, T.StringType()) for k in fields])) \
            .json(str(snapshot_dir / (table + '.jsonl')))
        frame.createOrReplaceTempView('src_' + table)


def local_day(column):
    return F.to_date(F.from_utc_timestamp(F.to_timestamp(column), 'Asia/Jakarta'))


def with_date_key(frame, column):
    return frame.withColumn('date_key', F.date_format(column, 'yyyyMMdd').cast('int'))


def fail_if(frame, predicate, reason):
    from .quality_checks import DataQualityError
    count = frame.where(predicate).count()
    if count:
        raise DataQualityError(reason, count)


def build_frames(spark, asof: date, secret=None, snapshot_start=None, history_cutoff=None):
    """The source extractor has already HMAC-pseudonymized all member IDs.

    Recompute invoice history from verified events. Incremental publication changes
    only differing natural keys, so late arrivals correct their original dates.
    """
    from .quality_checks import validate_sources
    validate_sources(spark)
    orders = spark.table('src_orders')
    items = spark.table('src_order_items')
    fail_if(items, F.col('quantity').isNull() | ~F.col('quantity').rlike(r'^[0-9]+$'),
        'Quantity must be a positive integer')
    products = spark.table('src_products')
    members = spark.table('src_members')
    payments = spark.table('src_payments')
    allocations = spark.table('src_receivable_payment_allocations')
    receivables = spark.table('src_receivables')
    inventory = spark.table('src_inventory_balances')
    expenses = spark.table('src_operating_expenses')
    batches = spark.table('src_import_batches').select(
        F.col('id').alias('batch_id'), F.col('status').alias('batch_status'))
    zero = F.lit(0).cast(MONEY)
    snapshot_day = F.lit(asof.isoformat()).cast('date')

    eligible = orders.join(batches, orders.import_batch_id == batches.batch_id, 'left') \
        .filter(F.col('import_batch_id').isNull() | (F.col('batch_status') == 'COMMITTED'))
    completed = eligible.filter(F.col('status') == 'COMPLETED')
    fail_if(completed, F.to_timestamp('completed_at').isNull(), 'Completed order lacks valid completed_at')
    sale = items.alias('i').join(completed.alias('o'), F.col('i.order_id') == F.col('o.id')) \
        .select(F.col('i.id').alias('order_item_key'), F.col('o.id').alias('order_id'),
            F.col('i.product_id').alias('product_key'),
            F.coalesce(F.col('o.member_id'), F.lit('UNKNOWN_MEMBER')).alias('member_key'),
            F.col('i.quantity').cast('int').alias('quantity'),
            F.col('i.unit_price_snapshot').cast(MONEY).alias('price'),
            F.col('i.hpp_snapshot').cast(MONEY).alias('unit_cogs'),
            F.col('i.hpp_snapshot').alias('original_cost'),
            F.to_timestamp('o.completed_at').alias('completed_at'),
            F.col('o.channel').alias('channel'), F.col('o.payment_choice').alias('payment_choice'),
            F.col('o.import_batch_id').isNotNull().alias('is_historical'))
    fail_if(sale, F.col('quantity').isNull() | (F.col('quantity') <= 0) |
        F.col('price').isNull() | (F.col('price') < 0) |
        (F.col('original_cost').isNotNull() & F.col('unit_cogs').isNull()) |
        (F.col('unit_cogs') < 0), 'Completed items have invalid quantity, price or cost')
    sales = sale.withColumn('local_date', local_day('completed_at')) \
        .withColumn('revenue', (F.col('quantity') * F.col('price')).cast(MONEY)) \
        .withColumn('cogs', (F.col('quantity') * F.col('unit_cogs')).cast(MONEY)) \
        .withColumn('cogs_complete', F.col('unit_cogs').isNotNull()) \
        .drop('price', 'unit_cogs', 'original_cost')
    sales = sales.withColumn('before_stock_cutoff',
        (F.col('is_historical') & (F.col('local_date') < F.lit(history_cutoff).cast('date')))
        if history_cutoff else F.lit(None).cast('boolean'))
    fail_if(sales, F.col('local_date') > snapshot_day, 'Completed sales dated after snapshot')
    if sales.join(products, sales.product_key == products.id, 'left_anti').limit(1).count():
        raise ValueError('Completed sale references missing product')

    # One cash fact per payment, allocations aggregated before joining.
    allocation_totals = allocations.groupBy('payment_id').agg(
        F.sum(F.col('allocated_amount').cast(MONEY)).cast(MONEY).alias('receivable_amount'))
    payment_orders = eligible.select(F.col('id').alias('linked_order_id'),
        F.to_timestamp('completed_at').alias('order_completed_at'), F.col('status').alias('order_status'))
    verified = payments.filter(F.col('status') == 'VERIFIED') \
        .withColumn('verified_at', F.to_timestamp('verified_at')) \
        .withColumn('verified_amount', F.col('amount').cast(MONEY))
    fail_if(verified, F.col('verified_at').isNull() | F.col('verified_amount').isNull() |
        (F.col('verified_amount') <= 0), 'Verified payments require positive amount and verified_at')
    cash = verified.join(allocation_totals, verified.id == allocation_totals.payment_id, 'left') \
        .drop('payment_id').join(payment_orders, F.col('order_id') == F.col('linked_order_id'), 'left') \
        .withColumn('receivable_amount', F.coalesce(F.col('receivable_amount'), zero)) \
        .withColumn('unallocated', F.col('verified_amount') - F.col('receivable_amount'))
    fail_if(cash, (F.col('unallocated') > 0) & F.col('linked_order_id').isNull(),
        'Unallocated cash requires an eligible order; check imports and payment allocation')
    cash = cash.withColumn('advance_amount', F.when(
        (F.col('order_completed_at').isNull()) | (F.col('verified_at') < F.col('order_completed_at')),
        F.col('unallocated')).otherwise(zero).cast(MONEY)) \
        .withColumn('sale_amount', (F.col('unallocated') - F.col('advance_amount')).cast(MONEY)) \
        .withColumn('source_type', F.when((F.col('receivable_amount') > 0) & (F.col('unallocated') > 0), 'MIXED')
            .when(F.col('receivable_amount') > 0, 'RECEIVABLE')
            .when(F.col('advance_amount') > 0, 'ADVANCE').otherwise('SALE')) \
        .withColumn('local_date', local_day('verified_at')) \
        .select(F.col('id').alias('payment_id'), 'local_date', 'verified_at', 'verified_amount',
            'method', 'source_type', 'receivable_amount', 'advance_amount', 'sale_amount')
    fail_if(cash, F.col('local_date') > snapshot_day, 'Verified payment dated after snapshot')

    expense = expenses.filter(F.col('status').isNull() | F.col('status').isin('POSTED', 'APPROVED', 'VERIFIED')) \
        .select(F.col('id').alias('expense_id'), F.col('amount').cast(MONEY).alias('amount'), 'category',
            F.when(F.col('expense_date').rlike(r'^\d{4}-\d{2}-\d{2}$'), F.to_date('expense_date'))
                .otherwise(local_day('expense_date')).alias('local_date'))
    fail_if(expense, F.col('amount').isNull() | (F.col('amount') < 0) | F.col('local_date').isNull(),
        'Invalid posted expense amount or date')
    expense = expense.filter(F.col('local_date') <= snapshot_day)

    product_dim = products.select(F.col('id').alias('product_key'), 'sku', 'name', 'category',
        F.col('lead_time_days').cast('int').alias('lead_time_days'))
    member_keys = members.select(F.col('id').alias('member_key')) \
        .unionByName(orders.select(F.col('member_id').alias('member_key'))) \
        .unionByName(receivables.select(F.col('member_id').alias('member_key'))) \
        .unionByName(spark.createDataFrame([('UNKNOWN_MEMBER',)], ['member_key']))
    dim_member = member_keys.where(F.col('member_key').isNotNull()).distinct() \
        .withColumn('department', F.lit(None).cast('string'))

    if receivables.join(completed, receivables.order_id == completed.id, 'left_anti').limit(1).count():
        raise ValueError('Receivable requires a completed eligible order')
    invoice = receivables.alias('r').join(completed.alias('o'), F.col('r.order_id') == F.col('o.id')) \
        .select(F.col('r.id').alias('receivable_key'), F.col('r.order_id').alias('order_id'),
            F.col('r.member_id').alias('member_key'), local_day('o.completed_at').alias('issued_date'),
            F.to_date('r.due_date').alias('due_date'),
            F.col('r.original_amount').cast(MONEY).alias('original_amount'),
            F.col('r.outstanding_amount').cast(MONEY).alias('source_outstanding'))
    fail_if(invoice, F.col('member_key').isNull() | F.col('due_date').isNull() |
        F.col('original_amount').isNull() | (F.col('original_amount') < 0) |
        F.col('source_outstanding').isNull() | (F.col('source_outstanding') < 0), 'Invalid receivable')
    fail_if(invoice, F.col('due_date') != F.date_add('issued_date', 7), 'Due date must be pickup date plus seven days')
    issuance = invoice.select('order_id', F.col('issued_date').alias('local_date'), 'original_amount')
    applied = allocations.alias('a').join(payments.alias('p'), F.col('a.payment_id') == F.col('p.id')) \
        .filter(F.col('p.status') == 'VERIFIED') \
        .select(F.col('a.receivable_id').alias('receivable_key'),
            local_day('p.verified_at').alias('payment_date'),
            F.col('a.allocated_amount').cast(MONEY).alias('allocated_amount'))
    fail_if(applied, F.col('payment_date').isNull() | (F.col('payment_date') > snapshot_day),
        'Verified allocation has invalid payment date')
    if applied.join(invoice, 'receivable_key', 'left_anti').limit(1).count():
        raise ValueError('Allocation references unknown invoice')
    fail_if(applied.join(invoice.select('receivable_key', 'issued_date'), 'receivable_key'),
        F.col('payment_date') < F.col('issued_date'), 'Invoice allocation predates handover')

    first_invoice = invoice.agg(F.min('issued_date')).first()[0]
    start = snapshot_start or first_invoice or asof
    if start > asof:
        raise ValueError('snapshot_start must be <= as-of')
    dates = spark.range(1).select(F.explode(F.sequence(F.lit(start.isoformat()).cast('date'),
        snapshot_day)).alias('snapshot_date'))
    dated_invoice = invoice.crossJoin(dates).where(F.col('issued_date') <= F.col('snapshot_date'))
    paid_daily = applied.groupBy('receivable_key', 'payment_date').agg(F.sum('allocated_amount').alias('paid'))
    age = dated_invoice.join(paid_daily, 'receivable_key', 'left') \
        .groupBy('receivable_key', 'order_id', 'member_key', 'issued_date', 'due_date',
            'original_amount', 'source_outstanding', 'snapshot_date') \
        .agg(F.sum(F.when(F.col('payment_date') <= F.col('snapshot_date'), F.col('paid'))
            .otherwise(zero)).alias('paid_asof')) \
        .withColumn('outstanding_amount', (F.col('original_amount') - F.col('paid_asof')).cast(MONEY))
    fail_if(age, F.col('outstanding_amount') < 0, 'Invoice is overallocated')
    fail_if(age, (F.col('snapshot_date') == snapshot_day) &
        (F.col('outstanding_amount') != F.col('source_outstanding')),
        'Balance differs from verified allocation ledger; credit-note contract required')
    age = age.withColumn('days_overdue', F.datediff('snapshot_date', 'due_date')) \
        .withColumn('snapshot_key', F.concat_ws(':', F.col('snapshot_date').cast('string'), 'receivable_key')) \
        .select('snapshot_key', 'snapshot_date', 'receivable_key', 'member_key', 'due_date',
            'outstanding_amount', 'days_overdue')
    ar = age.withColumn('overdue_amount', F.when(F.col('days_overdue') > 0,
        F.col('outstanding_amount')).otherwise(zero)) \
        .groupBy('snapshot_date', 'member_key') \
        .agg(F.sum('outstanding_amount').cast(MONEY).alias('outstanding_amount'),
            F.sum('overdue_amount').cast(MONEY).alias('overdue_amount'),
            F.sum(F.when(F.col('outstanding_amount') > 0, 1).otherwise(0)).cast('int').alias('open_invoices')) \
        .withColumn('snapshot_key', F.concat_ws(':', F.col('snapshot_date').cast('string'), 'member_key'))

    stock = inventory.select(F.col('product_id').alias('product_key'),
        F.col('on_hand').cast('int').alias('on_hand'), F.col('reserved').cast('int').alias('reserved'))
    fail_if(stock, F.col('on_hand').isNull() | F.col('reserved').isNull() | (F.col('reserved') < 0) |
        (F.col('on_hand') < F.col('reserved')), 'Inventory violates on_hand >= reserved >= 0')
    if stock.join(products, stock.product_key == products.id, 'left_anti').limit(1).count():
        raise ValueError('Inventory references missing product')
    stock = stock.withColumn('snapshot_date', snapshot_day) \
        .withColumn('available', F.col('on_hand') - F.col('reserved')) \
        .withColumn('snapshot_key', F.concat_ws(':', F.col('snapshot_date').cast('string'), 'product_key'))
    facts = {'fact_sales': (sales, 'local_date'), 'fact_cash_receipts': (cash, 'local_date'),
        'fact_operating_expenses': (expense, 'local_date'), 'fact_credit_issuance': (issuance, 'local_date'),
        'fact_stock_daily': (stock, 'snapshot_date'), 'fact_receivables_daily': (ar, 'snapshot_date'),
        'fact_receivable_aging_daily': (age, 'snapshot_date')}
    frames = {name: with_date_key(frame, day) for name, (frame, day) in facts.items()}
    day_frames = [frame.select(F.col(day).alias('calendar_date')) for frame, day in facts.values()]
    days = dates.select(F.col('snapshot_date').alias('calendar_date'))
    for frame in day_frames:
        days = days.unionByName(frame)
    frames['dim_date'] = with_date_key(days.distinct(), 'calendar_date') \
        .withColumn('iso_week', F.weekofyear('calendar_date')) \
        .withColumn('month', F.month('calendar_date')).withColumn('year', F.year('calendar_date'))
    frames['dim_product'] = product_dim
    frames['dim_member'] = dim_member
    first_sale = sales.agg(F.min('local_date')).first()[0]
    quality = {'cogs_complete': not sales.where(~F.col('cogs_complete')).limit(1).count(),
        'expenses_complete': False, 'rejected_sales': 0, 'rejected_cash': 0, 'rejected_expenses': 0,
        'snapshot_start': start.isoformat(), 'snapshot_end': asof.isoformat(),
        'range_start': (first_sale or asof).isoformat(), 'range_end': asof.isoformat(),
        'stock_snapshot_date': asof.isoformat(), 'snapshot_date': asof.isoformat(),
        'source_adjustments_supported': False, 'history_cutoff': str(history_cutoff) if history_cutoff else None, 'historical_inventory_cutoff_applied': history_cutoff is not None,
        'load_strategy': 'natural_key_incremental_diff_full_consistent_extract'}
    return frames, quality
