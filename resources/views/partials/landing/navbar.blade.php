{{-- ==============================================================================
   navbar.blade.php - Two-Tier E-Commerce Header (Inspirasi e-mox Layout)
   - Tier 1: Logo, Search Bar Live Filter, Gerai Info, Cart Button, Login
   - Tier 2: Sub-nav Baris Kategori & Navigasi Cepat
   ============================================================================== --}}
<header class="sticky-top storefront-header">
    <!-- TIER 1: MAIN STOREFRONT NAVBAR -->
    <nav class="navbar navbar-expand-lg bg-white border-bottom py-2 position-relative" style="z-index: 1045;">
        <div class="container px-3 px-lg-4 d-flex align-items-center justify-content-between gap-2 gap-lg-3">
            
            <!-- Brand Logo -->
            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-dark fs-5 flex-shrink-0 me-2 me-xl-3" href="{{ url('/') }}">
                <div class="bg-primary text-white rounded-3 p-1.5 d-flex align-items-center justify-content-center shadow-sm" style="width: 36px; height: 36px;">
                    <i class="bi bi-intersect fs-6" aria-hidden="true"></i>
                </div>
                <div class="d-flex flex-column">
                    <span class="lh-1 fw-extrabold text-primary">{{ config('koperasi.brand_name', 'KoperasiKu') }}</span>
                    <span class="text-muted fw-normal" style="font-size: 0.65rem; letter-spacing: 0.04em;">KOPEG POLMAN BANDUNG</span>
                </div>
            </a>

            <!-- Center Search Bar (Desktop) -->
            <div class="d-none d-lg-flex flex-grow-1 justify-content-center px-3 px-xl-4 min-w-0 position-relative">
                <div class="storefront-search-bar position-relative" style="max-width: 640px; width: 100%;">
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
                <button class="btn btn-outline-dark btn-sm position-relative p-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#memberProfileDrawer" aria-controls="memberProfileDrawer" title="Menu Akun">
                    <i class="bi bi-person-circle fs-6" aria-hidden="true"></i>
                    @if(!empty($landingShowCreditWidget) && !empty($receivableStatus['has_debt']))
                        <span class="position-absolute top-0 start-100 translate-middle p-1 bg-warning border border-light rounded-circle">
                            <span class="visually-hidden">Ada Tagihan Aktif</span>
                        </span>
                    @endif
                </button>
                @if(!empty($landingShowCart))
                    <button class="btn btn-outline-primary btn-sm position-relative p-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#cartDrawer" aria-controls="cartDrawer" title="Buka Keranjang">
                        <i class="bi bi-cart3 fs-6" aria-hidden="true"></i>
                        <span id="mobileCartBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" style="font-size: 0.65rem;">
                            0
                        </span>
                    </button>
                @endif
                <button class="navbar-toggler border-0 shadow-none p-1" type="button" data-bs-toggle="collapse" data-bs-target="#mobileSubnav" aria-controls="mobileSubnav" aria-expanded="false" aria-label="Buka navigasi">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>

            <!-- Right Actions (Desktop) -->
            <div class="d-none d-lg-flex align-items-center gap-2 gap-xl-3 flex-shrink-0">

                @if(!empty($landingShowCart))
                    <!-- Shopping Cart Button with Dynamic Badge -->
                    <button class="btn btn-outline-primary btn-sm rounded-pill px-3 py-2 fw-semibold d-inline-flex align-items-center gap-2 position-relative text-nowrap" type="button" data-bs-toggle="offcanvas" data-bs-target="#cartDrawer" aria-controls="cartDrawer">
                        <i class="bi bi-cart3 fs-6" aria-hidden="true"></i>
                        <span>Keranjang</span>
                        <span id="desktopCartBadge" class="badge rounded-pill bg-danger d-none ms-1">
                            0
                        </span>
                    </button>
                @endif

                @if(!empty($isLoggedIn) && !empty($member))
                    <!-- Profile Trigger Button -->
                    <div class="dropdown">
                        <button class="btn bg-white border text-dark rounded-pill px-3 py-2 d-flex align-items-center gap-2 dropdown-toggle shadow-xs text-nowrap" type="button" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Menu Akun ({{ $landingRoleLabel ?? 'Anggota' }})" style="--bs-btn-bg:#ffffff;--bs-btn-hover-bg:#f8fafc;--bs-btn-active-bg:#f1f5f9;--bs-btn-border-color:#dee2e6;--bs-btn-hover-border-color:#cbd5e1;">
                            <span class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 22px; height: 22px; font-size: 0.7rem;">
                                <i class="bi bi-person-fill" aria-hidden="true"></i>
                            </span>
                            <span class="fw-semibold text-dark small text-nowrap">{{ $member['name'] }}</span>
                            @if(!empty($landingShowCreditWidget) && !empty($receivableStatus['has_debt']))
                                <span class="badge bg-warning text-dark rounded-pill px-2 py-0.5 fw-bold" style="font-size: 0.65rem;" title="Ada Tagihan Aktif">
                                    Tagihan: {{ $receivableStatus['days_remaining'] ?? 3 }}h
                                </span>
                            @endif
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border mt-2" aria-labelledby="userMenuDropdown" style="z-index: 1080; min-width: 220px;">
                            @if(!empty($landingHasCashierRole))
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="{{ route('cashier.pos') }}">
                                        <i class="bi bi-upc-scan text-success"></i>
                                        <span>Portal Kasir</span>
                                    </a>
                                </li>
                            @endif
                            @if(!empty($landingHasManagerRole))
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="{{ route('manager.dashboard') }}">
                                        <i class="bi bi-speedometer2 text-success"></i>
                                        <span>Portal Manajer</span>
                                    </a>
                                </li>
                            @endif
                            @if(!empty($landingIsMemberRole))
                                <li>
                                    <button class="dropdown-item d-flex align-items-center gap-2 py-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#memberProfileDrawer">
                                        <i class="bi bi-person-vcard text-primary"></i>
                                        <span>{{ !empty($landingShowCreditWidget) ? 'Profil & Tagihan Saya' : 'Profil Akun Saya' }}</span>
                                    </button>
                                </li>
                            @endif
                            <li><hr class="dropdown-divider my-1"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="dropdown-item text-danger">Keluar (Logout)</button></form>
                            </li>
                        </ul>
                    </div>
                @else
                    <!-- Tombol Masuk Akun untuk Pengunjung / Guest -->
                    <a href="{{ route('login') }}" class="btn btn-primary btn-sm rounded-pill px-3 py-2 fw-semibold d-inline-flex align-items-center gap-2 shadow-xs text-nowrap">
                        <i class="bi bi-box-arrow-in-right fs-6"></i>
                        <span>Masuk Akun</span>
                    </a>
                @endif
            </div>

        </div>
    </nav>

    <!-- TIER 2: SUB-NAV CATEGORY STRIP (Desktop & Collapsible Mobile) -->
    <div class="storefront-subnav position-relative" style="z-index: 1020;">
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
                        @if(!empty($landingHasCashierRole))
                            <a href="{{ route('cashier.pos') }}" class="subnav-link text-success fw-bold">
                                <i class="bi bi-upc-scan me-1"></i>
                                <span>Portal Kasir</span>
                            </a>
                        @endif
                        @if(!empty($landingHasManagerRole))
                            <a href="{{ route('manager.dashboard') }}" class="subnav-link text-success fw-bold">
                                <i class="bi bi-speedometer2 me-1"></i>
                                <span>Portal Manajer</span>
                            </a>
                        @endif
                        @if(!empty($landingIsMemberRole))
                            <button type="button" class="btn btn-link subnav-link text-start text-primary fw-bold p-0 border-0 text-decoration-none" data-bs-toggle="offcanvas" data-bs-target="#memberProfileDrawer">
                                <i class="bi bi-person-vcard text-primary me-1"></i>
                                <span>{{ !empty($landingShowCreditWidget) ? 'Profil & Tagihan' : 'Profil Akun Saya' }} ({{ $member['name'] }})</span>
                                @if(!empty($landingShowCreditWidget) && !empty($receivableStatus['has_debt']))
                                    <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">{{ !empty($receivableStatus['is_overdue']) ? 'Lewat tempo' : 'Ada tagihan' }}</span>
                                @endif
                            </button>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-link subnav-link text-danger text-start w-100 p-0 border-0 text-decoration-none">
                                <i class="bi bi-box-arrow-right me-1"></i>
                                <span>Keluar (Logout)</span>
                            </button>
                        </form>
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
            <div class="d-none d-lg-flex align-items-center justify-content-between gap-3 py-1">
                <div class="d-flex align-items-center gap-1 overflow-x-auto flex-grow-1 min-w-0 text-nowrap">
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

                <div class="d-flex align-items-center gap-2 flex-shrink-0 text-nowrap">
                    <span class="text-secondary small d-inline-flex align-items-center gap-1.5" style="font-size: 0.74rem;">
                        <i class="bi bi-shield-check text-success"></i>
                        <span>Koperasi Konsumen Resmi POLMAN Bandung</span>
                    </span>
                </div>
            </div>

        </div>
    </div>
</header>
