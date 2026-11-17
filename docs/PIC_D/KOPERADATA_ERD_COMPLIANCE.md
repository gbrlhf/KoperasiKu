# Kepatuhan ERD v1.4 — audit PIC D, 17 November 2026

Sumber pemeriksaan adalah migrasi gabungan yang benar-benar diterapkan pada PostgreSQL proyek `koperadata`, bukan hanya pencarian nama class. Snapshot lengkap kolom, tipe, default, nullable, PK/FK/CHECK/UNIQUE dan indeks berada pada `KoperasiKu/docs/PIC_D/erd_actual.json`. Tidak ada baris data pribadi dalam snapshot tersebut.

Status integrasi seluruh ERD adalah **PARTIAL**. Domain analytics tersedia dan alur D diuji; struktur OLTP B/C masih memiliki deviasi di bawah.

## Tabel operasional

| Tabel ERD | Aktual | Kolom | PK/FK/CHECK/UNIQUE | Status keberadaan |
|---|---|---:|---|---|
| users | public.users | 9 | 1/0/0/1 | PASS |
| roles | public.roles | 1 | 1/0/0/0 | PASS |
| user_roles | public.user_roles | 2 | 1/2/0/0 | PASS |
| members | public.members | 9 | 1/0/0/3 | PASS |
| categories | public.categories | 5 | 1/0/0/1 | PASS |
| products | public.products | 14 | 1/2/1/1 | PASS |
| suppliers | public.suppliers | 9 | 1/0/0/1 | PASS |
| product_price_histories | public.product_price_histories | 9 | 1/1/0/0 | PASS |
| orders | public.orders | 9 | 1/1/0/0 | PASS |
| order_items | public.order_items | 8 | 1/1/0/0 | PASS |
| payments | public.payments | 10 | 1/0/0/0 | PASS |
| credit_policies | public.credit_policies | 7 | 1/0/0/0 | PASS |
| credit_policy_logs | Tidak ada | 0 | 0/0/0/0 | BLOCKED — PIC B |
| member_credit_accounts | public.member_credit_accounts | 5 | 1/0/0/0 | PASS |
| credit_holds | public.credit_holds | 7 | 1/0/0/1 | PASS |
| receivables | public.receivables | 9 | 1/1/0/1 | PASS |
| receivable_payment_allocations | public.receivable_payment_allocations | 6 | 1/2/0/0 | PASS |
| inventory_balances | public.inventory_balances | 5 | 1/0/1/0 | PASS |
| stock_reservations | public.stock_reservations | 6 | 1/1/0/0 | PASS |
| stock_movements | public.stock_movements | 8 | 1/0/0/0 | PASS |
| purchase_receipts | public.purchase_receipts | 9 | 1/1/0/1 | PASS |
| receipt_items | public.receipt_items | 7 | 1/2/1/0 | PASS |
| operating_expenses | public.operating_expenses | 9 | 1/0/1/0 | PASS |
| import_batches | public.import_batches | 17 | 1/0/0/0 | PASS |
| import_errors | public.import_errors | 7 | 1/1/0/0 | PASS |
| audit_logs | Tidak ada | 0 | 0/0/0/0 | BLOCKED — PIC B |

PASS pada tabel di atas hanya menyatakan keberadaan, bukan validasi seluruh business rule.

## Deviasi dan tindakan

- `users.id` masih bigint dan `password` memakai kontrak Laravel; ERD mendesain UUID/password_hash. `user_roles.user_id` sengaja mengikuti PK terpasang agar login laporan berfungsi tanpa mengganti identitas pengguna. `members.user_id` masih UUID tanpa FK ke users. Penyatuan identitas wajib diputuskan PIC B sebelum transaksi pengguna nyata.
- `roles` memiliki tiga kode MEMBER/CASHIER/MANAGER; kolom nama role belum ada. Tidak ada peran ADMIN. Hak laporan diuji dari tabel role, bukan input/session mock.
- `credit_policy_logs` dan `audit_logs` belum tersedia. Idempotency key/audit transaksi B dan beberapa FK/check OLTP belum lengkap. Menambah tabel kosong saja tidak membuktikan layanan transaksi telah benar.
- Duplikasi `inventory_balances` C→B diperbaiki dengan guard pada migrasi B. Koreksi setelah migrasi tidak dapat memperbaiki kegagalan CREATE TABLE yang terjadi sebelumnya, sehingga perubahan bootstrap ini diperlukan. Migrasi gabungan diuji pada database PostgreSQL baru. Tabel stok tetap satu.
- Migrasi aditif `2026_11_17_000001_add_pic_d_source_contract` menambah nullable `payments.verified_at` dan FK `orders.import_batch_id`. Tidak melakukan backfill waktu historis.
- Payment model mencatat waktu UTC hanya saat event verifikasi baru/transition; event verified tidak boleh diubah status/waktunya melalui model. Query SQL langsung dan workflow verifikasi resmi tetap tanggung jawab B.
- Timestamp `completed_at` baru pada TransactionService disimpan UTC. Tanggal jatuh tempo tetap dihitung +7 hari kalender Jakarta. Timestamp historis tidak dikonversi dengan asumsi.
- Master kategori dan lead time berasal dari relasi kategori/pemasok C melalui adapter SELECT; tidak disalin ke tabel operasional tambahan.
- `order_items.hpp_snapshot` B masih NOT NULL. Histori dengan HPP tidak diketahui harus menyediakan null/penanda yang sah dari C; jangan menganggap angka nol sebagai bukti HPP lengkap.
- Histori impor penjualan belum mempunyai workflow commit lengkap pada C saat audit. FK batch dan filter ETL sudah diuji dengan fixture COMMITTED/PREVIEW_READY, bukan klaim importer C selesai.

## Schema analytics

| Tabel | Kolom | PK/FK/CHECK/UNIQUE | Indeks |
|---|---:|---|---:|
| dim_date | 5 | 1/0/0/1 | 2 |
| dim_member | 3 | 1/0/0/0 | 1 |
| dim_product | 6 | 1/0/0/0 | 1 |
| etl_runs | 10 | 1/0/1/0 | 2 |
| fact_cash_receipts | 10 | 1/1/1/0 | 2 |
| fact_credit_issuance | 4 | 1/1/1/0 | 2 |
| fact_operating_expenses | 5 | 1/1/1/0 | 2 |
| fact_receivable_aging_daily | 8 | 1/2/1/1 | 3 |
| fact_receivables_daily | 7 | 1/2/3/1 | 3 |
| fact_sales | 15 | 1/3/1/0 | 3 |
| fact_stock_daily | 7 | 1/2/3/1 | 3 |

`fact_credit_issuance` dan `fact_receivable_aging_daily` adalah companion fact untuk memisahkan grain piutang baru dan aging per invoice. Natural key dan unique snapshot mencegah double count. `fact_forecast`/`model_runs` tetap milik PIC E; implementasi model/load E tidak diklaim tersedia.

Rollback dua migrasi aditif baru dan reapply diuji hanya pada database `picd_test_phpunit_20261117`. Database proyek tidak di-reset. Backup pra-migrasi disimpan di `KOPERADATA_BACKUP_BEFORE_FINAL/database-before-final-20261117.dump` dan tidak dimasukkan ke ZIP.
