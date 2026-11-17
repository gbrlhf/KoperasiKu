# Kontrak KPI resmi PIC D v1.4

**Pengakuan bisnis:** pesanan `COMPLETED` di `orders.completed_at`; waktu kas `payments.verified_at`; tagihan `receivables.due_date`. Periode `Asia/Jakarta`, minggu dimulai Senin.

| KPI / JSON | Grain | Rumus | Perhatian |
|---|---|---|---|
| sales_revenue | order item selesai | Σ(quantity × unit_price_snapshot) | Checkout belum pickup tidak dihitung, retur/nota kredit B/C belum terhubung |
| cogs | order item selesai | Σ(quantity × hpp_snapshot) | Jika HPP NULL, KPI harus NULL/incomplete, bukan dipaksakan nol |
| gross_profit | periode | sales_revenue - cogs | NULL bila ada HPP tidak diketahui |
| operating_expenses | expense POSTED/APPROVED/VERIFIED | Σ(amount) | Validasi kelengkapan oleh C masih pending |
| estimated_net_profit | periode | gross_profit - operating_expenses | **Estimasi** sampai biaya lengkap |
| cash_receipts | payment VERIFIED | Σ(amount) | Termasuk uang muka dan cicilan lama, bukan omzet |
| new_credit_sales | receivable per order selesai | Σ(original_amount) pada completion date | Piutang bukan pembayaran |
| receivables_outstanding_as_of_end | snapshot per anggota per hari | Σ(outstanding_amount) pada **1 snapshot_date** | Jangan jumlah lintas tanggal |
| overdue_outstanding_as_of_end | snapshot per anggota per hari | Σ(overdue_amount) pada **1 tanggal** | due_date lebih kecil daripada tanggal snapshot |
| stock_available_as_of_end | snapshot SKU per hari | Σ(on_hand-reserved) pada **1 tanggal** | Jangan menduga snapshot stok masa lalu |
| top_products | completed order item × SKU | Σ(quantity) dalam rentang | Sort qty terjual, bukan frekuensi pembayaran |
| aging | invoice open pada as-of | current, 1–30,31–60,61–90, >90 hari | Cicilan mengubah saldo, tidak mengubah due_date |

**Koreksi/retur:** ledger koreksi belum tersedia di repo basis, sehingga penjualan sekarang *gross completed sales* dan tidak dapat disebut **penjualan bersih final** bila ada retur sah. PIC B/C harus menyediakan event koreksi yang dapat dipakai sebagai adjustment negatif dengan audit utuh.

**Kualitas:** `last_updated_at` dari `etl_runs SUCCEEDED` terbaru, `is_stale` bila melebihi `PICD_ANALYTICS_STALE_HOURS` default 24 jam atau belum ada run; `snapshot_available=false` bila tidak ada run sukses pada tanggal requested. **Data tidak tersedia berbeda dari angka nol.**
