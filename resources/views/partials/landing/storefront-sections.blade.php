{{-- ==============================================================================
   storefront-sections.blade.php - Katalog Tunggal Semua Produk KoperasiKu
   Menggantikan seksi terpisah (Best Deals, Bestseller dll) menjadi satu etalase
   terpadu dengan tab filter kategori interaktif dan pencarian real-time.
   ============================================================================== --}}

<!-- Anchor aliases untuk backward-compatibility -->
<div id="best-deals" class="visually-hidden"></div>
<div id="sembako-section" class="visually-hidden"></div>
<div id="household-section" class="visually-hidden"></div>
<div id="snack-section" class="visually-hidden"></div>

<section id="allProductsSection" class="py-4 py-lg-5 bg-light border-bottom">
    <div class="container px-3 px-lg-4">
        
        <!-- Section Header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 mb-md-4 gap-2">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                    <h2 class="fw-extrabold text-dark mb-0 fs-4">Semua Produk Koperasi</h2>
                    <span class="badge bg-primary text-white rounded-pill px-2.5 py-1 fw-bold" style="font-size: 0.72rem;">
                        {{ count($allProducts ?? $allProductsMap) }} PRODUK TERSEDIA
                    </span>
                </div>
                <p class="text-secondary small mb-0">
                    Katalog lengkap komoditas pokok, sembako, bumbu dapur, perlengkapan rumah tangga, dan aneka camilan anggota.
                </p>
            </div>
            
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1.5 fw-semibold small rounded-pill">
                    <i class="bi bi-shop me-1.5"></i> Pickup Gerai Kanayakan A11
                </span>
            </div>
        </div>

        <!-- Filter Kategori Cepat (Horizontal Pills) -->
        <div class="category-filter-pills-wrapper mb-4">
            <div class="d-flex align-items-center gap-2 overflow-x-auto pb-2" id="storefrontCategoryPills" style="scrollbar-width: thin;">
                <button type="button" class="btn btn-sm btn-primary rounded-pill fw-semibold px-3 category-pill active text-nowrap" onclick="filterByPill(this, '')">
                    <i class="bi bi-grid-fill me-1"></i> Semua Produk ({{ count($allProducts ?? $allProductsMap) }})
                </button>
                @foreach($categories as $cat)
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill fw-semibold px-3 bg-white text-dark border category-pill text-nowrap" onclick="filterByPill(this, '{{ addslashes($cat['name']) }}')">
                        <i class="bi {{ $cat['icon'] }} me-1 text-primary"></i> {{ $cat['name'] }}
                    </button>
                @endforeach
            </div>
        </div>

        <!-- 5-Column Product Grid (Semua 20 Produk Komoditas Koperasi) -->
        <div class="row row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-5 g-3 storefront-grid" id="mainProductGrid">
            @foreach(($allProducts ?? array_values($allProductsMap)) as $item)
                <div class="col product-item-col" data-name="{{ strtolower($item['name']) }}" data-category="{{ strtolower($item['category']) }}">
                    @include('partials.landing.product-card', ['item' => $item])
                </div>
            @endforeach
        </div>

        <!-- Empty State Jika Filter/Pencarian Nihil -->
        <div id="noProductsFoundState" class="text-center py-5 d-none">
            <div class="d-inline-flex align-items-center justify-content-center bg-white rounded-circle p-4 shadow-xs mb-3" style="width: 80px; height: 80px;">
                <i class="bi bi-search fs-2 text-muted"></i>
            </div>
            <h5 class="fw-bold text-dark mb-1">Produk Tidak Ditemukan</h5>
            <p class="text-secondary small mb-3">Tidak ada produk yang cocok dengan kata kunci atau kategori yang Anda pilih.</p>
            <button type="button" class="btn btn-primary btn-sm px-4 py-2 rounded-pill fw-semibold" onclick="showAllProducts(event)">
                <i class="bi bi-arrow-repeat me-1"></i> Tampilkan Semua Produk
            </button>
        </div>

    </div>
</section>
