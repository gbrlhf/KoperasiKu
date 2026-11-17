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

<!-- Modal Bukti Pesanan & Kode Pickup Unik Bergaya Boarding Pass (Tokopedia Style) -->
<div class="modal fade" id="pickupSuccessModal" tabindex="-1" aria-labelledby="pickupSuccessModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-body text-center p-3 p-md-4">
                
                <!-- Animasi Centang Sukses Interaktif -->
                <div class="success-animation-wrap mb-3">
                    <div class="success-checkmark-circle shadow-sm">
                        <i class="bi bi-check-lg fs-1 text-success"></i>
                    </div>
                </div>

                <h4 class="fw-extrabold text-dark mb-1 fs-5">Pesanan Berhasil Dibuat!</h4>
                <p class="text-secondary small mb-3">Stok barang Anda telah di-reservasi di etalase KoperasiKu.</p>

                <!-- KARTU TIKET PICKUP BERGAYA BOARDING PASS KONTRAS TINGGI -->
                <div class="boarding-pass-card mb-3 text-start position-relative shadow-sm">
                    <!-- Top Section: Identitas Gerai -->
                    <div class="boarding-pass-header p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-primary text-white fw-bold px-2 py-0.5 rounded-1" style="font-size: 0.65rem;">
                                PICKUP BOARDING PASS
                            </span>
                            <div class="fw-bold text-dark small mt-1">Gerai KOPEG Mart Bandung</div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-success-subtle text-success border border-success-subtle fw-semibold" style="font-size: 0.68rem;">
                                <i class="bi bi-check-circle-fill me-1"></i> RESERVED
                            </span>
                            <small class="text-muted d-block" style="font-size: 0.68rem;">Bebas Ongkir</small>
                        </div>
                    </div>

                    <!-- Perforated Notch Left & Right -->
                    <div class="boarding-pass-notch-left"></div>
                    <div class="boarding-pass-notch-right"></div>

                    <!-- Middle Section: Kode Pickup & Barcode Besar -->
                    <div class="p-3 text-center bg-white">
                        <small class="text-uppercase text-muted fw-bold d-block mb-1" style="font-size: 0.7rem; letter-spacing: 0.06em;">
                            Tunjukkan Kode Ini ke Kasir Gerai:
                        </small>
                        <div id="displayPickupCode" class="display-6 fw-extrabold text-primary font-monospace tracking-wider my-1">
                            PKP-84920
                        </div>

                        <!-- Scannable Barcode SVG Container -->
                        <div class="p-2.5 bg-light rounded-3 border d-inline-block my-2 text-center w-100" style="max-width: 320px;">
                            <svg id="checkoutPickupBarcodeSvg" class="w-100" style="max-height: 70px;"></svg>
                        </div>

                        <div class="d-flex justify-content-center gap-2 mt-1 flex-wrap">
                            <button type="button" class="btn btn-outline-dark btn-sm fw-semibold d-inline-flex align-items-center gap-1.5 px-3 py-1.5" onclick="openFullscreenPickupModal(document.getElementById('displayPickupCode').textContent.trim())">
                                <i class="bi bi-arrows-fullscreen text-primary"></i>
                                <span>Layar Penuh (Kecerahan Maksimal)</span>
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1.5 px-3 py-1.5" onclick="copyPickupCode()">
                                <i class="bi bi-clipboard me-1" id="copyIcon"></i>
                                <span id="copyBtnText">Salin Kode</span>
                            </button>
                        </div>
                    </div>

                    <!-- Bottom Section: Rincian Jadwal & Titik Temu -->
                    <div class="p-3 bg-light border-top" style="font-size: 0.78rem;">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted"><i class="bi bi-geo-alt-fill text-danger me-1"></i> Lokasi Ambil:</span>
                            <span class="fw-semibold text-dark text-end">Jl. Kanayakan No. A11 Dago</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted"><i class="bi bi-clock text-primary me-1"></i> Jam Layanan:</span>
                            <span class="text-dark">07.00 - 18.00 WIB (Senin - Sabtu)</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted"><i class="bi bi-credit-card text-success me-1"></i> Metode Bayar:</span>
                            <span id="displayPaymentMethodName" class="fw-bold text-primary">Tunai saat Ambil (CASH)</span>
                        </div>
                    </div>
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

<!-- Modal Fullscreen Barcode Kecerahan Maksimal untuk Kasir -->
<div class="modal fade" id="fullscreenBarcodeModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content bg-white text-dark text-center d-flex flex-column justify-content-between p-3 p-md-4">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary px-3 py-1.5 font-monospace fw-bold fs-6" id="fsPickupCodeBadge">PKP-84920</span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle small d-none d-sm-inline">Siap Pindai Kasir</span>
                </div>
                <button type="button" class="btn btn-outline-dark btn-sm px-3 fw-bold d-inline-flex align-items-center gap-1.5" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i>
                    <span>Tutup Layar Penuh</span>
                </button>
            </div>
            
            <div class="my-auto py-3">
                <small class="text-uppercase text-secondary fw-bold d-block mb-2" style="font-size: 0.85rem; letter-spacing: 0.08em;">
                    Arahkan Layar Ini ke Scanner Barcode Kasir Gerai
                </small>
                
                <div class="p-4 bg-white border border-2 border-dark rounded-4 d-inline-block shadow-lg mx-auto" style="max-width: 90vw;">
                    <div class="mb-3 d-flex justify-content-center">
                        <canvas id="fsPickupQrCanvas" style="width: 220px; height: 220px; max-width: 65vw; max-height: 65vw;"></canvas>
                    </div>
                    <div class="d-flex justify-content-center px-2">
                        <svg id="fsPickupBarcodeSvg" style="max-width: 100%; height: auto; max-height: 90px;"></svg>
                    </div>
                    <div class="display-5 fw-extrabold text-dark font-monospace mt-2 tracking-wider" id="fsPickupCodeText">
                        PKP-84920
                    </div>
                </div>

                <div class="mt-3 text-secondary small">
                    <i class="bi bi-brightness-high-fill text-warning fs-6 me-1"></i> Layar putih berlatar terang memudahkan pemindaian kamera kasir secara instan.
                </div>
            </div>

            <div class="pt-2 border-top">
                <button type="button" class="btn btn-dark w-100 py-2.5 fw-bold fs-6 rounded-3" data-bs-dismiss="modal">
                    Kembali ke Bukti Pesanan
                </button>
            </div>
        </div>
    </div>
</div>
