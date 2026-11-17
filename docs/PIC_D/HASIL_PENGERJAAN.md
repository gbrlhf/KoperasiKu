# Finalisasi PIC D — 17 November 2026

Pekerjaan diterapkan pada `D:\BDT2\KoperasiKu`. Lingkup permintaan adalah PIC D, dengan perbaikan minimum dependensi B/C dan login untuk membuka laporan secara sah. Ini bukan pernyataan bahwa seluruh aplikasi lima PIC siap produksi.

**Hasil utama**: PostgreSQL dari migrasi gabungan B/C → snapshot read-only → PySpark → Parquet raw/validated/transformed → MinIO → analytics → API dan Blade berhasil diuji. Data uji sintetis dipisahkan dalam database berawalan `picd_test_`. Database proyek berhasil dimigrasikan tanpa reset; pada pemeriksaan setelah migrasi, orders/products/members/payments masing-masing masih kosong.

Backup sumber ada pada `KOPERADATA_BACKUP_BEFORE_FINAL`. Backup database pra-migrasi juga ada di sana dan tidak masuk ZIP. Source utama belum merupakan repository Git. Tidak ada commit, push, atau perubahan pada repository pembanding B dalam sesi finalisasi ini.

## Perbaikan integrasi

1. Guard tabel stok bersama mengatasi benturan migrasi C/B pada instalasi baru.
2. Migrasi aditif menyediakan waktu verifikasi pembayaran dan provenance batch impor. Waktu historis tidak ditebak dari updated_at.
3. Payment mencatat UTC saat VERIFIED baru; waktu verifikasi yang sudah tercatat tidak boleh ditulis ulang melalui model. TransactionService menulis completed_at baru dalam UTC.
4. Adapter SELECT membaca kategori dan lead time dari relasi master C. Source reader diberikan SELECT untuk relasi tersebut.
5. Login nyata, tabel peran, pergantian konteks peran terotorisasi, logout, dan command provisioning manager tersedia. Akun/kata sandi produksi tidak dibuat otomatis. Hak laporan tidak berasal dari request role atau mock session.
6. Dashboard dan laporan memakai snapshot baca konsisten. Ekspor CSV/XLSX/PDF dan empat frekuensi periode diuji melalui HTTP Laravel.
7. Forecast feed menolak rentang di luar cakupan ETL sukses agar sejarah yang tidak tersedia tidak diubah menjadi nol penjualan. Export feed tidak lagi meminta kredensial MinIO.
8. Test tambahan memakai tabel UUID/numeric/FK/check dari migrasi nyata, bukan hanya tabel stub berisi text.

Daftar file perubahan terhadap backup ada di `KoperasiKu/docs/PIC_D/changed_files.json`. Penyesuaian style dibatasi file D, pengujian, dan file dependensi yang disentuh.

## Matriks tugas PIC D

Status PASS di bawah berlaku pada bukti uji terpisah yang disebutkan. Deployment dengan data bisnis nyata tetap membutuhkan konfigurasi dan kontrak yang belum tersedia.

| Requirement ID | PIC Pemilik | Referensi PRD/ERD | File Implementasi | Status | Masalah | Tindakan | Hasil Pengujian |
|---|---|---|---|---|---|---|---|
| D-01 | D | Grain warehouse | data-platform/analytics/data_dictionary.md, schema.sql | PASS | Grain harus konsisten | PK natural source, snapshot per tanggal | DDL nyata, rerun nol perubahan |
| D-02 | D/B/C | KPI resmi | analytics/kpi_definitions.md, etl/transform.py | PARTIAL | Retur/nota kredit belum disahkan | Kontrak usulan dan quality flag eksplisit | Kredit200k/cicilan75k cocok; retur belum diuji |
| D-03 | D/E | Read-only, privacy, lake | etl/source.py, analytics/provision_roles.sql | PASS | Akun deployment belum diisi | Dua role terpisah, HMAC sebelum file | UPDATE OLTP ditolak, pseudonim dan MinIO teruji |
| D-04 | D | analytics, etl_runs | analytics/schema.sql, warehouse_load.py | PASS | — | Metadata/watermark/status atomik | Migrasi, rollback, failure preservation |
| D-05 | D | COMMITTED import | source.py, transform.py | PASS | Importer C histori belum lengkap | Adapter batch teruji mandiri | PREVIEW dan checkout tidak masuk omzet |
| D-06 | D | Waktu, status, nilai | transform.py, quality_checks.py | PASS | Historis waktu/HPP tak boleh ditebak | Validasi sebelum publish | UTC/Jakarta, nilai invalid, due boundary |
| D-07 | D | Lake tiga lapisan | run_pipeline.py | PASS | — | Prefix run UUID dan partisi tanggal | 80 objek MinIO, tiga lapisan Parquet |
| D-08 | D | dim_date/product/member | transform.py | PASS | Relasi master C berbeda | Adapter kategori/pemasok | Dimensi nyata, HMAC, unknown member sentinel |
| D-09 | D | Sales/cash/expense facts | transform.py, schema.sql | PARTIAL | Belum ada sumber reversal resmi | Fakta dasar tersedia; jangan klaim neto final | 200k revenue, 140k COGS,75k kas,10k biaya |
| D-10 | D | Snapshot saldo/stok | transform.py | PASS | Stok historis sebelum observasi tidak tersedia | Hanya snapshot stok aktual; AR dari event | Saldo125k, available96, histori9hari |
| D-11 | D/A | Dashboard/manajer | AnalyticsQueryService.php, views/manager | PASS | — | Backend SQL NUMERIC | API/Blade, kategori/SKU/aging lulus |
| D-12 | D/A | Report periods/export | ReportPeriod.php, ReportExporter.php | PASS | — | Harian/pekanan/bulanan/tahunan | Agregasi4periode, CSV/XLSX/PDF, invalid-date422 |
| D-13 | D/B | API RBAC/freshness | routes/api.php, RequireManager.php | PASS | Auth B sebelumnya mock | Login + role DB nyata | Guest401, cashier403, manager200, stale saat gagal |
| D-14 | D/A | Quality/cut-off | transform.py, AnalyticsQueryService.php | PASS | Tanggal cut-off belum resmi | Konfigurasi nullable + quality flag | Cut-off sintetis, incomplete/stale teruji |
| D-15 | D | Idempotent/late changes | warehouse_load.py | PARTIAL | Reversal ledger B/C belum ada | Atomic diff/upsert dengan watermark | Rerun0, rollback SQL, late HPP update; reversal belum |
| D-16 | D/E | Forecast input | etl/forecast_feed.py | PASS | Stockout historis bisa unknown | Dense SKU/day, nullable flag | 9hari,8nol,2unit,lead3, coverage guard |
| D-17 | D/E | Forecast handoff | contracts/data-schemas/forecast_*.json | PARTIAL | Implementasi/validasi model E belum tersedia | Kontrak input/output disediakan | Feed diuji, model/loader E belum |
| D-18 | D | Rekonsiliasi | etl/reconcile.py | PASS | Data riil belum ada | SQL independen enam metrik | Seluruh source=mart pada skema nyata |
| D-19 | D/E | Benchmark | benchmarks/compare_sql_spark.py | PASS | RSS server PG tidak diukur | 3ulangan/ukuran, scope memory dijelaskan | 10k/100k/1M exact aggregate match |

## Acceptance PRD lintas PIC

| AC | Status | Bukti / batas |
|---|---|---|
| 01 | PARTIAL | Login nyata dan akses laporan tested; seluruh akses data anggota/transaksi B belum diaudit runtime |
| 02–07 | BLOCKED B | Reservasi, pickup, global limit, concurrency dan kredit overdue bukan modul D; belum diuji end-to-end bisnis |
| 08 | PASS D | Due tepat +7 tanggal lokal, hari jatuh tempo belum overdue, hari berikutnya overdue |
| 09 | PASS D | Penjualan200k, kas75k, saldo125k tanpa omzet tambahan |
| 10 | PARTIAL | Pending transfer diabaikan ETL; endpoint verifikasi dan alokasi atomik B belum lengkap |
| 11–15 | BLOCKED B/A/C | Idempotensi transaksi, cancel/refund, policy audit, kamera barcode dan seluruh audit harga belum dibuktikan |
| 16 | PARTIAL | ETL committed/cut-off/rerun diuji; workflow importer histori C belum lengkap |
| 17 | PASS D | Empat frekuensi, snapshot akhir tidak dijumlah lintas hari, cocok sumber |
| 18 | PASS D | HPP null tidak ditebak dan biaya belum lengkap diberi label estimasi |
| 19 | PASS D | Rerun changed_rows=0, tidak ada duplikasi natural key |
| 20 | PARTIAL | Benchmark lulus; model/backtest E belum tersedia |
| 21 | PARTIAL | Mart sukses tetap terbaca + stale saat gagal; transaksi kasir selama ETL offline belum diuji |

## Hal yang masih diperlukan

- Data operasional nyata B/C dan akun manager yang akan digunakan. Schema proyek sudah siap untuk kontrak sumber D, tetapi tabel bisnis masih kosong. Login manager dapat disiapkan melalui `php artisan picd:manager EMAIL` dengan kata sandi yang dimasukkan tersembunyi.
- Kredensial source read-only, warehouse writer, dan endpoint/akun MinIO untuk lingkungan yang dipilih. `data-platform/.env` belum diisi otomatis memakai kredensial pengujian.
- Keputusan tanggal cut-off stok. Isi `PICD_STOCK_CUTOFF=YYYY-MM-DD` setelah disahkan; fixture cutoff bukan keputusan operasional.
- Kontrak retur/nota kredit, tanggal posting dan hubungan refund dengan koreksi piutang. Laporan memberi indikator `source_adjustments_supported=false`; nominal belum boleh dinyatakan penjualan neto final jika ada retur yang belum masuk sumber.
- B perlu menyelesaikan verifikasi transfer + alokasi atomik, penyatuan UUID pengguna/anggota, idempotency/audit transaksi, dan kontrak waktu historis. C perlu menyelesaikan importer histori dan kelengkapan HPP. E tetap pemilik model forecast/deployment tim.

File ZIP bernama sesuai permintaan master prompt, tetapi hasil ini **bukan 100% PASS untuk lima PIC**. Dokumen lampiran dipakai sebagai acuan persyaratan; scope tindakan mengikuti permintaan pengguna PIC D.
