{{-- ==============================================================================
   checkout-modal.blade.php - Konfirmasi Checkout & Modal Sukses Kode Pickup
   - 3 Metode Pembayaran Resmi: Tunai di Kasir, Transfer Bank, Piutang Anggota 7 Hari
   - Penerbitan Kode Pickup Unik PKP-XXXXX dengan tombol Salin Kode
   ============================================================================== --}}

<!-- Modal Checkout Pesanan -->
<div class="modal fade" id="checkoutModal" tabindex="-1" aria-labelledby="checkoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 rounded-2 bg-primary text-white d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-bag-check fs-6" aria-hidden="true"></i>
                    </div>
                    <h5 class="modal-title fw-bold text-dark fs-6" id="checkoutModalLabel">Konfirmasi Checkout Pesanan</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body p-3 p-md-4">
                <form id="checkoutForm" onsubmit="handleCheckoutSubmit(event)">
                    <!-- Info Pemesan -->
                    <div class="mb-3">
                        <label for="checkoutMemberName" class="form-label small fw-bold text-secondary mb-1">ID Anggota / Nama Karyawan</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted px-3"><i class="bi bi-person-badge"></i></span>
                            <input type="text" id="checkoutMemberName" class="form-control ps-1" value="EMP-001 — Karyawan Koperasi" required>
                        </div>
                        <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">Diverifikasi oleh petugas kasir saat pengambilan barang fisik.</small>
                    </div>

                    <!-- Ringkasan Singkat Belanja -->
                    <div class="p-3 rounded-2 bg-light border mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1.5 small text-secondary">
                            <span>Total Pesanan</span>
                            <span id="modalCheckoutTotalItems" class="fw-semibold text-dark">0 Barang</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-dark small">Total Bayar</span>
                            <span id="modalCheckoutTotalPrice" class="fw-bold text-primary fs-6">Rp 0</span>
                        </div>
                    </div>

                    <!-- Pilihan Pembayaran Sesuai PRD -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary mb-2">Pilih Metode Pembayaran</label>
                        <div class="d-flex flex-column gap-2.5">
                            
                            <!-- Opsi 1: Cash -->
                            <label class="payment-option-card">
                                <input type="radio" name="payment_method" value="CASH" class="form-check-input mt-1" checked>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold text-dark small">Tunai di Kasir (CASH)</span>
                                        <span class="badge bg-success text-white" style="font-size: 0.65rem;">Langsung</span>
                                    </div>
                                    <div class="text-secondary small mt-0.5" style="font-size: 0.72rem;">Bayar saat serah terima barang di gerai KOPEG Mart.</div>
                                </div>
                            </label>

                            <!-- Opsi 2: Bank Transfer -->
                            <label class="payment-option-card">
                                <input type="radio" name="payment_method" value="BANK_TRANSFER" class="form-check-input mt-1">
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold text-dark small">Transfer Bank / QRIS</span>
                                        <span class="badge bg-primary text-white" style="font-size: 0.65rem;">Non-Tunai</span>
                                    </div>
                                    <div class="text-secondary small mt-0.5" style="font-size: 0.72rem;">Tunjukkan bukti transfer ke kasir saat pengambilan barang.</div>
                                </div>
                            </label>

                            <!-- Opsi 3: Piutang 7 Hari -->
                            <label class="payment-option-card">
                                <input type="radio" name="payment_method" value="CREDIT" class="form-check-input mt-1">
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold text-primary small">Piutang Anggota (Tempo 7 Hari)</span>
                                        <span class="badge bg-warning text-dark fw-bold" style="font-size: 0.65rem;">Khusus Anggota</span>
                                    </div>
                                    <div class="text-secondary small mt-0.5" style="font-size: 0.72rem;">Bunga 0%, jatuh tempo tepat 7 hari kalender setelah barang diambil.</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Catatan Pickup -->
                    <div class="p-3 rounded-2 bg-light border text-secondary small mb-4" style="font-size: 0.75rem;">
                        <i class="bi bi-info-circle text-primary me-1.5"></i> <strong>Sistem Pickup:</strong> Pesanan ini mereservasi stok di gerai. Pengurangan stok dan pencatatan buku kasir dilakukan saat fisik barang diserahkan di Jl. Kanayakan No. A11 Dago.
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm flex-grow-1 py-2" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" id="btnConfirmCheckout" class="btn btn-primary btn-sm flex-grow-1 py-2 fw-semibold d-inline-flex align-items-center justify-content-center shadow-sm">
                            <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                            <i class="bi bi-check2-circle me-1.5"></i>
                            <span>Buat Pesanan & Dapatkan Kode</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Bukti Pesanan & Kode Pickup Unik -->
<div class="modal fade" id="pickupSuccessModal" tabindex="-1" aria-labelledby="pickupSuccessModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-body text-center p-4 p-md-5">
                <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-circle bg-success-subtle text-success mb-3" style="width: 64px; height: 64px;">
                    <i class="bi bi-check-lg fs-2"></i>
                </div>

                <h4 class="fw-bold text-dark mb-1 fs-5">Pesanan Berhasil Dibuat!</h4>
                <p class="text-secondary small mb-4">Stok pesanan Anda telah berhasil di-reservasi di etalase KoperasiKu.</p>

                <!-- Box Kode Pickup Resmi -->
                <div class="p-4 rounded-3 bg-light border border-2 border-primary border-opacity-25 mb-4 position-relative">
                    <small class="text-uppercase text-muted fw-bold d-block mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                        Tunjukkan Kode Ini ke Kasir Gerai:
                    </small>
                    <div id="displayPickupCode" class="display-6 fw-extrabold text-primary font-monospace tracking-wide my-1">
                        PKP-84920
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2 d-inline-flex align-items-center gap-1.5 px-3 py-1.5" onclick="copyPickupCode()">
                        <i class="bi bi-clipboard me-1" id="copyIcon"></i>
                        <span id="copyBtnText">Salin Kode</span>
                    </button>
                </div>

                <!-- Petunjuk Pengambilan -->
                <div class="text-start p-3 rounded-2 bg-light border small text-secondary mb-4" style="font-size: 0.8rem;">
                    <div class="fw-bold text-dark mb-1"><i class="bi bi-geo-alt-fill text-danger me-1.5"></i> Lokasi Pengambilan:</div>
                    <p class="mb-2">Gerai KOPEG Mart, Jl. Kanayakan No. A11 (depan Kampus POLMAN Bandung). Buka Senin–Sabtu 07.00–18.00 WIB.</p>
                    <div class="fw-bold text-dark mb-1"><i class="bi bi-credit-card me-1.5 text-primary"></i> Metode Bayar Dipilih:</div>
                    <p id="displayPaymentMethodName" class="mb-0 text-primary fw-semibold">Tunai saat Ambil (CASH)</p>
                </div>

                <!-- Tombol Navigasi Lanjutan -->
                <div class="d-flex flex-column gap-2">
                    <button type="button" class="btn btn-primary fw-semibold py-2.5 shadow-sm" data-bs-dismiss="modal">
                        Selesai & Lanjut Belanja
                    </button>
                    <a href="{{ route('login') }}" class="btn btn-outline-secondary btn-sm py-2 fw-medium d-inline-flex align-items-center justify-content-center">
                        <i class="bi bi-person-check me-2"></i>
                        <span>Masuk Akun untuk Lihat Riwayat Pesanan</span>
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>
