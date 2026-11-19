# KoperasiKu (KOPERADATA)

Aplikasi sistem koperasi konsumsi karyawan berbasis web (**Laravel 12** + **PostgreSQL**) dengan pemenuhan pesanan model **pickup only** di gerai koperasi, pengelolaan POS kasir, piutang anggota, dan integrasi pipeline analitik data.

---

## Ringkasan Sistem & Peran (RBAC)

Sistem menggunakan **3 peran utama** dengan pemisahan hak akses pada rute web dan API:

| Peran | Portal Utama | Kapabilitas Utama |
|---|---|---|
| **Anggota (`MEMBER`)** | Etalase Publik (`/`) & Laci Profil | Menjelajahi katalog produk, checkout keranjang (tunai, transfer, atau piutang anggota), memantau kode pickup, serta melihat sisa limit dan tagihan berjalan. |
| **Kasir (`CASHIER`)** | `/cashier/pos`, `/cashier/antrian`, `/cashier/piutang` | Melayani transaksi POS langsung (pemindai barcode kamera / input manual SKU), verifikasi pickup pesanan anggota, verifikasi pembayaran transfer, dan mencatat cicilan/pelunasan piutang. |
| **Manajer (`MANAGER`)** | `/manager/dashboard` | Memantau ringkasan operasional & keuangan, mengelola portofolio & aging piutang, mengatur limit kredit global, mengelola master produk/anggota/pemasok, serta mengakses laporan & audit log. |

### Aturan Bisnis Inti
- **Stok & Reservasi:** Checkout anggota mereservasi stok (`available = on_hand - reserved`). Pengurangan stok fisik dan pencatatan penjualan dilakukan saat petugas/kasir mengonfirmasi serah terima barang (pickup atau transaksi POS).
- **Piutang Anggota:** Kredit konsumsi berlaku tanpa bunga (`0%`) dengan tenor **7 hari kalender** sejak penyerahan barang, dibatasi oleh limit kredit global yang dikelola oleh Manajer.

---

## Teknologi yang Digunakan

- **Backend:** PHP `^8.2`, Laravel Framework `^12.0`, `predis/predis`
- **Frontend:** Blade Templates, Bootstrap `5.3`, Bootstrap Icons, Chart.js, `html5-qrcode`, `qrcode`, `jsbarcode`, Vite `7`
- **Database:** PostgreSQL `16` (`postgres:16-alpine`)
- **Data Platform & ML:** Pipeline ETL & data mart (`data-platform/`) serta layanan Python (`services/ml/`)
- **Pengujian & Kualitas Kode:** PHPUnit `^11.5`, Laravel Pint, Larastan

---

## Struktur Direktori

```text
├── app/                   # Controller, Model Eloquent, Middleware RBAC, dan Service transaksi
├── bootstrap/             # Konfigurasi startup aplikasi, routing, dan exception handler API
├── config/                # Konfigurasi Laravel & identitas koperasi (config/koperasi.php)
├── data-platform/         # Pipeline ETL, definisi skema data mart, rekonsiliasi, dan benchmark
├── database/
│   ├── migrations/        # Skema tabel transaksi, inventori, HPP, idempotency, dan analitik
│   └── seeders/           # Seeder data awal termasuk StorefrontDatabaseSeeder
├── docker/                # Dockerfile PHP, Python, dan init.sql PostgreSQL
├── docs/                  # Dokumen kontrak API, audit backend, dan catatan teknis tim
├── infra/                 # Konfigurasi Docker Compose & MinIO tambahan
├── public/                # Aset publik, CSS/JS terkompilasi, dan entrypoint index.php
├── resources/             # View Blade (etalase, portal kasir, portal manajer) & aset sumber
├── routes/                # Definisi rute web (routes/web.php) dan API v1 (routes/api.php)
├── services/ml/           # Layanan Python untuk kebutuhan analitik/prediksi
├── tests/                 # Pengujian Unit, Integration (concurrency), dan Feature
├── docker-compose.yml     # Orkestrasi layanan PostgreSQL, App, dan ML
├── phpunit.pgsql.xml      # Konfigurasi PHPUnit khusus PostgreSQL terisolasi
└── PRD_KoperasiKu.md      # Product Requirements Document (PRD v1.4)
```

---

## Menjalankan Proyek Secara Lokal

### Prasyarat
- PHP `>= 8.2` beserta ekstensi PostgreSQL (`pdo_pgsql`, `pgsql`)
- Composer
- Node.js & npm
- Docker Desktop (disarankan untuk database PostgreSQL) atau instance PostgreSQL 16 lokal

### 1. Jalankan Database PostgreSQL (via Docker)
File `docker-compose.yml` menyediakan layanan `db` pada port **`5433`**:

```bash
docker compose up -d db
```

Parameter bawaan container `db`:
- `DB_HOST`: `127.0.0.1`
- `DB_PORT`: `5433`
- `DB_DATABASE`: `koperadata`
- `DB_USERNAME`: `postgres`
- `DB_PASSWORD`: `secret`

### 2. Konfigurasi Lingkungan & Dependensi
```bash
composer install
cp .env.example .env
php artisan key:generate
```

Pastikan bagian konfigurasi database di `.env` menyesuaikan instance PostgreSQL yang berjalan (contoh untuk Docker di atas):
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5433
DB_DATABASE=koperadata
DB_USERNAME=postgres
DB_PASSWORD=secret
```

### 3. Migrasi Database & Seed Data Awal
```bash
php artisan migrate
php artisan db:seed --class=StorefrontDatabaseSeeder
```

### 4. Build Aset Frontend & Jalankan Server
```bash
npm install
npm run build
php artisan serve
```

Aplikasi dapat diakses di `http://127.0.0.1:8000`.  
Untuk pengembangan frontend dengan Vite hot-reload, jalankan `npm run dev` pada terminal terpisah (atau gunakan `composer run dev`).

---

## Akun Uji Bawaan

`StorefrontDatabaseSeeder` menyediakan akun uji berikut untuk masuk melalui halaman `/login`:

| Peran | Email | Password |
|---|---|---|
| `MEMBER` | `ahmad.fauzi@polman-bandung.ac.id` | `password123` |
| `CASHIER` | `kasir@polman-bandung.ac.id` | `kasir123` |
| `MANAGER` | `manajer@polman-bandung.ac.id` | `manajer123` |

---

## Menjalankan Pengujian (Testing)

Rangkaian pengujian utama menggunakan database PostgreSQL terpisah **`koperadata_test`** (terkonfigurasi di `phpunit.pgsql.xml`) agar tidak mengganggu data operasional `koperadata`.

Pastikan database `koperadata_test` tersedia pada PostgreSQL di port `5433`, lalu jalankan:

```bash
php artisan test -c phpunit.pgsql.xml
```

---

## Rute Utama Aplikasi

### Rute Web (`routes/web.php`)
- `/` — Etalase publik & belanja anggota
- `/login`, `/logout` — Autentikasi pengguna berbasis database
- `/cashier/pos`, `/cashier/antrian`, `/cashier/piutang` — Portal Kasir
- `/manager/dashboard`, `/manager/transaksi`, `/manager/piutang`, `/manager/produk`, `/manager/import`, `/manager/laporan`, `/manager/sistem` — Portal Manajer

### Rute API v1 (`routes/api.php`)
- `POST /api/v1/auth/login`, `POST /api/v1/auth/logout`, `GET /api/v1/auth/me`
- `POST /api/v1/orders`, `GET /api/v1/orders/my`, `GET /api/v1/members/me/credit`, `GET /api/v1/members/me/receivables`
- `GET /api/v1/products/lookup`, `GET /api/v1/cashier/orders`, `POST /api/v1/pos/sale`
- `POST /api/v1/orders/{id}/payments`, `POST /api/v1/payments/{id}/verify`, `POST /api/v1/orders/{id}/confirm-pickup`, `POST /api/v1/orders/{id}/cancel`
- `GET /api/v1/cashier/receivables`, `POST /api/v1/receivables/payments`
- `GET|PATCH /api/v1/credit-policy`, `PATCH /api/v1/members/{id}/credit-status`, `GET /api/v1/manager/receivables`

---

## Dokumentasi Lanjutan

- [PRD_KoperasiKu.md](PRD_KoperasiKu.md) — Spesifikasi kebutuhan produk (PRD v1.4)
- [docs/BACKEND_AUDIT.md](docs/BACKEND_AUDIT.md) — Catatan audit backend
- [docs/HPP_PIC_C_INTEGRATION_CONTRACT.md](docs/HPP_PIC_C_INTEGRATION_CONTRACT.md) — Kontrak integrasi HPP dan master data
- [docs/UI_RESPONSIVE_20261118.md](docs/UI_RESPONSIVE_20261118.md) — Catatan pembaruan antarmuka responsif
