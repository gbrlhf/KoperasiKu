{{-- ==============================================================================
   product-detail-modal.blade.php - Tokopedia/Marketplace Style Product Detail Modal
   Interaktivitas:
   - Foto produk besar resolusi tinggi dengan badge status
   - Rating, ulasan pembeli, dan jumlah produk terjual
   - Harga resmi anggota tanpa potongan diskon
   - Tabel spesifikasi resmi produk (Berat, sertifikasi Halal, kemasan, penyimpanan)
   - Deskripsi lengkap komoditas sembako/kebutuhan rumah tangga
   - Pengatur jumlah beli (Quantity Stepper) dengan kalkulasi subtotal otomatis
   - Tombol aksi cepat: "+ Keranjang" dan "Beli Langsung"
   ============================================================================== --}}

<div class="modal fade" id="productDetailModal" tabindex="-1" aria-labelledby="productDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            
            <!-- Modal Header -->
            <div class="modal-header border-bottom py-2.5 px-3 px-md-4 bg-light align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-1 fw-semibold" style="font-size: 0.72rem;" id="modalProductCategory">
                        Kategori
                    </span>
                    <span class="text-muted small">•</span>
                    <span class="text-secondary small d-none d-sm-inline" id="modalProductSku">SKU: SEM-001</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup jendela rincian produk"></button>
            </div>

            <!-- Modal Body (2-Column Tokopedia Layout) -->
            <div class="modal-body p-3 p-md-4">
                <div class="row g-4">
                    
                    <!-- Left Column: Product Imagery & Store Badge -->
                    <div class="col-12 col-md-5">
                        <div class="position-relative rounded-3 overflow-hidden bg-white border mb-3 d-flex align-items-center justify-content-center p-2" style="aspect-ratio: 1/1; max-height: 320px;">
                            <img id="modalProductImage" src="" alt="" class="w-100 h-100 object-fit-contain d-block" onerror="this.classList.add('d-none'); document.getElementById('modalImageFallback').classList.remove('d-none');">
                            
                            <!-- Fallback Icon if Image Fails -->
                            <div id="modalImageFallback" class="w-100 h-100 d-none d-flex align-items-center justify-content-center text-secondary bg-light">
                                <i id="modalFallbackIcon" class="bi bi-box-seam" style="font-size: 4rem;"></i>
                            </div>

                            <!-- Badge Terlaris / Promo -->
                            <span id="modalProductBadge" class="position-absolute top-0 start-0 m-2.5 badge bg-primary text-white fw-bold px-2.5 py-1 rounded-1 shadow-xs" style="font-size: 0.72rem;">
                                Terlaris
                            </span>
                        </div>

                        <!-- Info Gerai Pengambilan (Pickup Official Store) -->
                        <div class="p-2.5 rounded-2 bg-light border d-flex align-items-center gap-2.5">
                            <div class="p-2 rounded-circle bg-white text-primary border shadow-xs d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                                <i class="bi bi-shop fs-5"></i>
                            </div>
                            <div class="lh-sm">
                                <div class="fw-bold text-dark small">Gerai KOPEG Mart Kanayakan A11</div>
                                <small class="text-success fw-semibold" style="font-size: 0.72rem;">
                                    <i class="bi bi-check-circle-fill me-1"></i> Stok Fisik Siap Diambil Hari Ini
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Title, Ratings, Pricing, Specs, Description & Purchase Actions -->
                    <div class="col-12 col-md-7 d-flex flex-column">
                        
                        <!-- Rating & Sold Summary Bar -->
                        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                            <div class="d-flex align-items-center text-warning small fw-bold">
                                <i class="bi bi-star-fill me-1"></i>
                                <span class="text-dark" id="modalProductRating">4.9</span>
                            </div>
                            <span class="text-muted small">•</span>
                            <span class="text-muted small" id="modalProductReviews">48 ulasan</span>
                            <span class="text-muted small">•</span>
                            <span class="badge bg-light text-secondary border rounded-1 small" id="modalProductSold">340+ terjual</span>
                        </div>

                        <!-- Product Title -->
                        <h4 class="fw-bold text-dark mb-2 lh-sm" id="modalProductTitle">
                            Nama Produk
                        </h4>

                        <!-- Price Section -->
                        <div class="p-2.5 rounded-2 bg-light border mb-3 d-flex align-items-baseline gap-2 flex-wrap">
                            <span class="fs-3 fw-extrabold text-primary" id="modalProductPrice">
                                Rp 0
                            </span>
                            <span class="text-muted small ms-auto" id="modalProductUnit">/ Sak</span>
                        </div>

                        <!-- Stock Status -->
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="badge bg-success-subtle text-success border border-success-subtle fw-semibold" id="modalProductStockBadge">
                                <i class="bi bi-box-seam me-1"></i> Tersedia: 32 Sak
                            </span>
                            <span class="text-muted small">Maksimal pesan per transaksi: 10 unit</span>
                        </div>

                        <!-- Tab Nav: Spesifikasi & Deskripsi -->
                        <ul class="nav nav-tabs nav-tabs-sm border-bottom mb-2.5" id="productDetailTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active fw-semibold text-secondary py-1.5 px-3" id="specs-tab" data-bs-toggle="tab" data-bs-target="#specs-pane" type="button" role="tab" aria-selected="true" style="font-size: 0.82rem;">
                                    Spesifikasi
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-semibold text-secondary py-1.5 px-3" id="desc-tab" data-bs-toggle="tab" data-bs-target="#desc-pane" type="button" role="tab" aria-selected="false" style="font-size: 0.82rem;">
                                    Deskripsi Produk
                                </button>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content mb-3 flex-grow-1" id="productDetailTabsContent">
                            <!-- Pane 1: Spesifikasi Table -->
                            <div class="tab-pane fade show active" id="specs-pane" role="tabpanel" tabindex="0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered align-middle mb-0" style="font-size: 0.8rem;">
                                        <tbody id="modalProductSpecsBody">
                                            <!-- Dynamically generated rows -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Pane 2: Deskripsi Lengkap -->
                            <div class="tab-pane fade" id="desc-pane" role="tabpanel" tabindex="0">
                                <p class="text-secondary small mb-0 lh-base" id="modalProductDescription" style="font-size: 0.83rem;">
                                    Deskripsi lengkap produk komoditas koperasi...
                                </p>
                            </div>
                        </div>

                        @if(!empty($landingShowCart))
                            <!-- Bottom Stepper & Action Controls -->
                            <div class="pt-3 border-top mt-auto">
                                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="small fw-semibold text-dark">Atur Jumlah:</span>
                                        <div class="btn-group btn-group-sm border rounded-2 bg-white shadow-xs">
                                            <button type="button" class="btn btn-light px-2.5 py-1 text-secondary" id="modalQtyMinusBtn" onclick="changeModalQty(-1)" aria-label="Kurangi jumlah">
                                                <i class="bi bi-dash fw-bold"></i>
                                            </button>
                                            <span class="px-3 py-1 bg-white fw-bold text-dark d-flex align-items-center" id="modalQtyDisplay" style="min-width: 36px; justify-content: center;">
                                                1
                                            </span>
                                            <button type="button" class="btn btn-light px-2.5 py-1 text-secondary" id="modalQtyPlusBtn" onclick="changeModalQty(1)" aria-label="Tambah jumlah">
                                                <i class="bi bi-plus fw-bold"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="text-end">
                                        <small class="text-muted d-block" style="font-size: 0.72rem;">Subtotal Belanja:</small>
                                        <span class="fs-5 fw-extrabold text-primary" id="modalSubtotalDisplay">
                                            Rp 0
                                        </span>
                                    </div>
                                </div>

                                <!-- CTA Buttons: + Keranjang & Beli Langsung -->
                                <div class="row g-2">
                                    <div class="col-6">
                                        <button type="button" class="btn btn-outline-primary w-100 fw-bold py-2 shadow-xs d-inline-flex align-items-center justify-content-center gap-1.5" onclick="modalAddToCart()">
                                            <i class="bi bi-cart-plus fs-6"></i>
                                            <span>+ Keranjang</span>
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button type="button" class="btn btn-primary w-100 fw-bold py-2 shadow-xs d-inline-flex align-items-center justify-content-center gap-1.5" onclick="modalBuyNow()">
                                            <i class="bi bi-lightning-charge-fill fs-6"></i>
                                            <span>Beli Langsung</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="pt-3 border-top mt-auto">
                                <span class="badge bg-light text-secondary border px-2.5 py-1.5 small fw-medium">
                                    <i class="bi bi-eye me-1"></i> Mode pratinjau katalog (read-only) untuk petugas koperasi
                                </span>
                            </div>
                        @endif

                    </div>
                </div>
            </div>

            <!-- Modal Footer Note -->
            <div class="modal-footer bg-light py-2 px-3 px-md-4 border-top d-flex justify-content-center align-items-center">
                <small class="text-muted" style="font-size: 0.74rem;">
                    <i class="bi bi-shield-check text-success me-1"></i> Jaminan komoditas asli & resmi Koperasi Konsumen Polman Bandung
                </small>
            </div>

        </div>
    </div>
</div>
