{{-- ==============================================================================
   mobile-cart-bar.blade.php - Sticky Floating Cart Bar Mobile (Tokopedia Style)
   - Muncul otomatis di layar ponsel ketika keranjang terisi >= 1 barang
   - Menampilkan ringkasan kuantitas item, total rupiah belanja, dan tombol checkout/buka keranjang
   ============================================================================== --}}
<div id="mobileStickyCartBar" class="mobile-sticky-cart-bar d-md-none d-none" aria-live="polite">
    <div class="container px-3">
        <div class="d-flex align-items-center justify-content-between gap-2 py-2">
            <div class="d-flex align-items-center gap-2" onclick="openCartDrawer()" role="button" style="cursor: pointer;">
                <div class="position-relative p-2 rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bi bi-cart3 fs-6" aria-hidden="true"></i>
                    <span id="floatingCartCountBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                        0
                    </span>
                </div>
                <div class="lh-sm">
                    <small class="text-muted d-block" style="font-size: 0.68rem;">Total (<span id="floatingCartItemsCount">0 item</span>):</small>
                    <span class="fw-extrabold text-primary fs-6 font-monospace" id="floatingCartPriceDisplay">Rp 0</span>
                </div>
            </div>

            <button type="button" class="btn btn-primary btn-sm px-3.5 py-2 fw-bold d-inline-flex align-items-center gap-1.5 rounded-pill shadow-sm" onclick="openCartDrawer()">
                <span>Buka Keranjang</span>
                <i class="bi bi-chevron-right fs-7"></i>
            </button>
        </div>
    </div>
</div>
