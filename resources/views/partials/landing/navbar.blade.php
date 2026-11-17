{{-- ==============================================================================
   navbar.blade.php - Two-Tier E-Commerce Header (Inspirasi e-mox Layout)
   - Tier 1: Logo, Search Bar Live Filter, Gerai Info, Cart Button, Login
   - Tier 2: Sub-nav Baris Kategori & Navigasi Cepat
   ============================================================================== --}}
<header class="sticky-top storefront-header">
    <!-- TIER 1: MAIN STOREFRONT NAVBAR -->
    <nav class="navbar navbar-expand-lg bg-white border-bottom py-2.5">
        <div class="container px-3 px-lg-4">
            
            <!-- Brand Logo -->
            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-dark fs-5 me-3 me-xl-4" href="{{ url('/') }}">
                <div class="bg-primary text-white rounded-3 p-1.5 d-flex align-items-center justify-content-center shadow-sm" style="width: 36px; height: 36px;">
                    <i class="bi bi-intersect fs-6" aria-hidden="true"></i>
                </div>
                <div class="d-flex flex-column">
                    <span class="lh-1 fw-extrabold text-primary">{{ config('koperasi.brand_name', 'KoperasiKu') }}</span>
                    <span class="text-muted fw-normal" style="font-size: 0.65rem; letter-spacing: 0.04em;">KOPEG POLMAN BANDUNG</span>
                </div>
            </a>

            <!-- Center Search Bar (Desktop) -->
            <div class="d-none d-lg-flex flex-grow-1 justify-content-center px-3 position-relative">
                <div class="storefront-search-bar position-relative" style="max-width: 620px; width: 100%;">
                    <i class="bi bi-search text-muted me-2" aria-hidden="true"></i>
                    <input type="text" id="storefrontSearchInput" oninput="filterStorefrontSearch(this.value)" onfocus="openSearchSuggestions()" onblur="closeSearchSuggestions()" placeholder="Cari sembako, beras ramos, minyak goreng, deterjen, biskuit..." aria-label="Cari produk di etalase KoperasiKu" autocomplete="off">
                    <button type="button" class="btn btn-primary btn-sm px-3 rounded-pill fw-semibold text-nowrap" onclick="scrollToFirstProduct()">
                        Cari
                    </button>

                    <!-- Dropdown Rekomendasi Pencarian Instan (Tokopedia Style) -->
                    <div id="searchSuggestionDropdown" class="search-suggestion-dropdown shadow-lg d-none">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-secondary fw-bold small text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">
                                <i class="bi bi-fire text-danger me-1"></i> Pencarian Populer Anggota
                            </span>
                            <span class="badge bg-light text-muted border" style="font-size: 0.65rem;">Klik untuk filter</span>
                        </div>
                        <div class="d-flex flex-wrap gap-1.5 mb-2.5">
                            <button type="button" class="search-chip" onmousedown="applySearchChip('Beras Ramos')">
                                <i class="bi bi-search me-1 text-muted"></i> Beras Ramos 5kg
                            </button>
                            <button type="button" class="search-chip" onmousedown="applySearchChip('Minyak Goreng')">
                                <i class="bi bi-search me-1 text-muted"></i> Minyak Goreng 2L
                            </button>
                            <button type="button" class="search-chip" onmousedown="applySearchChip('Gula Pasir')">
                                <i class="bi bi-search me-1 text-muted"></i> Gula Pasir
                            </button>
                            <button type="button" class="search-chip" onmousedown="applySearchChip('Telur Ayam')">
                                <i class="bi bi-search me-1 text-muted"></i> Telur Ayam
                            </button>
                            <button type="button" class="search-chip" onmousedown="applySearchChip('Susu UHT')">
                                <i class="bi bi-search me-1 text-muted"></i> Susu UHT
                            </button>
                        </div>
                        <div class="p-2 rounded bg-light border d-flex justify-content-between align-items-center">
                            <small class="text-muted" style="font-size: 0.72rem;">
                                <i class="bi bi-geo-alt-fill text-danger me-1"></i> Penyerahan pesanan di Gerai Kanayakan A11
                            </small>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.68rem;">Pickup Bebas Ongkir</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobile Actions (Cart, Profile & Toggle) -->
            <div class="d-flex align-items-center gap-1.5 d-lg-none">
                <button class="btn btn-outline-dark btn-sm position-relative p-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#memberProfileDrawer" aria-controls="memberProfileDrawer" title="Profil Anggota & Cek Hutang">
                    <i class="bi bi-person-circle fs-6" aria-hidden="true"></i>
                    @if(!empty($receivableStatus['has_debt']))
                        <span class="position-absolute top-0 start-100 translate-middle p-1 bg-warning border border-light rounded-circle">
                            <span class="visually-hidden">Ada Tagihan Aktif</span>
                        </span>
                    @endif
                </button>
                <button class="btn btn-outline-primary btn-sm position-relative p-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#cartDrawer" aria-controls="cartDrawer" title="Buka Keranjang">
                    <i class="bi bi-cart3 fs-6" aria-hidden="true"></i>
                    <span id="mobileCartBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" style="font-size: 0.65rem;">
                        0
                    </span>
                </button>
                <button class="navbar-toggler border-0 shadow-none p-1" type="button" data-bs-toggle="collapse" data-bs-target="#mobileSubnav" aria-controls="mobileSubnav" aria-expanded="false" aria-label="Buka navigasi">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>

            <!-- Right Actions (Desktop) -->
            <div class="d-none d-lg-flex align-items-center gap-2.5">
                
                <!-- Gerai Pickup Info Pill -->
                <div class="storefront-location-pill" title="Lokasi & Jam Pengambilan Pesanan">
                    <i class="bi bi-geo-alt-fill text-danger fs-6" aria-hidden="true"></i>
                    <div class="d-flex flex-column text-start" style="line-height: 1.15;">
                        <span class="fw-bold text-dark" style="font-size: 0.76rem;">Gerai Kanayakan A11</span>
                        <span class="text-secondary" style="font-size: 0.68rem;">Pickup: 07.00 - 18.00 WIB</span>
                    </div>
                </div>

                <!-- Shopping Cart Button with Dynamic Badge -->
                <button class="btn btn-outline-primary btn-sm px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-2 position-relative" type="button" data-bs-toggle="offcanvas" data-bs-target="#cartDrawer" aria-controls="cartDrawer">
                    <i class="bi bi-cart3 fs-6" aria-hidden="true"></i>
                    <span>Keranjang</span>
                    <span id="desktopCartBadge" class="badge rounded-pill bg-danger d-none ms-1">
                        0
                    </span>
                </button>

                @if(!empty($isLoggedIn) && !empty($member))
                    <!-- Profile & Hutang Trigger Button (Anggota yang Sudah Login) -->
                    <div class="dropdown">
                        <button class="btn btn-outline-dark btn-sm px-2.5 py-1.5 fw-semibold d-inline-flex align-items-center gap-2 shadow-xs dropdown-toggle" type="button" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Menu Akun Anggota">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 26px; height: 26px; font-size: 0.75rem;">
                                <i class="bi bi-person-fill"></i>
                            </div>
                            <div class="d-flex flex-column text-start lh-1">
                                <span class="fw-bold text-dark" style="font-size: 0.78rem;">{{ $member['name'] }}</span>
                                <span class="text-muted" style="font-size: 0.65rem;">{{ $member['id'] }} &bull; Anggota</span>
                            </div>
                            @if(!empty($receivableStatus['has_debt']))
                                <span class="badge bg-warning text-dark rounded-pill px-2 py-0.5 fw-bold" style="font-size: 0.65rem;" title="Ada Tagihan Aktif">
                                    Tagihan: {{ $receivableStatus['days_remaining'] ?? 3 }}h
                                </span>
                            @endif
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="userMenuDropdown">
                            <li>
                                <button class="dropdown-item d-flex align-items-center gap-2 py-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#memberProfileDrawer">
                                    <i class="bi bi-person-vcard text-primary"></i>
                                    <span>Profil & Tagihan Saya</span>
                                </button>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2 text-danger py-2" href="{{ route('logout') }}">
                                    <i class="bi bi-box-arrow-right"></i>
                                    <span>Keluar (Logout)</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                @else
                    <!-- Tombol Masuk Akun untuk Pengunjung / Guest -->
                    <a href="{{ route('login') }}" class="btn btn-primary btn-sm px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 shadow-xs">
                        <i class="bi bi-box-arrow-in-right fs-6"></i>
                        <span>Masuk Akun</span>
                    </a>
                @endif
            </div>

        </div>
    </nav>

    <!-- TIER 2: SUB-NAV CATEGORY STRIP (Desktop & Collapsible Mobile) -->
    <div class="storefront-subnav">
        <div class="container px-3 px-lg-4">
            
            <!-- Mobile Menu Dropdown -->
            <div class="collapse d-lg-none py-2" id="mobileSubnav">
                <div class="mb-2 position-relative">
                    <input type="text" id="mobileStorefrontSearchInput" class="form-control form-control-sm" placeholder="Cari sembako, minyak, beras..." oninput="filterStorefrontSearch(this.value)" onfocus="openMobileSearchSuggestions()" onblur="closeMobileSearchSuggestions()" autocomplete="off">
                    <!-- Dropdown Rekomendasi Mobile -->
                    <div id="mobileSearchSuggestionDropdown" class="search-suggestion-dropdown shadow-lg d-none mt-1">
                        <div class="text-secondary fw-bold small text-uppercase mb-2" style="font-size: 0.72rem;">
                            <i class="bi bi-fire text-danger me-1"></i> Pencarian Populer:
                        </div>
                        <div class="d-flex flex-wrap gap-1.5">
                            <button type="button" class="search-chip" onmousedown="applySearchChip('Beras Ramos')">Beras Ramos 5kg</button>
                            <button type="button" class="search-chip" onmousedown="applySearchChip('Minyak Goreng')">Minyak Goreng 2L</button>
                            <button type="button" class="search-chip" onmousedown="applySearchChip('Gula Pasir')">Gula Pasir</button>
                            <button type="button" class="search-chip" onmousedown="applySearchChip('Telur Ayam')">Telur Ayam</button>
                            <button type="button" class="search-chip" onmousedown="applySearchChip('Susu UHT')">Susu UHT</button>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-column gap-1">
                    @if(!empty($isLoggedIn) && !empty($member))
                        <button type="button" class="btn btn-link subnav-link text-start text-primary fw-bold p-0 border-0 text-decoration-none" data-bs-toggle="offcanvas" data-bs-target="#memberProfileDrawer">
                            <i class="bi bi-person-vcard text-primary me-1"></i>
                            <span>Profil & Tagihan ({{ $member['name'] }})</span>
                            @if(!empty($receivableStatus['has_debt']))
                                <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">Tempo 3h</span>
                            @endif
                        </button>
                        <a href="{{ route('logout') }}" class="subnav-link text-danger">
                            <i class="bi bi-box-arrow-right me-1"></i>
                            <span>Keluar (Logout)</span>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="subnav-link text-primary fw-bold">
                            <i class="bi bi-box-arrow-in-right me-1"></i>
                            <span>Masuk Akun Anggota</span>
                        </a>
                    @endif
                    <a href="#allProductsSection" class="subnav-link" onclick="showAllProducts(event)">Semua Produk Koperasi</a>
                    <a href="#allProductsSection" class="subnav-link" onclick="filterStorefrontCategory('Sembako & Beras')">Sembako & Beras</a>
                    <a href="#allProductsSection" class="subnav-link" onclick="filterStorefrontCategory('Minyak & Bumbu')">Minyak & Bumbu</a>
                    <a href="#allProductsSection" class="subnav-link" onclick="filterStorefrontCategory('Sabun & Cuci')">Sabun & Kebersihan</a>
                    <a href="#allProductsSection" class="subnav-link" onclick="filterStorefrontCategory('Snack & Biskuit')">Snack & Minuman</a>
                    <a href="#alur-pesan" class="subnav-link">Alur Pickup (Bebas Ongkir)</a>
                    <a href="#faq" class="subnav-link">FAQ & Bantuan</a>
                </div>
            </div>

            <!-- Desktop Horizontal Category Quick Links -->
            <div class="d-none d-lg-flex align-items-center justify-content-between overflow-x-auto py-1">
                <div class="d-flex align-items-center gap-1">
                    <a href="#allProductsSection" class="subnav-link active" onclick="showAllProducts(event)">
                        <i class="bi bi-grid-fill text-primary" aria-hidden="true"></i>
                        <span>Semua Produk</span>
                    </a>
                    <a href="#allProductsSection" class="subnav-link" onclick="filterStorefrontCategory('Sembako & Beras')">
                        <span>Sembako & Beras</span>
                    </a>
                    <a href="#allProductsSection" class="subnav-link" onclick="filterStorefrontCategory('Minyak & Bumbu')">
                        <span>Minyak & Bumbu</span>
                    </a>
                    <a href="#allProductsSection" class="subnav-link" onclick="filterStorefrontCategory('Sabun & Cuci')">
                        <span>Kebersihan Rumah</span>
                    </a>
                    <a href="#allProductsSection" class="subnav-link" onclick="filterStorefrontCategory('Snack & Biskuit')">
                        <span>Snack & Biskuit</span>
                    </a>
                    <a href="#alur-pesan" class="subnav-link">
                        <i class="bi bi-bag-check text-success" aria-hidden="true"></i>
                        <span>Alur Pickup</span>
                    </a>
                    <a href="#faq" class="subnav-link">
                        <span>FAQ Belanja</span>
                    </a>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <span class="text-secondary small d-inline-flex align-items-center gap-1.5" style="font-size: 0.74rem;">
                        <i class="bi bi-shield-check text-success"></i>
                        <span>Koperasi Konsumen Resmi POLMAN Bandung</span>
                    </span>
                </div>
            </div>

        </div>
    </div>
</header>
