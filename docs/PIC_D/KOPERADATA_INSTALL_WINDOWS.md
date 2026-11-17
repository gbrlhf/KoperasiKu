# Instalasi Windows dan handoff PIC D

Folder kerja utama `D:\BDT2\KoperasiKu`. Jangan menyalin `.env` pengujian ke lingkungan bisnis. Backup ada di `D:\BDT2\KOPERADATA_BACKUP_BEFORE_FINAL`.

## 1. Runtime

Gunakan PHP8.3 pada Laragon (minimum sesuai composer PHP8.2), Composer2, Node yang sesuai package.json/lock, PostgreSQL16, Python3.11, Java17 dan Docker Desktop dengan WSL2. Aktifkan extension PHP pdo_pgsql, mbstring, openssl, fileinfo, zip, xml, ctype, tokenizer, curl dan iconv. Verifikasi CLI memakai php.ini yang benar dengan `php --ini` dan `php -m`.

Lingkungan yang benar-benar diuji menggunakan XAMPP PHP8.2.12, bukan Laragon. Jika pdo_pgsql belum aktif di XAMPP, perintah satu proses dapat menggunakan `C:\xampp\php\php.exe -d extension=php_pdo_pgsql.dll ...`. Untuk artisan serve/test yang meluncurkan child process, aktifkan extension pada php.ini CLI atau panggil PHPUnit langsung.

```powershell
Set-Location D:\BDT2\KoperasiKu
composer install
npm ci
npm run build
if (-not (Test-Path -LiteralPath .env)) { Copy-Item .env.example .env }
# Isi .env secara lokal, jangan kirim password di chat atau Git.
# DB_CONNECTION=pgsql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD.
# php artisan key:generate hanya jika APP_KEY belum ada. Jangan ganti key aplikasi terpasang.
php artisan migrate
php artisan picd:manager manajer@domain-koperasi.example
php artisan serve --host=127.0.0.1 --port=8000
```

Email di atas contoh, ganti dengan akun yang benar. Command menanyakan nama dan kata sandi tersembunyi untuk akun baru, minimal12karakter, tanpa default password. Akun yang sudah ada tidak diubah kata sandi/statusnya. Login UI memakai email+password; akses HPP/laba hanya MANAGER. Tidak ada auto-seeder akun demo di database proyek.

## 2. Infrastruktur data

Jika PostgreSQL sudah ada pada5432, jangan membuat service baru pada port sama. Pada mesin saat pengujian, `koperadata-picd-postgres` sudah tersedia, MinIO uji tersedia pada19000/19001. Database test memakai prefix picd_test_; layanan/akun uji tidak otomatis dijadikan kredensial bisnis.

Untuk instalasi baru, `infra/docker-compose.picd.yml` menyediakan PostgreSQL dan MinIO. Isi PICD_POSTGRES_PASSWORD/PICD_MINIO_ROOT_USER/PICD_MINIO_ROOT_PASSWORD pada environment lokal, lalu jalankan `docker compose -f infra/docker-compose.picd.yml up -d --build`. Image MinIO dibangun dari revision resmi yang dipin pada `infra/minio/Dockerfile`; jangan mengganti pin tanpa review.

Setelah migrasi, DBA menjalankan `data-platform/analytics/provision_roles.sql` pada database aplikasi. Buat dua LOGIN terpisah dengan secret sendiri dan grant group `picd_source_reader` serta `picd_warehouse_writer`. Reader SELECT public tertentu; writer DML analytics. Reapply grants setelah penambahan tabel. Jangan memakai akun superuser untuk ETL rutin.

## 3. Konfigurasi ETL

```powershell
py -3.11 -m venv data-platform\.venv
.\data-platform\.venv\Scripts\python.exe -m pip install -r data-platform\requirements.txt
if (-not (Test-Path -LiteralPath data-platform\.env)) {
    Copy-Item data-platform\.env.example data-platform\.env
}
```

Isi `.env` data-platform dengan DSN reader/writer, secret pseudonim acak32+karakter yang persisten, endpoint/akun/bucket MinIO. Jangan rotasi secret HMAC tanpa strategi rekey karena member_key berubah. `PICD_STOCK_CUTOFF` dibiarkan kosong sampai keputusan tanggal sah. Tidak ada transaksi sintetis yang perlu dimasukkan ke database aplikasi.

Native Spark Windows memerlukan Java17 dan instalasi Hadoop/Winutils yang sesuai. Opsi yang telah diuji adalah image Docker Linux:

```powershell
docker build -f infra/Dockerfile.picd -t koperadata-picd:local .
# Gunakan --env-file yang hanya berisi konfigurasi data-platform lokal.
# Dalam DSN/endpoint container, gunakan host.docker.internal untuk service di host.
docker run --rm --env-file data-platform/.env -v "${PWD}:/project" -w /project/data-platform --entrypoint python koperadata-picd:local -m etl.preflight
docker run --rm --env-file data-platform/.env -v "${PWD}:/project" -w /project/data-platform --entrypoint python koperadata-picd:local -m etl.run_pipeline
```

Snapshot stok saat ini berarti as-of hanya hari berjalan Jakarta. ETL membaca OLTP, menulis analytics dan lake. Jika VERIFIED lama tidak mempunyai verified_at sah, perbaiki sumber dengan bukti bisnis; jangan memakai updated_at. Source retur belum didukung sebelum kontrak resmi tersedia.

Sesudah run sukses, buka `/manager/dashboard`, `/manager/piutang`, `/manager/laporan`. API `/api/v1/analytics/summary`, `/top-products`, `/categories`, `/trend`, `/reports/financial`, `/reports/financial/export`, `/manager/receivables/aging` menggunakan session terautentikasi. Prefix empat endpoint analytics terakhir tetap `/api/v1/analytics/...`; lihat routes/api.php untuk path lengkap.

Untuk rekonsiliasi, dari data-platform jalankan `python -m etl.reconcile --start YYYY-MM-DD --end YYYY-MM-DD`. Feed E `python -m etl.forecast_feed --start YYYY-MM-DD --end YYYY-MM-DD --output forecast_input.csv`; rentang harus tercakup run sukses. Stok sebelum observasi tetap unknown.

## 4. Pengujian ulang

`php artisan test` menjalankan tes cepat SQLite memory. Tes skema PostgreSQL memakai `phpunit.pgsql.xml` dan wajib DB_DATABASE berawalan `picd_test_`. Isi DB_HOST/PORT/USERNAME/PASSWORD melalui environment, DB_CONNECTION=pgsql, DB_URL kosong; lalu jalankan `php vendor/bin/phpunit -c phpunit.pgsql.xml --testdox`.

Untuk E2E, gunakan database test kedua yang sudah dimigrasikan, grant source/warehouse group ke role test. Isi PICD_TEST_DSN dan konfigurasi MinIO test, pseudonym secret test. Jalankan `python data-platform/tests/integrated_bc.py` dalam container `/project`. Skrip ini TRUNCATE hanya setelah memverifikasi nama database test. Sesudah sukses, jalankan PHPUnit `tests/Published` dengan DB_DATABASE menunjuk database E2E yang sama. Jangan menjalankan RefreshDatabase suite di antara E2E dan Published.

`integration_pipeline.py` menggunakan database test tersendiri karena sengaja mengganti fakta dan mencatat run gagal. Unit Python berada di data-platform/tests; benchmark pada data-platform/benchmarks/compare_sql_spark.py membutuhkan PICD_BENCHMARK_DSN.

Log hasil sesi aktual ada di D:\BDT2\KOPERADATA_TEST_LOGS. Source/final ZIP tidak membawa credentials, vendor, node_modules, database backup atau data upload pribadi.
