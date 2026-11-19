# PIC B — Refinement handover (19 November 2026)

Basis: ZIP terbaru `KoperasiKu-feat-B-core-credit(2).zip`, branch lokal `feat/B-core-credit-refinement`. Root Laravel dipertahankan sebagai aplikasi PIC B. File PRD, ERD, migration lama, aplikasi `apps/web`, dan data-platform tidak diganti. Tidak ada push atau akses ke PostgreSQL. Baseline yang dilaporkan sebelum perubahan: **31 tests, 346 assertions**. Baseline tersebut tidak berlaku sebagai bukti kelulusan kode baru.

## Perubahan yang disiapkan

| Paket | Implementasi |
|---|---|
| B-FIX-00 | Bootstrap PHPUnit root membaca pengaturan koneksi dari environment proses atau `.env.testing`, memaksa `koperadata_test` dan host loopback. Worker concurrency mewarisi koneksi yang efektif. `.env.testing` di-ignore; template tersedia. |
| B-FIX-01 | Validasi status/hold/reservasi sebelum pickup atau cancel. Pembayaran verified atau transfer pending menghalangi cancel sampai ada penyelesaian yang sah. Saldo on_hand dan reserved pada pickup berubah dalam satu UPDATE. |
| B-FIX-02 | Pickup wajib kode yang cocok dan konfirmasi fisik. UI kasir mengambil katalog, anggota dan antrean dari database; memanggil API nyata untuk POS, pembayaran order, approval/rejection, pickup dan cancellation; tidak menampilkan sukses saat server gagal. |
| B-FIX-03 | Perhitungan keputusan finansial di TransactionService dalam integer satuan 0,01. Alokasi harus tepat, positif dan tidak duplikat; bila tidak dikirim, gunakan jatuh tempo terawal. POS transfer wajib pernyataan verified dari petugas; UI POS mengirim total yang disetujui untuk menolak harga yang berubah. |
| B-FIX-04 | Mutasi reserve, fulfill, release, POS, receipt dan adjustment melalui InventoryService root; histori reservasi dipertahankan; agregasi identifier produk sebelum lock. Audit receipt/adjustment. Kontrak receive/getOnHand/getAvailable lama dipertahankan untuk PIC C. |
| B-FIX-05 | Migration forward-only `2026_11_19_000001_strengthen_pic_b_invariants.php`: lifecycle reservasi, indeks, constraint validitas, unique allocation pair dan maksimal satu policy aktif di PostgreSQL. Data lama yang duplikat menahan migrasi, bukan dihapus. |
| B-FIX-06 | Advisory transaction lock PostgreSQL untuk key idempotency, retry transaksi terbatas, penanganan duplikasi external_reference. Urutan penguncian stok/akun diperketat. Concurrency identik diuji dengan stok pas. |
| B-FIX-07 | `contracts/api/pic-b-v1.openapi.json` draft kontrak REST root, termasuk sesi, CSRF, role, payload perubahan baru. RoleSeeder idempotent hanya tiga role. |
| B-FIX-08 | Tambahan regresi untuk invariant CHECK, missing hold/reservation, paid cancel, invalid code/handover, alias stok, POS transfer/harga, dan presisi nominal. Test lama yang memanggil pickup disesuaikan dengan kontrak baru. **Belum dijalankan.** |

## Keputusan kompatibilitas dan batasan

- Autentikasi sesi yang sudah digunakan branch ini dipertahankan; tidak menambah Sanctum atau mengganti arsitektur login.
- POS transfer memakai konfirmasi eksplisit oleh petugas, bukan integrasi bank otomatis. Kasir tidak boleh menyatakan dana diterima sebelum memeriksa mutasi.
- Perubahan skema hanya melalui migration baru. Historisasi tidak dapat memulihkan baris reservasi yang pernah dihapus. Policy aktif maksimal satu, tidak otomatis membuat policy baru atau mengarang nominal limit.
- Pembatalan pesanan yang sudah dibayar ditolak dengan REFUND_REQUIRED. Implementasi refund/reversal sesungguhnya memerlukan kontrak akuntansi, skema, dan koordinasi tim; belum dibuat. Jangan menandai refund selesai.
- Snapshot HPP saat fulfillment tetap dipertahankan; rumus WAC tidak diubah. Integrasi receipt/HPP dari aplikasi terpisah `apps/web` dengan root harus disepakati bersama PIC C. Receipt retry lintas sistem masih perlu kontrak identitas line yang unik.
- Legacy endpoint tanpa key idempotency tetap diizinkan untuk menjaga kompatibilitas. Klien mutasi wajib menggunakan key stabil pada retry; kebijakan mewajibkan key pada seluruh endpoint perlu koordinasi. Retry setelah reload browser belum dipersistenkan.
- Portal piutang kasir, manager, dan sebagian katalog/checkout storefront masih memiliki fixture/pekerjaan UI PIC A; implementasi UI menyeluruh, master data/impor, ETL/ML di luar patch ini. Tidak mengklaim seluruh sistem siap produksi. Root tidak memiliki workflow CI tersendiri; workflow PIC lain dipertahankan.
- OpenAPI adalah draft dan belum divalidasi terhadap response runtime. Konfigurasi test sengaja hanya mengizinkan host loopback dengan nama `koperadata_test`.

## Penerapan di laptop (pilih patch ATAU ZIP)

Paling disarankan: patch pada folder repo yang sekarang, branch `feat/B-core-credit-refinement`. Patch dibuat terhadap isi ZIP terbaru, termasuk empat file konfigurasi lokal yang telah diubah sebelumnya. Jangan terapkan lagi patch lama PIC-B-01. Simpan file patch di Downloads.

```powershell
git branch --show-current
git status --short
git apply --check "$HOME\Downloads\PIC-B-refinement.patch"
# Hanya jika pemeriksaan berhasil:
git apply "$HOME\Downloads\PIC-B-refinement.patch"
git diff --check
git status --short
```

Jika check gagal, berhenti, jangan force atau restore keseluruhan repository. ZIP sumber adalah alternatif untuk review/backup dan tidak berisi `.git`, `.env`, `.env.testing`, `vendor`, `node_modules`, generated build, atau data/log aplikasi. Jangan menyalin ZIP menimpa repo tanpa pemeriksaan perbedaan. File konfigurasi rahasia tetap di folder lama.

## Testing nanti di kampus

1. Jalankan PostgreSQL di Laragon, pastikan `koperadata_test` dibuat dan disposable. `.env` tetap menggunakan `koperadata`. `.env.testing` menggunakan `APP_ENV=testing`, `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_PORT=5432` (sesuaikan jika diperlukan), `DB_DATABASE=koperadata_test`, username/password lokal, `DB_URL=`. Pertahankan APP_KEY dan pengaturan testing lain. Jangan commit kedua file environment tersebut. Bootstrap membolehkan override koneksi melalui env proses; periksa jika ada DB_* tersisa di shell. Pastikan shell dan aplikasi menunjuk host/port DATABASE TEST yang sama sebelum migrasi.
2. Dari folder kerja sekarang, jalankan perintah berikut secara bertahap. Jangan jalankan migration/seeder ke database utama, dan jangan gunakan migrate:fresh untuk mengatasi kegagalan.

```powershell
php artisan config:clear
php artisan tinker --env=testing --execute="dump(config('database.default'), DB::selectOne('SELECT current_database() AS db'));"
php vendor/bin/phpunit --configuration=phpunit.pgsql.xml --filter=DatabaseIsolationTest
```

Lanjut hanya setelah `pgsql`, `koperadata_test` dan tes isolasi lulus. Periksa database lewat tes konfigurasi worker juga; MigrationSafetyPgsqlTest menggunakan operasi rollback/drop pada database disposable.

```powershell
php artisan migrate --env=testing
php artisan migrate:status --env=testing
npm run build
php vendor/bin/phpunit --configuration=phpunit.pgsql.xml --filter=PhaseBCoreCompliancePgsqlTest
php vendor/bin/phpunit --configuration=phpunit.pgsql.xml --filter=ConcurrencyPgsqlTest
php vendor/bin/phpunit --configuration=phpunit.pgsql.xml --filter=MigrationSafetyPgsqlTest
php vendor/bin/phpunit --configuration=phpunit.pgsql.xml
```

Jika migration baru melaporkan policy aktif lebih dari satu atau duplikasi alokasi, jangan menghapus data otomatis. Rekonsiliasi fixture/database testing sesuai dokumen. Uji juga migrasi dari schema kosong pada database disposable di bawah pengaman yang sama, lalu cek manual UI dengan akun fixture. Ulangi concurrency beberapa kali. Tidak ada klaim jumlah assertion final sampai hasilnya tersedia. Jangan mengurangi assertion atau menghapus test untuk membuat indikator hijau.

Sebelum push: pastikan semua file dan diff sesuai, tidak ada rahasia, dan lakukan commit pada branch pribadi saja. Jangan merge ke main sampai ada hasil pengujian dan review tim.
