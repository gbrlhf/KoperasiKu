-- Run via psql with -v start=2026-11-01 -v end=2026-11-30
-- Requires SELECT access to public OLTP source and analytics. This SQL does not mutate data.
\echo Verifikasi omzet order COMPLETED, bukan checkout
SELECT 'source_sales' AS label, COALESCE(SUM(i.quantity * i.unit_price_snapshot),0)::numeric(18,2) AS value
FROM public.orders o JOIN public.order_items i ON i.order_id=o.id
WHERE o.status='COMPLETED'
  AND (o.completed_at AT TIME ZONE 'Asia/Jakarta')::date BETWEEN :'start'::date AND :'end'::date;
SELECT 'mart_sales' AS label, COALESCE(SUM(revenue),0)::numeric(18,2) AS value
FROM analytics.fact_sales WHERE local_date BETWEEN :'start'::date AND :'end'::date;
\echo Verifikasi kas dari pembayaran VERIFIED
SELECT 'source_cash' AS label, COALESCE(SUM(amount),0)::numeric(18,2) AS value
FROM public.payments WHERE status='VERIFIED'
  AND (verified_at AT TIME ZONE 'Asia/Jakarta')::date BETWEEN :'start'::date AND :'end'::date;
SELECT 'mart_cash' AS label, COALESCE(SUM(verified_amount),0)::numeric(18,2) AS value
FROM analytics.fact_cash_receipts WHERE local_date BETWEEN :'start'::date AND :'end'::date;
\echo Snapshot saldo akhir: jangan SUM lintas tanggal
SELECT 'receivables_as_of_end' AS label, COALESCE(SUM(outstanding_amount),0)::numeric(18,2) AS value
FROM analytics.fact_receivables_daily WHERE snapshot_date=:'end'::date;
