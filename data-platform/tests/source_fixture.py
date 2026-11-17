from etl.source import SPECS

def source_fixture():
    source = {table: [] for table in SPECS}
    source['orders'] = [dict(id='o1',member_id='pseudo1',status='COMPLETED',completed_at='2026-11-16T03:00:00Z',payment_choice='CREDIT',channel='POS',import_batch_id=None)]
    source['order_items'] = [dict(id='i1',order_id='o1',product_id='p1',quantity='2',unit_price_snapshot='100000',hpp_snapshot='70000')]
    source['products'] = [dict(id='p1',sku='BERAS',name='Beras',category='Pangan',lead_time_days='3')]
    source['members'] = [dict(id='pseudo1')]
    source['inventory_balances'] = [dict(product_id='p1',on_hand='98',reserved='0')]
    source['receivables'] = [dict(id='r1',order_id='o1',member_id='pseudo1',original_amount='200000',outstanding_amount='125000',due_date='2026-11-23',created_at='2026-11-16T03:00:00Z')]
    source['payments'] = [dict(id='pay1',amount='75000',status='VERIFIED',verified_at='2026-11-20T04:00:00Z',order_id=None,method='CASH',member_id='pseudo1')]
    source['receivable_payment_allocations'] = [dict(id='a1',payment_id='pay1',receivable_id='r1',allocated_amount='75000')]
    return source
