# Panduan review Git PIC D

Folder `D:\BDT2\KoperasiKu` pada saat audit tidak memiliki .git. Tidak ada branch/commit dasar yang dapat diklaim untuk folder ini. Pembanding `D:\BDT2\KoperasiKu-B-Core` berada pada branch `feat/D-bigdata-financial-analytics` dan sudah memiliki perubahan lokal sebelum finalisasi. Repository pembanding tidak ditimpa.

Jangan melakukan reset/clean/force push. Tinjau daftar perubahan `docs/PIC_D/changed_files.json` terhadap backup `D:\BDT2\KOPERADATA_BACKUP_BEFORE_FINAL`.

Jika tim memakai repo yang sudah ada, lakukan pada checkout terpisah dengan working tree aman:

```powershell
git status --short
git switch -c codex/pic-d-final-integration
# Salin/merge per file berdasarkan changed_files.json, jangan overwrite seluruh folder.
git diff --check
git diff -- app database routes data-platform tests docs
# Jalankan suite sesuai KOPERADATA_TEST_RESULTS.md pada database test.
# Stage file hasil review secara eksplisit, tidak termasuk .env/data pribadi.
git diff --cached --stat
git diff --cached
# Commit dan ajukan PR hanya setelah review tim.
```

Ownership perubahan:
- D: adapter source, forecast coverage, schema/kontrak, laporan/API, tes integrasi dan bukti benchmark.
- B: Payment verified_at, completed_at UTC, guard migrasi shared inventory, FK import provenance.
- B/A dependensi akses laporan: roles/user_roles, SessionController, login/logout/switch role, command provision manager.
- C: tabel dan layanan tetap dipertahankan; perbedaan kategori/supplier ditangani SELECT adapter. Migrasi C tidak diubah.

Khusus migrasi B lama, guard inventory perlu direview B karena diperlukan untuk instalasi baru sesudah migrasi C; perubahan tidak mengubah tabel yang sudah ada. Tabel baru roles mengikuti bigint users terpasang; konversi identity ke UUID harus melalui migrasi tim yang terencana, bukan penggantian PK sembarangan.

Template PR ringkas:

> PIC D kini membaca skema B/C gabungan, memisahkan kas verified dari omzet completed, dan menyediakan laporan manager dari mart yang tervalidasi. Perbaikan dependency mencakup verified_at UTC, provenance impor, konflik inventory dan login/peran nyata. Validasi: 15 PostgreSQL tests, 2 published-report tests, Python/Spark/E2E dan benchmark10k/100k/1M. Retur/nota kredit, data riil, cut-off dan penyatuan identitas B masih belum final; rincian di audit.

Tidak ada push atau merge otomatis yang dilakukan dalam pekerjaan ini.
