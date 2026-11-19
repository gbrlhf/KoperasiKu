# PIC C — Master Data, HPP & Harga Jual, Supplier, Excel Migration

**Pemilik:** Anggota tim 3  
**Area:** Kualitas master data dan impor historis serta perhitungan dasar harga pokok  
**Review utama:** PIC B untuk perubahan database operasional, PIC D untuk definisi data historis dan cost.

## 1. Tujuan PIC C

Menyiapkan data yang benar dan konsisten untuk operasional **dan** Big Data. Fokus pada profil anggota, produk dengan SKU/barcode, harga pokok & harga jual, supplier, penerimaan persediaan, biaya operasional, dan migrasi data lama Excel/CSV yang aman untuk data transaksi yang sedang berjalan.

## 2. Modul dan tabel

| Domain | Tabel & penanggung jawab |
|---|---|
| Data karyawan | `members` (pengguna/auth dan role dari PIC B) |
| Master barang | `categories`, `products`, `suppliers`, (opsional `product_suppliers`) |
| Harga | `product_price_histories`, `products.selling_price`, `products.current_cost` |
| Penerimaan | `purchase_receipts`, `receipt_items` (mutasi stok via service B) |
| Beban | `operating_expenses` |
| Excel | `import_batches`, `import_rows` (staging), `import_errors`, `import_mappings` (opsional) |

**Aturan biaya:** gunakan nilai uang `NUMERIC(18,2)`; untuk MVP metode HPP **moving weighted average (usulan sampai disahkan manajer/proyek)**. `products.current_cost` adalah harga pokok acuan/penilaian saldo saat ini, bukan kebenaran HPP dari transaksi lama. `order_items.hpp_snapshot` diisi pada saat fulfilment melalui kontrak PIC B.

## 3. Task yang harus selesai

### Sprint 0–1 — Master data

- [ ] **C-01** Desain input karyawan: nomor anggota dan `employee_number` unik, divisi, status aktif, relasi `users` (B).
- [ ] **C-02** CRUD kategori, produk, SKU unik, barcode unik bila ada, satuan, image opsional, status aktif.
- [ ] **C-03** Supplier + `lead_time_days` dan data kontak minimum yang diperlukan.
- [ ] **C-04** Produk dengan harga jual, cost/HPP acuan, reorder point; validasi harga nonnegatif dan warning price < HPP.
- [ ] **C-05** Buat service perubahan harga khusus Manajer: `old/new`, alasan, actor, timestamp pada `product_price_histories`.

### Sprint 2–3 — HPP, stock receipt, Excel

- [ ] **C-06** Penerimaan pemasok: `purchase_receipts`+`receipt_items`; hitung metode HPP yang disepakati dan panggil `InventoryService.receive()` milik B dalam DB transaction.
- [ ] **C-07** Biaya operasional: tanggal, kategori, nominal, bukti opsional, pembuat; hanya Manajer yang mengelola.
- [ ] **C-08** Sediakan template XLSX/CSV untuk anggota, produk, stok awal, supplier, histori transaksi.
- [ ] **C-09** Upload file dengan validasi MIME/ukuran, record checksum, autentikasi `MANAGER`, simpan file ke storage privat.
- [ ] **C-10** Mapping nama kolom sumber ke schema standar; preview N baris serta total diterima, ditolak, duplikat.
- [ ] **C-11** Normalisasi data tanggal, separator ribuan/desimal, kode SKU/barcode, unit, nama; pilih aturan konservatif saat ambigu.
- [ ] **C-12** Staging baris, validasi cross-table FK/unik, error line + alasan dan unduhan laporan error.
- [ ] **C-13** Commit idempotent berdasarkan batch/file hash + source transaction key; jika sumber tidak punya key kuat, minta keputusan manual sebelum commit.
- [ ] **C-14** Import saldo awal stok pakai `cutoff_at`; **jangan** menjalankan stok keluar lagi atas transaksi sebelum cutoff.
- [ ] **C-15** Histori penjualan impor: pertahankan source ID/status, tanggal, qty, price/HPP yang diketahui; tandai `cost_missing=true` jika tidak ada basis HPP valid.
- [ ] **C-16** Jika ada piutang lama di Excel, **jangan** aktifkan saldo piutang melalui upload sembarang; butuh mapping tagihan, outstanding awal, due date, rekonsiliasi disetujui PIC B/Manajer.
- [ ] **C-17** Expose metadata penilaian kualitas data dan laporan hasil import untuk PIC D.

### Sprint 4–6 — Integrasi

- [ ] **C-18** Konsistensi snapshot harga & cost dengan PIC B: checkout lock harga jual, HPP final dari persediaan saat fulfilled.
- [ ] **C-19** Ekspor master data/biaya sesuai akses; lindungi CSV formula injection.
- [ ] **C-20** Test import ulang, file rusak, data duplikat, cut-off, rollback & histori HPP.

## 4. Struktur implementasi

```text
apps/web/app/
├── Http/Controllers/Api/V1/{Member,Product,Category,Supplier,Import,Pricing,Expense}Controller.php
├── Http/Requests/{Product,ImportUpload,PriceChange,Receipt,Expense}Request.php
├── Services/
│   ├── MasterData/{MemberService,ProductService,SupplierService}.php
│   ├── Pricing/{PriceChangeService,WeightedAverageCostService}.php
│   ├── Import/{ImportBatchService,ColumnMapper,RowValidator,ImportCommitService}.php
│   └── Expenses/OperatingExpenseService.php
└── Jobs/{ParseImportJob,ValidateImportJob}.php
contracts/excel-templates/{members,products,stock_opening,sales_history}.csv
apps/web/tests/Feature/{Products,Import,Pricing,Receipts}Test.php
```

## 5. Data import contract (wajib disepakati)

| Data | Minimal field | Key untuk dedupe | Perlakuan |
|---|---|---|---|
| Anggota | `employee_number`, `full_name`, `status` | `employee_number` | update terkontrol setelah preview |
| Produk | `sku`, `product_name`, `category`, `selling_price`, `barcode?` | `sku`; barcode unik opsional | jangan overwrite harga tanpa log |
| Stok awal | `sku`, `cutoff_date`, `quantity`, `unit_cost` | `sku+cutoff_date+source_batch` | satu saldo yang direkonsiliasi |
| Histori transaksi | `source_transaction_id`, `line_no`, `date`, `sku`, `qty`, `price`, `status` | `source_transaction_id+line_no` | hanya COMPLETED menjadi penjualan historis |
| Supplier | `supplier_code/name`, `lead_time_days` | kode supplier stabil | normalisasi & cek duplikat |
| Biaya operasional | `date`, `category`, `amount`, `reference` | referensi + tanggal | butuh bukti/aturan review |

Jika Excel hanya menyimpan total transaksi tanpa item/SKU, PIC C tidak mengarang rinciannya. Simpan sebagai data terpisah untuk laporan yang sesuai dan labeli keterbatasan bagi D/E.

## 6. Aturan arsitektur khusus PIC C

1. **Master data adalah domain C, transaksional inventory domain B.** Jangan `UPDATE inventory_balances` langsung dari impor/penerimaan; gunakan B `InventoryService` dengan `reason`, `actor`, `source_reference`.
2. Harga jual hanya dapat diedit MANAGER. HPP/master cost tidak boleh diekspos ke role Anggota/Kasir melalui API/view.
3. Saat harga berubah, item pesanan terdahulu tetap menyimpan snapshot. Ubah harga masa depan, jangan mengedit order historis.
4. Stok awal **harus** punya cut-off yang terkonfirmasi; import histori lebih lama tidak menjadi movement baru di stok aktif.
5. Data mentah dan staging disimpan privat; `preview` dan `commit` adalah langkah terpisah, semua edit manual diaudit.
6. Import job boleh async (Laravel Queue/Redis), tetapi UI harus bisa poll status. Commit ulang tidak menghasilkan data ganda.
7. HPP persediaan memerlukan metode konsisten, dan koreksi cost dilakukan melalui jurnal/adjustment terkontrol. Tidak mengedit HPP historis diam-diam.
8. Batasi ukuran, MIME, jumlah row, normalisasi rumus Excel ke nilai yang diverifikasi; jangan mengeksekusi macro/script pada file.
9. Field anggota sensitif tidak dikirim ke `data-platform` kecuali perlu; koordinasikan pseudonimisasi dengan D.
10. Jika aturan sumber data tidak jelas, tandai `REQUIRES_REVIEW` dan cegah commit daripada menebak.

## 7. API yang disediakan dan dependensi

- CRUD master: `/api/v1/members`, `/products`, `/categories`, `/suppliers` (detail route disepakati di OpenAPI dengan B).
- Pricing: `PATCH /api/v1/products/{id}/pricing` (MANAGER) dan `GET /api/v1/products/{id}/price-history` (MANAGER).
- Supplier receipt: `POST /api/v1/inventory/receipts` memanggil InventoryService milik B.
- Excel: `POST /api/v1/imports`, `GET /api/v1/imports/{id}/preview`, `POST /api/v1/imports/{id}/commit`, `GET /api/v1/imports/{id}/errors`.
- Biaya: `GET/POST /api/v1/operating-expenses` dan operasi edit berbasis policy.
- **B tergantung C** untuk SKU, barcode, harga dan cost; **D tergantung C** untuk sumber data historis/cost completeness; **A tergantung C** untuk form dan preview; **E** membutuhkan data produk/lead time untuk restock.

## 8. Test case & definition of done

- [ ] **C-T01** SKU duplikat ditolak/upsert terkontrol; barcode NULL boleh, barcode terisi unik.
- [ ] **C-T02** Kasir/Anggota tidak dapat mengubah pricing atau membaca HPP.
- [ ] **C-T03** Ganti harga 18k→20k: pesanan checkout sebelumnya tetap 18k.
- [ ] **C-T04** Penerimaan stok 20 unit menambah on_hand 20 tepat satu kali beserta movement dan HPP cost.
- [ ] **C-T05** Import sama dua kali -> jumlah transaksi/anggota/stok tidak tergandakan.
- [ ] **C-T06** Histori sebelum cut-off tidak mengurangi stok berjalan.
- [ ] **C-T07** File Excel dengan kolom salah/nominal tidak valid tampil dengan lokasi error; tidak commit secara parsial tanpa aturan.
- [ ] **C-T08** HPP tidak lengkap → flag quality disediakan untuk laporan, bukan laba palsu.
- [ ] **C-T09** Ubah cost master tercatat actor, before/after, waktu, alasan.

**Output:** migrations + CRUD master, import staging/idempotency, template Excel, pricing history, biaya operasional, receipt/HPP, tes dan dokumentasi pemetaan Excel nyata.
