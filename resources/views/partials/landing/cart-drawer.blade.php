{{-- ==============================================================================
   cart-drawer.blade.php - Offcanvas Keranjang Belanja Anggota KoperasiKu
   - Manajemen keranjang belanja langsung di sisi klien
   - Stepper jumlah barang (+/-) dan penghapusan item
   - Rangkuman total belanja dan tombol lanjut checkout
   ============================================================================== --}}
<div class="offcanvas offcanvas-end shadow" tabindex="-1" id="cartDrawer" aria-labelledby="cartDrawerLabel" style="width: 420px; max-width: 92vw;">
    <div class="offcanvas-header border-bottom py-3">
        <div class="d-flex align-items-center gap-2">
            <div class="p-2 rounded-2 bg-primary text-white d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                <i class="bi bi-cart3 fs-6" aria-hidden="true"></i>
            </div>
            <div>
                <h5 class="offcanvas-title fw-bold text-dark fs-6 mb-0" id="cartDrawerLabel">Keranjang Belanja</h5>
                <small class="text-muted" style="font-size: 0.72rem;">Reservasi belanja mandiri</small>
            </div>
        </div>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
    </div>

    <div class="offcanvas-body p-3 d-flex flex-column">
        @if(empty($isLoggedIn))
            <!-- Guest Login Alert -->
            <div class="p-3 rounded-2 bg-warning-subtle border border-warning-subtle text-dark small mb-3 shadow-xs">
                <div class="d-flex align-items-center gap-1.5 fw-bold mb-1">
                    <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                    <span>Status: Belum Masuk Akun</span>
                </div>
                <p class="mb-2.5 lh-sm" style="font-size: 0.76rem;">
                    Untuk dapat memesan sembako dan reservasi pengambilan di kasir gerai, Anda harus masuk sebagai anggota koperasi terlebih dahulu.
                </p>
                <button type="button" class="btn btn-warning btn-sm w-100 fw-bold text-dark py-1.5 d-inline-flex align-items-center justify-content-center gap-1.5 shadow-xs" onclick="openLoginModalFromCart()">
                    <i class="bi bi-box-arrow-in-right"></i>
                    <span>Masuk Akun Anggota</span>
                </button>
            </div>
        @endif

        <!-- Delivery Notice Pill -->
        <div class="p-3 rounded-2 bg-primary-subtle border border-primary-subtle text-primary small d-flex align-items-center gap-2.5 mb-3">
            <i class="bi bi-shop fs-5 flex-shrink-0 me-1" aria-hidden="true"></i>
            <div class="lh-sm" style="font-size: 0.78rem;">
                <strong>Pickup Only:</strong> Ambil langsung di Gerai KOPEG Mart Jl. Kanayakan No. A11 Dago tanpa biaya pengiriman.
            </div>
        </div>

        <!-- Cart Items List (Rendered dynamically via JS) -->
        <div id="cartItemList" class="flex-grow-1 overflow-y-auto mb-3 pe-1">
            <!-- Empty state fallback -->
            <div id="cartEmptyState" class="text-center py-5">
                <div class="d-inline-flex p-3 rounded-circle bg-light text-muted mb-3" style="width: 72px; height: 72px; align-items: center; justify-content: center;">
                    <i class="bi bi-bag-x" style="font-size: 2.2rem;" aria-hidden="true"></i>
                </div>
                <h6 class="fw-bold text-dark mb-1 fs-6">Keranjang Masih Kosong</h6>
                <p class="text-secondary small mb-3">Pilih beras, minyak goreng, dan sembako keluarga dari etalase.</p>
                <button type="button" class="btn btn-outline-primary btn-sm px-3 py-2 fw-semibold" data-bs-dismiss="offcanvas">
                    Mulai Belanja
                </button>
            </div>
        </div>

        <!-- Cart Footer Checkout Summary -->
        <div id="cartSummaryBox" class="border-top pt-3 mt-auto d-none">
            <div class="d-flex justify-content-between align-items-center mb-2 small text-secondary">
                <span>Total Kuantitas</span>
                <span id="cartTotalItemsCount" class="fw-semibold text-dark">0 item</span>
            </div>
            <div class="d-flex justify-content-between align-items-baseline mb-3">
                <span class="fw-bold text-dark fs-6">Total Belanja</span>
                <div class="d-flex align-items-baseline gap-1">
                    <span class="text-primary fw-bold fs-5" id="cartTotalPriceDisplay">Rp 0</span>
                </div>
            </div>

            <button type="button" class="btn btn-primary w-100 py-2.5 fw-semibold d-inline-flex align-items-center justify-content-center shadow-sm" onclick="openCheckoutModal()">
                <i class="bi bi-bag-check-fill me-2" aria-hidden="true"></i>
                <span>Lanjut ke Checkout Pesanan</span>
            </button>
            <div class="text-center mt-2">
                <small class="text-muted" style="font-size: 0.72rem;">
                    <i class="bi bi-shield-check text-success me-1"></i> Pembayaran saat serah terima barang di kasir
                </small>
            </div>
        </div>
    </div>
</div>
