# Perbandingan PIC D dan PIC B — 17 November 2026

Target utama D:\BDT2\KoperasiKu sudah berisi migrasi/model B dan C, berbeda dari kondisi audit16November. Pembanding D:\BDT2\KoperasiKu-B-Core tidak dimodifikasi pada finalisasi ini.

Kesenjangan D yang diperbaiki: payments.verified_at, UTC completed_at baru, provenance import_batch_id, konflik CREATE inventory_balances, relasi kategori/supplier dalam extract, autentikasi/role untuk laporan.

Perbedaan dan ownership tercatat pada KOPERADATA_GIT_MERGE_GUIDE.md. Schema aktual lengkap pada erd_actual.json. Histori status lama tidak lagi mencerminkan codebase saat ini. B masih perlu menyelesaikan identity UUID, transfer verification/allocation, idempotency/audit dan kontrol transaksi. C histori import masih belum lengkap. Lihat KOPERADATA_FINAL_AUDIT.md untuk cakupan acceptance yang benar-benar diuji.
