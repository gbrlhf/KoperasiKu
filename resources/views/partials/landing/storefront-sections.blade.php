{{-- ==============================================================================
   storefront-sections.blade.php - Multi-Aisle E-Commerce Sections (Inspirasi e-mox)
   1. Todays Best Deals For You (5 Kolom Grid)
   2. Triple Promo Banner Cards
   3. Bestseller in Grocery (Sembako & Bumbu Dapur - 5 Kolom Grid)
   4. Fullwidth Cooperative Piutang Banner
   5. Bestsellers in Household (Sabun & Kebersihan - 5 Kolom Grid)
   6. Bestsellers in Snacks & Minuman (5 Kolom Grid)
   ============================================================================== --}}

<!-- 1. TODAYS BEST DEALS FOR YOU (5-COLUMN AISLE) -->
<section id="best-deals" class="py-4 bg-light border-bottom">
    <div class="container px-3 px-lg-4">
        
        <!-- Header Strip -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h3 class="fw-bold text-dark mb-0 fs-5">Todays Best Deals For You!</h3>
                    <span class="badge bg-danger text-white rounded-pill px-2.5 py-1 fw-bold" style="font-size: 0.65rem;">
                        HOT DEALS
                    </span>
                </div>
                <small class="text-secondary" style="font-size: 0.78rem;">Produk kebutuhan pokok paling banyak dipesan anggota hari ini</small>
            </div>
            <a href="#sembako-section" class="text-primary text-decoration-none fw-semibold small d-inline-flex align-items-center gap-1">
                <span>Lihat Semua</span>
                <i class="bi bi-chevron-right" style="font-size: 0.75rem;"></i>
            </a>
        </div>

        <!-- 5-Column Product Grid -->
        <div class="row row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-5 g-3 storefront-grid">
            @foreach($bestDeals as $item)
                <div class="col product-item-col" data-name="{{ strtolower($item['name']) }}" data-category="{{ strtolower($item['category']) }}">
                    <div class="product-card">
                        <!-- Thumbnail Box with Real Image & Fallback -->
                        <div class="product-thumbnail-box" onclick="openProductDetailModal('{{ $item['id'] }}')" role="button" tabindex="0" title="Klik untuk lihat detail {{ $item['name'] }}" style="cursor: pointer;">
                            @if(!empty($item['image']))
                                <img src="{{ $item['image'] }}" alt="{{ $item['name'] }}" class="product-thumb-img" loading="lazy" onerror="this.classList.add('d-none'); this.nextElementSibling.classList.remove('d-none');">
                                <div class="product-icon-fallback d-none w-100 h-100 d-flex align-items-center justify-content-center">
                                    <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                                </div>
                            @else
                                <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                            @endif

                            @if(!empty($item['badge']))
                                <span class="position-absolute top-0 start-0 m-2 badge bg-primary text-white fw-semibold rounded-1" style="font-size: 0.65rem;">
                                    {{ $item['badge'] }}
                                </span>
                            @endif
                        </div>

                        <!-- Card Content -->
                        <div class="d-flex flex-column flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="text-muted text-uppercase fw-bold text-truncate" style="font-size: 0.68rem; letter-spacing: 0.03em;">
                                    {{ $item['category'] }}
                                </small>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-1" style="font-size: 0.65rem;">
                                    Stok {{ $item['available_stock'] }}
                                </span>
                            </div>

                            <div class="product-rating-bar">
                                <i class="bi bi-star-fill text-warning me-1"></i>
                                <span class="fw-bold text-dark">{{ $item['rating'] ?? 4.9 }}</span>
                                <span class="text-muted">({{ $item['reviews_count'] ?? 28 }})</span>
                                @if(!empty($item['sold_count']))
                                    <span class="text-muted ms-1 d-none d-sm-inline">• {{ $item['sold_count'] }}</span>
                                @endif
                            </div>

                            <h5 class="product-title" title="{{ $item['name'] }}" onclick="openProductDetailModal('{{ $item['id'] }}')" role="button" style="cursor: pointer;">
                                {{ $item['name'] }}
                            </h5>

                            <!-- Price & Add Button -->
                            <div class="mt-auto pt-2.5 border-top d-flex align-items-center justify-content-between gap-1">
                                <div>
                                    <div class="product-price-box">
                                        <span class="product-price-currency">Rp</span>
                                        <span class="product-price-number">{{ number_format($item['price'], 0, ',', '.') }}</span>
                                    </div>
                                    @if(!empty($item['original_price']) && $item['original_price'] > $item['price'])
                                        <div class="d-flex align-items-center gap-1">
                                            <del class="text-muted" style="font-size: 0.68rem;">Rp {{ number_format($item['original_price'], 0, ',', '.') }}</del>
                                        </div>
                                    @else
                                        <small class="text-muted" style="font-size: 0.68rem;">/ {{ $item['unit'] }}</small>
                                    @endif
                                </div>
                                <button type="button" class="btn-add-cart" onclick="addToCart('{{ $item['id'] }}', '{{ addslashes($item['name']) }}', {{ $item['price'] }}, '{{ $item['unit'] }}', '{{ $item['icon'] }}')" aria-label="Tambah {{ $item['name'] }} ke keranjang">
                                    <i class="bi bi-cart-plus me-1"></i>
                                    <span class="d-none d-sm-inline">+</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

    </div>
</section>

<!-- 2. TRIPLE PROMO BANNER CARDS (Inspirasi e-mox Page 1 Bottom) -->
<section class="py-4 bg-white border-bottom">
    <div class="container px-3 px-lg-4">
        <div class="row g-3 g-lg-4 row-cols-1 row-cols-md-3">
            
            <!-- Tile 1: Sayur Segar (Red / Magenta Gradient) -->
            <div class="col">
                <div class="promo-tile" style="background: linear-gradient(135deg, #9f1239 0%, #be123c 100%);">
                    <div>
                        <span class="badge bg-white text-danger px-2.5 py-1 fw-bold rounded-pill mb-2" style="font-size: 0.7rem;">
                            PASOKAN PAGI
                        </span>
                        <h4 class="fw-bold mb-1 fs-5">Sayur & Bahan Segar</h4>
                        <p class="small text-white text-opacity-90 mb-0 lh-sm">
                            Beras kualitas terjamin dan produk segar mitra petani anggota koperasi.
                        </p>
                    </div>
                    <div class="pt-3">
                        <a href="#sembako-section" class="btn btn-light btn-sm text-danger fw-bold px-3 py-2">
                            <span>Cek Produk</span>
                            <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Tile 2: Paket Hemat Sembako (Blue Gradient) -->
            <div class="col">
                <div class="promo-tile" style="background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 100%);">
                    <div>
                        <span class="badge bg-warning text-dark px-2.5 py-1 fw-bold rounded-pill mb-2" style="font-size: 0.7rem;">
                            PAKET HEMAT
                        </span>
                        <h4 class="fw-bold mb-1 fs-5">Sembako Awal Bulan</h4>
                        <p class="small text-white text-opacity-90 mb-0 lh-sm">
                            Beras ramos 5kg + Minyak goreng 2L + Gula kristal harga subsidi anggota.
                        </p>
                    </div>
                    <div class="pt-3">
                        <a href="#sembako-section" class="btn btn-warning text-dark btn-sm fw-bold px-3 py-2">
                            <span>Beli Paket</span>
                            <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Tile 3: Bebas Antre Pickup Gerai (Teal / Emerald Gradient) -->
            <div class="col">
                <div class="promo-tile" style="background: linear-gradient(135deg, #065f46 0%, #047857 100%);">
                    <div>
                        <span class="badge bg-white text-success px-2.5 py-1 fw-bold rounded-pill mb-2" style="font-size: 0.7rem;">
                            BEBAS ANTRE
                        </span>
                        <h4 class="fw-bold mb-1 fs-5">Ambil Cepat di Gerai</h4>
                        <p class="small text-white text-opacity-90 mb-0 lh-sm">
                            Pesan online, ambil langsung di kasir Gerai KOPEG Mart Jl. Kanayakan No. A11 Dago.
                        </p>
                    </div>
                    <div class="pt-3">
                        <a href="#alur-pesan" class="btn btn-light text-success btn-sm fw-bold px-3 py-2">
                            <span>Alur Pickup</span>
                            <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- 3. BESTSELLER IN GROCERY: SEMBAKO & BUMBU DAPUR (5-COLUMN AISLE) -->
<section id="sembako-section" class="py-4 bg-light border-bottom">
    <div class="container px-3 px-lg-4">
        
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="fw-bold text-dark mb-0 fs-5">Bestseller In Grocery (Sembako & Bumbu Dapur)</h3>
                <small class="text-secondary" style="font-size: 0.78rem;">Komoditas pokok keluarga dengan jaminan kualitas dan pasokan stabil</small>
            </div>
            <a href="#household-section" class="text-primary text-decoration-none fw-semibold small d-inline-flex align-items-center gap-1">
                <span>Lihat Semua</span>
                <i class="bi bi-chevron-right" style="font-size: 0.75rem;"></i>
            </a>
        </div>

        <div class="row row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-5 g-3 storefront-grid">
            @foreach($groceryProducts as $item)
                <div class="col product-item-col" data-name="{{ strtolower($item['name']) }}" data-category="{{ strtolower($item['category']) }}">
                    <div class="product-card">
                        <!-- Thumbnail Box with Real Image & Fallback -->
                        <div class="product-thumbnail-box" onclick="openProductDetailModal('{{ $item['id'] }}')" role="button" tabindex="0" title="Klik untuk lihat detail {{ $item['name'] }}" style="cursor: pointer;">
                            @if(!empty($item['image']))
                                <img src="{{ $item['image'] }}" alt="{{ $item['name'] }}" class="product-thumb-img" loading="lazy" onerror="this.classList.add('d-none'); this.nextElementSibling.classList.remove('d-none');">
                                <div class="product-icon-fallback d-none w-100 h-100 d-flex align-items-center justify-content-center">
                                    <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                                </div>
                            @else
                                <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                            @endif

                            @if(!empty($item['badge']))
                                <span class="position-absolute top-0 start-0 m-2 badge bg-success text-white fw-semibold rounded-1" style="font-size: 0.65rem;">
                                    {{ $item['badge'] }}
                                </span>
                            @endif
                        </div>

                        <div class="d-flex flex-column flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="text-muted text-uppercase fw-bold text-truncate" style="font-size: 0.68rem; letter-spacing: 0.03em;">
                                    {{ $item['category'] }}
                                </small>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-1" style="font-size: 0.65rem;">
                                    Stok {{ $item['available_stock'] }}
                                </span>
                            </div>

                            <div class="product-rating-bar">
                                <i class="bi bi-star-fill text-warning me-1"></i>
                                <span class="fw-bold text-dark">{{ $item['rating'] ?? 4.8 }}</span>
                                <span class="text-muted">({{ $item['reviews_count'] ?? 19 }})</span>
                                @if(!empty($item['sold_count']))
                                    <span class="text-muted ms-1 d-none d-sm-inline">• {{ $item['sold_count'] }}</span>
                                @endif
                            </div>

                            <h5 class="product-title" title="{{ $item['name'] }}" onclick="openProductDetailModal('{{ $item['id'] }}')" role="button" style="cursor: pointer;">
                                {{ $item['name'] }}
                            </h5>

                            <div class="mt-auto pt-2.5 border-top d-flex align-items-center justify-content-between gap-1">
                                <div>
                                    <div class="product-price-box">
                                        <span class="product-price-currency">Rp</span>
                                        <span class="product-price-number">{{ number_format($item['price'], 0, ',', '.') }}</span>
                                    </div>
                                    @if(!empty($item['original_price']) && $item['original_price'] > $item['price'])
                                        <div class="d-flex align-items-center gap-1">
                                            <del class="text-muted" style="font-size: 0.68rem;">Rp {{ number_format($item['original_price'], 0, ',', '.') }}</del>
                                        </div>
                                    @else
                                        <small class="text-muted" style="font-size: 0.68rem;">/ {{ $item['unit'] }}</small>
                                    @endif
                                </div>
                                <button type="button" class="btn-add-cart" onclick="addToCart('{{ $item['id'] }}', '{{ addslashes($item['name']) }}', {{ $item['price'] }}, '{{ $item['unit'] }}', '{{ $item['icon'] }}')" aria-label="Tambah {{ $item['name'] }} ke keranjang">
                                    <i class="bi bi-cart-plus me-1"></i>
                                    <span class="d-none d-sm-inline">+</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

    </div>
</section>

<!-- 4. FULLWIDTH COOPERATIVE PROMO BANNER (Inspirasi e-mox Green Banner) -->
<section class="py-4 bg-white border-bottom">
    <div class="container px-3 px-lg-4">
        <div class="promo-fullwidth-banner">
            <div class="row align-items-center g-4">
                <div class="col-12 col-lg-8">
                    <span class="badge bg-warning text-dark fw-bold px-3 py-1.5 rounded-pill mb-3" style="font-size: 0.75rem;">
                        <i class="bi bi-star-fill me-1"></i> FASILITAS RESMI ANGGOTA KOPEG
                    </span>
                    <h3 class="display-6 fw-extrabold text-white mb-2 lh-sm">
                        Belanja Kebutuhan Keluarga Lebih Tenang, <br class="d-none d-sm-inline">
                        Bayar Tempo 7 Hari Bunga 0%
                    </h3>
                    <p class="text-white text-opacity-90 fs-6 mb-0 pe-lg-4 lh-base">
                        Khusus anggota terdaftar Koperasi Konsumen Pegawai Polman Bandung. Bebas biaya uang muka, batas jatuh tempo tepat <strong>7 hari kalender</strong> terhitung sejak barang fisik diserahkan di kasir gerai.
                    </p>
                </div>
                <div class="col-12 col-lg-4 text-lg-end">
                    <div class="d-flex flex-column flex-sm-row flex-lg-column gap-2 justify-content-lg-end">
                        <button type="button" class="btn btn-warning text-dark fw-bold px-4 py-2.5 shadow-sm d-inline-flex align-items-center justify-content-center" data-bs-toggle="offcanvas" data-bs-target="#cartDrawer">
                            <i class="bi bi-cart-check-fill me-2"></i>
                            <span>Buka Keranjang</span>
                        </button>
                        <a href="#faq" class="btn btn-outline-light px-4 py-2 fw-semibold d-inline-flex align-items-center justify-content-center">
                            <span>Syarat & Ketentuan Piutang</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 5. BESTSELLERS IN HOUSEHOLD (SABUN & KEBERSIHAN RUMAH TANGGA - 5 KOLOM) -->
<section id="household-section" class="py-4 bg-light border-bottom">
    <div class="container px-3 px-lg-4">
        
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="fw-bold text-dark mb-0 fs-5">Bestsellers in Household (Sabun & Kebersihan)</h3>
                <small class="text-secondary" style="font-size: 0.78rem;">Deterjen, sabun cuci, pembersih lantai, dan kebutuhan sanitasi rumah</small>
            </div>
            <a href="#snack-section" class="text-primary text-decoration-none fw-semibold small d-inline-flex align-items-center gap-1">
                <span>Lihat Semua</span>
                <i class="bi bi-chevron-right" style="font-size: 0.75rem;"></i>
            </a>
        </div>

        <div class="row row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-5 g-3 storefront-grid">
            @foreach($householdProducts as $item)
                <div class="col product-item-col" data-name="{{ strtolower($item['name']) }}" data-category="{{ strtolower($item['category']) }}">
                    <div class="product-card">
                        <!-- Thumbnail Box with Real Image & Fallback -->
                        <div class="product-thumbnail-box" onclick="openProductDetailModal('{{ $item['id'] }}')" role="button" tabindex="0" title="Klik untuk lihat detail {{ $item['name'] }}" style="cursor: pointer;">
                            @if(!empty($item['image']))
                                <img src="{{ $item['image'] }}" alt="{{ $item['name'] }}" class="product-thumb-img" loading="lazy" onerror="this.classList.add('d-none'); this.nextElementSibling.classList.remove('d-none');">
                                <div class="product-icon-fallback d-none w-100 h-100 d-flex align-items-center justify-content-center">
                                    <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                                </div>
                            @else
                                <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                            @endif

                            @if(!empty($item['badge']))
                                <span class="position-absolute top-0 start-0 m-2 badge bg-secondary text-white fw-semibold rounded-1" style="font-size: 0.65rem;">
                                    {{ $item['badge'] }}
                                </span>
                            @endif
                        </div>

                        <div class="d-flex flex-column flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="text-muted text-uppercase fw-bold text-truncate" style="font-size: 0.68rem; letter-spacing: 0.03em;">
                                    {{ $item['category'] }}
                                </small>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-1" style="font-size: 0.65rem;">
                                    Stok {{ $item['available_stock'] }}
                                </span>
                            </div>

                            <div class="product-rating-bar">
                                <i class="bi bi-star-fill text-warning me-1"></i>
                                <span class="fw-bold text-dark">{{ $item['rating'] ?? 4.9 }}</span>
                                <span class="text-muted">({{ $item['reviews_count'] ?? 14 }})</span>
                                @if(!empty($item['sold_count']))
                                    <span class="text-muted ms-1 d-none d-sm-inline">• {{ $item['sold_count'] }}</span>
                                @endif
                            </div>

                            <h5 class="product-title" title="{{ $item['name'] }}" onclick="openProductDetailModal('{{ $item['id'] }}')" role="button" style="cursor: pointer;">
                                {{ $item['name'] }}
                            </h5>

                            <div class="mt-auto pt-2.5 border-top d-flex align-items-center justify-content-between gap-1">
                                <div>
                                    <div class="product-price-box">
                                        <span class="product-price-currency">Rp</span>
                                        <span class="product-price-number">{{ number_format($item['price'], 0, ',', '.') }}</span>
                                    </div>
                                    @if(!empty($item['original_price']) && $item['original_price'] > $item['price'])
                                        <div class="d-flex align-items-center gap-1">
                                            <del class="text-muted" style="font-size: 0.68rem;">Rp {{ number_format($item['original_price'], 0, ',', '.') }}</del>
                                        </div>
                                    @else
                                        <small class="text-muted" style="font-size: 0.68rem;">/ {{ $item['unit'] }}</small>
                                    @endif
                                </div>
                                <button type="button" class="btn-add-cart" onclick="addToCart('{{ $item['id'] }}', '{{ addslashes($item['name']) }}', {{ $item['price'] }}, '{{ $item['unit'] }}', '{{ $item['icon'] }}')" aria-label="Tambah {{ $item['name'] }} ke keranjang">
                                    <i class="bi bi-cart-plus me-1"></i>
                                    <span class="d-none d-sm-inline">+</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

    </div>
</section>

<!-- 6. BESTSELLERS IN SNACKS & MINUMAN (5-COLUMN AISLE) -->
<section id="snack-section" class="py-4 bg-white border-bottom">
    <div class="container px-3 px-lg-4">
        
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="fw-bold text-dark mb-0 fs-5">Bestsellers in Snacks & Minuman Segar</h3>
                <small class="text-secondary" style="font-size: 0.78rem;">Kopi khas Bandung, biskuit camilan kerja, teh celup, dan olahan produk anggota</small>
            </div>
            <a href="#alur-pesan" class="text-primary text-decoration-none fw-semibold small d-inline-flex align-items-center gap-1">
                <span>Lihat Semua</span>
                <i class="bi bi-chevron-right" style="font-size: 0.75rem;"></i>
            </a>
        </div>

        <div class="row row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-5 g-3 storefront-grid">
            @foreach($snackBeverages as $item)
                <div class="col product-item-col" data-name="{{ strtolower($item['name']) }}" data-category="{{ strtolower($item['category']) }}">
                    <div class="product-card">
                        <!-- Thumbnail Box with Real Image & Fallback -->
                        <div class="product-thumbnail-box" onclick="openProductDetailModal('{{ $item['id'] }}')" role="button" tabindex="0" title="Klik untuk lihat detail {{ $item['name'] }}" style="cursor: pointer;">
                            @if(!empty($item['image']))
                                <img src="{{ $item['image'] }}" alt="{{ $item['name'] }}" class="product-thumb-img" loading="lazy" onerror="this.classList.add('d-none'); this.nextElementSibling.classList.remove('d-none');">
                                <div class="product-icon-fallback d-none w-100 h-100 d-flex align-items-center justify-content-center">
                                    <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                                </div>
                            @else
                                <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                            @endif

                            @if(!empty($item['badge']))
                                <span class="position-absolute top-0 start-0 m-2 badge bg-primary text-white fw-semibold rounded-1" style="font-size: 0.65rem;">
                                    {{ $item['badge'] }}
                                </span>
                            @endif
                        </div>

                        <div class="d-flex flex-column flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="text-muted text-uppercase fw-bold text-truncate" style="font-size: 0.68rem; letter-spacing: 0.03em;">
                                    {{ $item['category'] }}
                                </small>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-1" style="font-size: 0.65rem;">
                                    Stok {{ $item['available_stock'] }}
                                </span>
                            </div>

                            <div class="product-rating-bar">
                                <i class="bi bi-star-fill text-warning me-1"></i>
                                <span class="fw-bold text-dark">{{ $item['rating'] ?? 4.9 }}</span>
                                <span class="text-muted">({{ $item['reviews_count'] ?? 32 }})</span>
                                @if(!empty($item['sold_count']))
                                    <span class="text-muted ms-1 d-none d-sm-inline">• {{ $item['sold_count'] }}</span>
                                @endif
                            </div>

                            <h5 class="product-title" title="{{ $item['name'] }}" onclick="openProductDetailModal('{{ $item['id'] }}')" role="button" style="cursor: pointer;">
                                {{ $item['name'] }}
                            </h5>

                            <div class="mt-auto pt-2.5 border-top d-flex align-items-center justify-content-between gap-1">
                                <div>
                                    <div class="product-price-box">
                                        <span class="product-price-currency">Rp</span>
                                        <span class="product-price-number">{{ number_format($item['price'], 0, ',', '.') }}</span>
                                    </div>
                                    @if(!empty($item['original_price']) && $item['original_price'] > $item['price'])
                                        <div class="d-flex align-items-center gap-1">
                                            <del class="text-muted" style="font-size: 0.68rem;">Rp {{ number_format($item['original_price'], 0, ',', '.') }}</del>
                                        </div>
                                    @else
                                        <small class="text-muted" style="font-size: 0.68rem;">/ {{ $item['unit'] }}</small>
                                    @endif
                                </div>
                                <button type="button" class="btn-add-cart" onclick="addToCart('{{ $item['id'] }}', '{{ addslashes($item['name']) }}', {{ $item['price'] }}, '{{ $item['unit'] }}', '{{ $item['icon'] }}')" aria-label="Tambah {{ $item['name'] }} ke keranjang">
                                    <i class="bi bi-cart-plus me-1"></i>
                                    <span class="d-none d-sm-inline">+</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

    </div>
</section>
