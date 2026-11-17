"""Reference financial invariants used by Spark, reconciliation and dependency-free tests."""
from datetime import date, datetime, timedelta, timezone
from decimal import Decimal
from zoneinfo import ZoneInfo
import hashlib
import hmac

JAKARTA = ZoneInfo('Asia/Jakarta')
MONEY = Decimal('0.01')


def money(value):
    if value is None: return None
    return Decimal(str(value)).quantize(MONEY)


def as_local_day(ts):
    if isinstance(ts,str): ts=datetime.fromisoformat(ts.replace('Z','+00:00'))
    if not isinstance(ts,datetime) or ts.tzinfo is None:
        raise ValueError('Timestamps must carry UTC/timezone offset')
    return ts.astimezone(JAKARTA).date()


def due_date(pickup):
    return as_local_day(pickup)+timedelta(days=7)


def aging_bucket(due:date, asof:date):
    days=(asof-due).days
    return 'current' if days <= 0 else ('1_30' if days<=30 else ('31_60' if days<=60 else ('61_90' if days<=90 else 'gt_90')))


def pseudonym(member_id, secret):
    if member_id is None:
        return 'UNKNOWN_MEMBER'
    return hmac.new(secret.encode(), str(member_id).encode(), hashlib.sha256).hexdigest()


def sales_line(order, item):
    if order['status']!='COMPLETED' or not order.get('completed_at'):return None
    qty=int(item['quantity'])
    price=money(item['unit_price_snapshot'])
    cost=money(item.get('hpp_snapshot'))
    if qty<=0 or price is None or price<0 or (cost is not None and cost<0):
        raise ValueError('Invalid quantity/price/cost')
    return {'order_item_key':str(item['id']), 'local_date':as_local_day(order['completed_at']),
            'revenue':price*qty, 'cogs':None if cost is None else cost*qty,
            'cogs_complete':cost is not None}


def verified_cash(payment):
    if payment.get('status') != 'VERIFIED': return None
    amount=money(payment['amount'])
    if amount is None or amount<=0 or not payment.get('verified_at'):
        raise ValueError('Verified payment must have amount >0 and verification timestamp')
    return {'local_date':as_local_day(payment['verified_at']), 'amount':amount}


def outstanding_asof(receivable, allocations, payments_by_id, asof):
    created=as_local_day(receivable['created_at'])
    if created>asof:return Decimal('0.00')
    amount=money(receivable['original_amount'])
    for allocation in allocations:
        if str(allocation['receivable_id'])!=str(receivable['id']):continue
        p=payments_by_id.get(str(allocation['payment_id']))
        if p and p['status']=='VERIFIED' and as_local_day(p['verified_at'])<=asof:
            amount-=money(allocation['allocated_amount'])
    if amount<0:raise ValueError('Receivable has over-allocation')
    return amount
