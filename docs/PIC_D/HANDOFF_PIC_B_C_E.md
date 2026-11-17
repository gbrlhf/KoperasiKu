# Handoff PIC D — 17 November 2026

Status terbaru dan bukti uji ada di KOPERADATA_FINAL_AUDIT.md dan KOPERADATA_TEST_RESULTS.md di direktori ini. Seluruh D-T01 sampai D-T08 diuji pada lingkup analitik dengan fixture sintetis, bukan sertifikasi seluruh transaksi B/C.

PIC A menggunakan API server untuk metrik keuangan serta last_updated_at, is_stale, data_quality, snapshot_available dan period_covered. Jangan menghitung saldo/laba di JavaScript. Dashboard, piutang dan laporan manager sudah membaca mart. Navbar/login kini memakai akun nyata dan logout POST.

PIC B meninjau Payment verified_at, UTC completed_at baru, import_batch_id dan guard inventory migration. Timestamp naive kontrak D adalah UTC. Jangan mengubah timestamp lama tanpa bukti. Masih diperlukan identity UUID yang seragam, endpoint verifikasi+alokasi atomik, audit dan idempotency transaksi. RequireManager tidak menerima role dari parameter/session mock.

PIC C menyediakan master kategori/supplier melalui relasi yang sekarang dibaca adapter SELECT. Selesaikan workflow SALES_HISTORY yang menulis import_batch_id saat COMMITTED, HPP unknown tanpa angka rekaan, keputusan cut-off, dan bukti kelengkapan biaya. D tidak mengubah stok saat membaca histori.

PIC B/C perlu menetapkan ledger retur/nota kredit, status final, tanggal posting, perubahan omzet/HPP/piutang dan hubungan refund. Proposal ada di contracts/data-schemas/integration_questions.md. Pipeline belum mengonsumsi proposal tersebut. Flag source_adjustments_supported=false wajib tetap terlihat sampai integrasi disahkan dan diuji.

PIC E menerima feed SKU/hari dengan zero-sale days hanya dalam cakupan ETL sukses, stockout nullable, lead_time_days dari pemasok. Kontrak forecast_input/output tersedia, benchmark10k/100k/1M tiga ulangan sudah dijalankan. Model, backtest, loader hasil forecast dan CI deployment E belum diverifikasi. Forecast tidak boleh mengubah fakta aktual atau OLTP.

Kredensial deploy diisi pada data-platform/.env lokal, tidak dalam ZIP. Role reader/writer dibuat/grant oleh DBA menggunakan provision_roles.sql. Secret pseudonim harus persisten. Contoh uji cut-off dan data anggota/transaksi sintetis tidak boleh dipakai sebagai data bisnis.
