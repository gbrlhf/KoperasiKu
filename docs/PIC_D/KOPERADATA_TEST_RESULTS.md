# Hasil pengujian PIC D — 17 November 2026

Pengujian memakai PHP 8.2.12 lokal XAMPP, Laravel12/PHPUnit11.5.58, PostgreSQL16.16 dalam Docker, Python3.11.18, PySpark3.5.4 dan Java17 dalam image lokal. Laragon/PHP8.3 belum tersedia pada lingkungan ini sehingga tidak diklaim diuji.

Log ada pada `D:\BDT2\KOPERADATA_TEST_LOGS`. Semua fixture adalah TEST ONLY. Tidak ada migrasi fresh atau truncate pada database proyek.

| Pemeriksaan | Perintah / alat | Hasil | Bukti |
|---|---|---|---|
| Composer | composer validate --no-check-publish | PASS | composer-validate.log |
| Routes | php artisan route:list --json | PASS | routes.json |
| Laravel cepat | php artisan test --no-ansi | PASS 12tes/32assertions | phpunit-sqlite.log; subset auth HTTP pada SQLite memory |
| PostgreSQL nyata | php -d extension=php_pdo_pgsql.dll vendor/bin/phpunit -c phpunit.pgsql.xml --testdox | PASS 15tes/47assertions | phpunit-postgres.log |
| API + laporan atas mart terisi | PHPUnit tests/Published setelah integrated_bc.py | PASS 2tes/38assertions | published-reports.log |
| Database proyek | php -d extension=php_pdo_pgsql.dll artisan migrate --force | PASS | project-migrate.log; backup dibuat sebelumnya |
| Rollback/reapply tambahan | artisan migrate:rollback --step=2 lalu migrate, hanya picd_test_phpunit_20261117 | PASS | test-migration-rollback.log |
| Python unit/reference | python -m unittest discover -s tests -p test_*.py -v | PASS 15tes | python-unit.log |
| Spark + PG | python data-platform/tests/integration_pipeline.py | PASS | spark-integration.log |
| E2E skema nyata | python data-platform/tests/integrated_bc.py | PASS | integrated-bc.log + integrated_bc_results.json |
| Build | npm run build | PASS | vite-build.log; warning deprecation Sass tanpa kegagalan |
| Pint file D/dependensi disentuh | vendor/bin/pint [file PIC D] --test --format=json | PASS final scope | pint-picd.json |
| Pint seluruh repo awal | vendor/bin/pint --test --format=json | FAIL style baseline di B/C | pint-initial.json; tidak diubah massal hanya demi style |
| Benchmark | compare_sql_spark.py --sizes 10000,100000,1000000 --repeats 3 | PASS exact match | benchmark.log + benchmark_results.json |

## Bukti finansial

Fixture kredit selesai dua unit x Rp100.000, HPP Rp70.000/unit; cicilan VERIFIED Rp75.000, transfer pending lain diabaikan; biaya Rp10.000. Hasil omzet Rp200.000, HPP Rp140.000, kas Rp75.000, piutang baru Rp200.000, saldo akhir/overdue Rp125.000, laba estimasi Rp50.000. Enam metrik source=mart, semua melalui skema hasil migrasi B/C. Snapshot stok98 reserved2 available96 tidak berubah karena ETL.

Impor PREVIEW_READY dan checkout tidak masuk omzet. COMMITTED sebelum cutoff sintetis ditandai historis. Dua run sukses menghasilkan changed_rows=0 pada run kedua, tiga lapisan Parquet dengan80objek. Pseudonim tidak menyimpan ID/nama asli. UPDATE dengan role sumber ditolak PostgreSQL. Feed sembilan hari menghasilkan delapan hari nol dan total dua unit dengan lead time3.

Integration Spark terpisah membuktikan constraint failure merollback perubahan dimensi/fakta, data sukses tetap ada setelah gagal, koreksi HPP terlambat mengubah natural key yang sama. Ini adalah koreksi source snapshot, bukan uji retur/nota kredit yang belum punya kontrak resmi.

HTTP menguji login password, inactive account, penolakan role spoofing, cashier403, guest401, manager200, laporan4periode, tanggal invalid422, CSV/XLSX/PDF, tiga halaman Blade, dan stale sesudah failure. Pengujian Blade/HTTP bukan pengujian visual browser atau kamera barcode.

## Benchmark

Median detik, tiga ulangan per ukuran, Spark local[2]. Generation termasuk dalam workload. Benchmark lokal ini tidak membuktikan Spark selalu lebih cepat.

| Input rows | SQL median s | Spark median s | Exact match |
|---:|---:|---:|---|
| 10,000 | 0.0178 | 0.3458 | PASS |
| 100,000 | 0.0400 | 0.2408 | PASS |
| 1,000,000 | 0.2443 | 0.3157 | PASS |

Peak RSS yang tersedia adalah process tree Python/Spark client. Peak RSS server PostgreSQL belum diukur terpisah. Angka lengkap, versi, CPU dan tiap ulangan ada pada JSON.

## Temuan selama uji yang diperbaiki

- CLI artisan test menjalankan child PHP yang tidak mewarisi flag `-d`; pengujian PG akhirnya dijalankan langsung melalui vendor/bin/phpunit dengan pdo_pgsql aktif.
- Salah nama variabel cutoff pada test integrasi pertama diperbaiki menjadi PICD_STOCK_CUTOFF dan seluruh E2E dijalankan ulang sukses.
- Pint memerlukan lint ulang pada controller halaman setelah formatting; scope final diuji kembali.

Tidak ada klaim concurrency kredit B, importer histori C lengkap, retur/refund, forecasting/backtest E atau deployment produksi telah lulus. Status tersebut ada di audit.
