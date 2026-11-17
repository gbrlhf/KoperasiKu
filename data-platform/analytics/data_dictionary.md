# Kamus Data / Lineage KOPERADATA PIC D

## OLTP â†’ Warehouse

| Data Mart | Sumber | Natural key | Waktu bisnis | Nullable / aturan |
|---|---|---|---|---|
| dim_date | fact event/snapshot dates | `date_key=YYYYMMDD` | Asia/Jakarta | Gregorian, ISO week Senin |
| dim_product | products + categories + suppliers | `product_key = products.id` | latest master | SKU wajib, name/category/lead time opsional |
| dim_member | members, orders, receivables | `member_key = HMAC-SHA256(secret, member_id)` | not temporal | POS tanpa anggota sentinel pseudo; tanpa nama/NIK/telepon |
| fact_sales | orders + order_items | order_item.id | orders.completed_at | hanya completed; HPP NULL jika belum diketahui |
| fact_cash_receipts | payments | payments.id | payments.verified_at | hanya VERIFIED; uang muka dan cicilan tidak dihitung ganda |
| fact_credit_issuance | receivables + orders | orders.id | orders.completed_at | hanya sisa kredit saat pickup; bukan semua transaksi CREDIT penuh |
| fact_operating_expenses | operating_expenses | expense.id | expense_date | only approved/posted if status exists |
| fact_stock_daily | inventory_balances | date + product.id | snapshot saat run | tidak merekonstruksi stok lampau |
| fact_receivables_daily | receivables + allocations + verified payments | date + pseudonymous member | snapshot saat run | 1 member/hari; dibentuk dari original minus VERIFIED allocation |
| fact_receivable_aging_daily | receivables + allocations + verified payments | date + receivable.id | snapshot saat run | 1 invoice/hari untuk aging, tanpa identitas karyawan |
| etl_runs | worker PIC D | run UUID | started/finished UTC | status, jumlah input/output/ditolak, quality flags |

## Parquet

Prefix penyimpanan: `s3://<bucket>/picd/runs/<run_id>/<dataset>/part-*`. Data di satu run ID bersifat immutable secara nama (run baru menulis prefix baru). **Tidak ada nama/NIK/nomor karyawan** di parquet. Raw OLTP snapshot `source-local-private/*.jsonl` bersifat sementara dan dihapus otomatis.

## Strategy rerun

PIC D saat ini menggunakan **full consistent-source reload** untuk `fact_sales`, `fact_cash_receipts`, `fact_credit_issuance` dan `fact_operating_expenses`, dalam **satu transaksi** setelah membuat dimensi. Ini idempotent per natural key dan menangkap late corrections sumber pada rerun berikutnya, tetapi belum memiliki optimasi incremental watermark parsial. Snapshot piutang dan stok hanya ditulis untuk hari run, dan snapshot lampau dipertahankan; koreksi saldo invoice historis memerlukan backfill berizin dengan sumber stok historis yang sah.

## Hak akses

- ETL `picd_reader`: SELECT public only.
- ETL `picd_writer`: SELECT/INSERT/UPDATE/DELETE analytics only.
- Laravel auth MANAGER: SELECT analytics, bukan akses ke salinan identitas.
- PIC E: konsumsi `forecast_input` dan dim_product; forecast hasil E bukan mutasi fakta PIC D.


## Pembaruan implementasi 16 November 2026

Loader sekarang melakukan diff/upsert natural key dalam transaksi atas ekstraksi penuh. Riwayat piutang direkonstruksi sejak invoice pertama sampai tanggal run. Snapshot stok tetap hanya tanggal run. Publikasi menolak watermark yang lebih lama. Prefix lake berlapis raw/validated/transformed di bawah run UUID. Lihat HASIL_PENGERJAAN.md untuk cakupan uji dan dependensi yang tersisa.
