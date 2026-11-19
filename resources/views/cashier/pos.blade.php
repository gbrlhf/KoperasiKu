@extends('layouts.app')

@section('title', 'Portal Kasir & Monitoring Stok')

@push('styles')
<style>
    /* Styling modul scanner barcode html5-qrcode */
    #qr-reader {
        border: none !important;
        width: 100% !important;
    }
    #qr-reader video {
        width: 100% !important;
        height: auto !important;
        max-height: 280px !important;
        object-fit: cover !important;
        border-radius: 8px !important;
    }
    #qr-reader__scan_region {
        background: transparent !important;
        position: relative;
    }
    #qr-reader__dashboard {
        padding: 6px 0 !important;
    }
    #qr-reader__dashboard_section_csr button,
    #qr-reader__dashboard_section_swaplink button {
        background-color: #0d6efd !important;
        color: #ffffff !important;
        border: none !important;
        border-radius: 6px !important;
        padding: 4px 10px !important;
        font-size: 0.78rem !important;
        font-weight: 600 !important;
    }

    /* Animasi Laser Barcode Scanner */
    .scanner-viewport-box {
        position: relative;
        background-color: #090d16;
        border-radius: 10px;
        overflow: hidden;
        border: 2px solid #1e293b;
        min-height: 240px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .scanner-laser-line {
        position: absolute;
        left: 6%;
        right: 6%;
        height: 2.5px;
        background: linear-gradient(90deg, rgba(239, 68, 68, 0) 0%, #ef4444 50%, rgba(239, 68, 68, 0) 100%);
        box-shadow: 0 0 10px #ef4444, 0 0 20px #dc2626;
        animation: laserScanAnim 1.8s infinite ease-in-out;
        z-index: 15;
        pointer-events: none;
    }
    @keyframes laserScanAnim {
        0% { top: 15%; opacity: 0.3; }
        50% { top: 85%; opacity: 1; }
        100% { top: 15%; opacity: 0.3; }
    }

    /* Reticle Garis Bidik Barcode Sembako */
    .scanner-reticle {
        position: absolute;
        inset: 12% 8%;
        border: 2px dashed rgba(255, 255, 255, 0.45);
        border-radius: 8px;
        pointer-events: none;
        z-index: 10;
        box-shadow: inset 0 0 0 1000px rgba(0, 0, 0, 0.22);
    }
    .scanner-reticle::before,
    .scanner-reticle::after {
        content: '';
        position: absolute;
        width: 18px;
        height: 18px;
        border-color: #38bdf8;
        border-style: solid;
    }
    .scanner-reticle::before {
        top: -2px; left: -2px;
        border-width: 3px 0 0 3px;
    }
    .scanner-reticle::after {
        bottom: -2px; right: -2px;
        border-width: 0 3px 3px 0;
    }

    /* Flash Sukses Scan */
    .scanner-flash-overlay {
        position: absolute;
        inset: 0;
        background: rgba(34, 197, 94, 0.45);
        opacity: 0;
        pointer-events: none;
        z-index: 25;
        transition: opacity 0.25s ease-out;
    }
    .scanner-flash-overlay.active {
        opacity: 1;
        transition: none;
    }

    /* GoPay-Style Modern Scan Feedback Bottom Sheet (Frontend-Design Crafted) */
    .gopay-scan-sheet {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 1095;
        max-width: 440px;
        width: calc(100% - 32px);
        margin: 0 auto;
        animation: gopaySlideIn 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s ease;
        pointer-events: auto;
    }
    @media (max-width: 767.98px) {
        .gopay-scan-sheet {
            bottom: 0;
            left: 0;
            right: 0;
            max-width: 100%;
            width: 100%;
        }
    }
    @keyframes gopaySlideIn {
        from {
            transform: translateY(100%);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    .gopay-sheet-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 20px 35px -10px rgba(15, 23, 42, 0.22), 0 0 1px 1px rgba(15, 23, 42, 0.05);
        overflow: hidden;
        position: relative;
    }
    @media (max-width: 767.98px) {
        .gopay-sheet-card {
            border-radius: 20px 20px 0 0;
            border-bottom: none;
        }
    }
    .gopay-drag-handle {
        width: 36px;
        height: 4px;
        background: #cbd5e1;
        border-radius: 999px;
        margin: 10px auto 2px auto;
    }
    .gopay-icon-badge {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
        animation: gopayBadgePop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    @keyframes gopayBadgePop {
        0% { transform: scale(0.6); opacity: 0; }
        100% { transform: scale(1); opacity: 1; }
    }
    .gopay-progress-bar {
        position: absolute;
        bottom: 0;
        left: 0;
        height: 3px;
        background: #10b981;
        width: 100%;
        transform-origin: left;
    }
    .gopay-progress-bar.active {
        animation: gopayProgressShrink 5s linear forwards;
    }
    @keyframes gopayProgressShrink {
        from { transform: scaleX(1); }
        to { transform: scaleX(0); }
    }
</style>
@endpush

@section('header')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="fw-bold mb-0 text-dark">Portal Kasir & Operasional Gerai</h4>
            <span class="badge bg-success text-white rounded-pill px-2.5 py-1 small">
                <i class="bi bi-shop me-1"></i> Gerai Kanayakan Dago
            </span>
        </div>
        <p class="text-secondary small mb-0">Kelola stok barang, transaksi POS kasir langsung, dan verifikasi antrean pickup pesanan anggota.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-white text-secondary border px-3 py-2 small shadow-xs">
            <i class="bi bi-clock me-1 text-success"></i> Buka Gerai: 07.00 - 18.00 WIB
        </span>
    </div>
</div>
@endsection

@section('content')

<!-- RINGKASAN STATUS STOK GERAI (Tokopedia Seller Stat Cards) -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="tokped-stat-card h-100 d-flex align-items-start justify-content-between gap-2">
            <div class="min-w-0">
                <small class="text-muted fw-semibold d-block mb-1" style="font-size: 0.74rem;">Total SKU Barang</small>
                <h4 class="fw-bold text-dark mb-0 fs-5 tokped-price">{{ $stockSummary['total_sku'] }} Item</h4>
                <small class="text-muted d-block text-truncate mt-1" style="font-size: 0.7rem;">Komoditas aktif gerai</small>
            </div>
            <span class="tokped-stat-icon bg-light text-secondary border">
                <i class="bi bi-box-seam"></i>
            </span>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="tokped-stat-card h-100 d-flex align-items-start justify-content-between gap-2">
            <div class="min-w-0">
                <small class="text-muted fw-semibold d-block mb-1" style="font-size: 0.74rem;">Stok Aman</small>
                <h4 class="fw-bold text-success mb-0 fs-5 tokped-price">{{ $stockSummary['safe_stock_count'] }} SKU</h4>
                <small class="text-success d-block text-truncate mt-1" style="font-size: 0.7rem;"><i class="bi bi-check-circle-fill me-1"></i>Pasokan cukup</small>
            </div>
            <span class="tokped-stat-icon" style="background: #E6F7EF; color: #00AA5B;">
                <i class="bi bi-check2-all"></i>
            </span>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="tokped-stat-card h-100 d-flex align-items-start justify-content-between gap-2">
            <div class="min-w-0">
                <small class="text-muted fw-semibold d-block mb-1" style="font-size: 0.74rem;">Stok Menipis</small>
                <h4 class="fw-bold text-danger mb-0 fs-5 tokped-price">{{ $stockSummary['low_stock_count'] }} SKU</h4>
                <small class="text-danger d-block text-truncate mt-1" style="font-size: 0.7rem;"><i class="bi bi-exclamation-triangle-fill me-1"></i>Perlu restock</small>
            </div>
            <span class="tokped-stat-icon" style="background: #FFEAEA; color: #EF144A;">
                <i class="bi bi-exclamation-circle"></i>
            </span>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="tokped-stat-card h-100 d-flex align-items-start justify-content-between gap-2">
            <div class="min-w-0">
                <small class="text-muted fw-semibold d-block mb-1" style="font-size: 0.74rem;">Antrian Pickup</small>
                <h4 class="fw-bold text-dark mb-0 fs-5 tokped-price">{{ $stockSummary['total_reserved_items'] }} Barang</h4>
                <small class="text-primary d-block text-truncate mt-1" style="font-size: 0.7rem;"><i class="bi bi-bag-check-fill me-1"></i>Siap diserahkan</small>
            </div>
            <span class="tokped-stat-icon" style="background: #FFF5E6; color: #FA591D;">
                <i class="bi bi-bag-check"></i>
            </span>
        </div>
    </div>
</div>

@php
    $activeTab = 'pos';
    if (request()->routeIs('cashier.stok')) {
        $activeTab = 'stock';
    } elseif (request()->routeIs('cashier.antrian')) {
        $activeTab = 'pickup';
    } elseif (request()->routeIs('cashier.pos') || request()->routeIs('cashier.index')) {
        $activeTab = 'pos';
    }
    if (request()->has('tab')) {
        $activeTab = request()->get('tab');
    }
@endphp

<!-- TABS OPERASIONAL KASIR -->
<ul class="nav nav-pills mb-3 gap-2 border-bottom pb-3 overflow-x-auto flex-nowrap text-nowrap" id="cashierTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ $activeTab === 'pos' ? 'active' : '' }} fw-semibold d-inline-flex align-items-center gap-2" id="pos-tab" data-bs-toggle="pill" data-bs-target="#pos-pane" type="button" role="tab">
            <i class="bi bi-upc-scan"></i>
            <span>Point of Sale (POS) Kasir</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ $activeTab === 'pickup' ? 'active' : '' }} fw-semibold d-inline-flex align-items-center gap-2" id="pickup-tab" data-bs-toggle="pill" data-bs-target="#pickup-pane" type="button" role="tab">
            <i class="bi bi-qr-code-scan"></i>
            <span>Antrian Pickup Pesanan ({{ count($pickupQueue) }})</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ $activeTab === 'stock' ? 'active' : '' }} fw-semibold d-inline-flex align-items-center gap-2" id="stock-tab" data-bs-toggle="pill" data-bs-target="#stock-pane" type="button" role="tab">
            <i class="bi bi-boxes"></i>
            <span>Monitoring Stok Barang</span>
        </button>
    </li>
</ul>

<div class="tab-content" id="cashierTabContent">
    
    <!-- TAB 1: MONITORING STOK BARANG REAL-TIME -->
    <div class="tab-pane fade {{ $activeTab === 'stock' ? 'show active' : '' }}" id="stock-pane" role="tabpanel">
        <div class="card border-0 shadow-sm bg-white">
            <div class="card-header bg-white py-3 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h5 class="card-title fw-bold text-dark mb-0 fs-6">Master Persediaan & Stok Barang Gerai</h5>
                    <small class="text-muted" style="font-size: 0.75rem;">Stok tersedia = Stok fisik rak dikurangi barang ter-reservasi pesanan online</small>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button type="button" class="btn btn-outline-dark btn-sm d-inline-flex align-items-center gap-1.5" onclick="openTestBarcodeGalleryModal()" title="Buka lembar katalog barcode siap dipindai kamera">
                        <i class="bi bi-upc-scan text-primary"></i>
                        <span>Katalog Barcode & QR</span>
                    </button>
                    <div style="max-width: 260px;" class="w-100">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" id="stockSearchInput" class="form-control" placeholder="Cari SKU atau nama barang..." oninput="filterCashierStock(this.value)">
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                    <thead class="table-light">
                        <tr>
                            <th>SKU / Barcode</th>
                            <th>Nama Komoditas</th>
                            <th>Kategori</th>
                            <th class="text-center">Stok Fisik Rak</th>
                            <th class="text-center">Reservasi Pickup</th>
                            <th class="text-center">Stok Tersedia Kasir</th>
                            <th class="text-end">Harga Jual</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody id="stockTableBody">
                        @foreach($stockItems as $item)
                            <tr class="stock-item-row" data-name="{{ strtolower($item['name']) }}" data-sku="{{ strtolower($item['sku']) }}">
                                <td>
                                    <div class="d-flex align-items-center gap-1.5">
                                        <span class="fw-bold font-monospace text-dark">{{ $item['sku'] }}</span>
                                        <button type="button" class="btn btn-sm btn-link p-0 text-primary" onclick="showProductBarcodeModal(@js($item['sku']), @js($item['barcode']), @js($item['name']), {{ $item['sell_price'] }})" title="Lihat Barcode Fisik & QR">
                                            <i class="bi bi-upc-scan" style="font-size: 0.85rem;"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted d-block font-monospace" style="font-size: 0.72rem;">{{ $item['barcode'] }}</small>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark">{{ $item['name'] }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-secondary border">{{ $item['category'] }}</span>
                                </td>
                                <td class="text-center fw-bold">{{ $item['on_hand'] }} {{ $item['unit'] }}</td>
                                <td class="text-center text-primary fw-bold">{{ $item['reserved'] }} {{ $item['unit'] }}</td>
                                <td class="text-center">
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 fs-7 fw-bold">
                                        {{ $item['available'] }} {{ $item['unit'] }}
                                    </span>
                                </td>
                                <td class="text-end fw-bold text-dark">
                                    Rp {{ number_format($item['sell_price'], 0, ',', '.') }}
                                </td>
                                <td class="text-center">
                                    @if($item['status'] === 'SAFE')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5 small">
                                            Aman
                                        </span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0.5 small">
                                            Menipis
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        <tr id="stockEmptyRow" class="d-none">
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="bi bi-box-seam fs-3 d-block text-secondary opacity-50 mb-1"></i>
                                <span class="small">Komoditas sembako tidak ditemukan. Periksa kembali SKU atau nama barang.</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 2: POINT OF SALE (POS) TRANSAKSI KASIR -->
    <div class="tab-pane fade {{ $activeTab === 'pos' ? 'show active' : '' }}" id="pos-pane" role="tabpanel">
        <div class="row g-3">
            <!-- Left: POS Input & Item Selection -->
            <div class="col-12 col-lg-7">
                <div class="card border-0 shadow-sm bg-white p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold text-dark mb-0 small"><i class="bi bi-upc-scan text-primary me-1"></i> Scan Barcode & Pencarian SKU Sembako</h6>
                        <span class="badge bg-light text-secondary border" style="font-size: 0.68rem;">
                            <i class="bi bi-shield-check text-success me-0.5"></i> Mode Kasir Langsung
                        </span>
                    </div>

                    <div class="input-group mb-2">
                        <!-- Tombol Buka Kamera Scanner Nyata (html5-qrcode) -->
                        <button type="button" class="btn btn-outline-primary fw-semibold d-inline-flex align-items-center gap-1.5 px-3" onclick="openBarcodeScannerModal()" title="Buka Kamera Barcode Scanner">
                            <i class="bi bi-camera-fill text-primary"></i>
                            <span class="d-none d-sm-inline">Scan Kamera</span>
                        </button>
                        <input type="text" id="posBarcodeField" class="form-control" placeholder="Scan barcode atau ketik SKU (contoh: SEM-001 / 89999991001)..." onkeydown="if(event.key === 'Enter'){ submitManualBarcodeField(); }">
                        <button type="button" class="btn btn-primary fw-semibold px-3" onclick="submitManualBarcodeField()">
                            + Tambah
                        </button>
                    </div>
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-1 text-muted" style="font-size: 0.72rem;">
                        <span><i class="bi bi-info-circle me-1"></i>Tekan <strong>Enter</strong> atau klik <strong>Tambah</strong> setelah scan barcode.</span>
                        <span class="text-primary fw-semibold">Scanner Kamera mendukung HP & Webcam</span>
                    </div>
                </div>

                <!-- Katalog Cepat Kasir (Gaya Kartu Produk Tokopedia) -->
                <div class="card border-0 shadow-sm bg-white p-3">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3 pb-2 border-bottom">
                        <div>
                            <h6 class="fw-bold text-dark mb-0 small d-flex align-items-center gap-1.5">
                                <i class="bi bi-grid-fill text-success"></i>
                                <span>Katalog Cepat Sembako</span>
                            </h6>
                            <small class="text-muted" style="font-size: 0.72rem;">Klik produk untuk langsung masuk ke keranjang kasir</small>
                        </div>
                        <div class="input-group input-group-sm" style="max-width: 240px;">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" placeholder="Cari produk cepat..." oninput="const q=this.value.toLowerCase();document.querySelectorAll('.quick-pick-col').forEach(el=>el.classList.toggle('d-none', q && !el.dataset.search.includes(q)));">
                        </div>
                    </div>
                    <div class="row g-2.5">
                        @foreach($stockItems as $item)
                            <div class="col-6 col-sm-4 col-xl-3 quick-pick-col" data-search="{{ strtolower($item['name'] . ' ' . $item['sku'] . ' ' . $item['category']) }}">
                                <div class="tokped-product-card h-100 d-flex flex-column justify-content-between"
                                     onclick="posAddToCart(@js($item['name']), {{ $item['sell_price'] }}, @js($item['sku']))"
                                     onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();this.click();}"
                                     role="button" tabindex="0" title="Tambah {{ $item['name'] }}">
                                    <div>
                                        <div class="d-flex justify-content-between align-items-center mb-1.5">
                                            <span class="badge bg-light text-muted border font-monospace" style="font-size: 0.64rem;">{{ $item['sku'] }}</span>
                                            <span class="small fw-semibold {{ $item['available'] <= 10 ? 'text-danger' : 'text-muted' }}" style="font-size: 0.68rem;">Stok {{ $item['available'] }}</span>
                                        </div>
                                        <div class="fw-semibold text-dark mb-2" style="font-size: 0.8rem; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.15rem;">
                                            {{ $item['name'] }}
                                        </div>
                                    </div>
                                    <div class="pt-2 border-top d-flex justify-content-between align-items-center gap-1">
                                        <span class="fw-bold tokped-price" style="font-size: 0.84rem;">Rp{{ number_format($item['sell_price'], 0, ',', '.') }}</span>
                                        <span class="btn btn-outline-success btn-sm py-0 px-2 tokped-add-btn" style="min-height: 26px; font-size: 0.72rem; border-radius: 6px;">+</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Right: POS Cart & Checkout Receipt (Sticky) -->
            <div class="col-12 col-lg-5">
                <div class="card border-0 shadow-sm bg-white p-3 p-md-4 pos-receipt-sticky">
                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-2 d-flex justify-content-between align-items-center">
                        <span class="d-flex align-items-center gap-1.5">
                            <i class="bi bi-receipt text-primary"></i>
                            <span>Keranjang Meja Kasir</span>
                        </span>
                        <div class="d-flex align-items-center gap-1.5 flex-shrink-0">
                            <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.7rem;">Transaksi baru</span>
                            <button type="button" class="btn btn-outline-danger btn-sm pos-btn-compact" onclick="clearPosCart()" title="Kosongkan Keranjang Kasir">
                                Reset
                            </button>
                        </div>
                    </h6>

                    <!-- Dynamic Cart Item List -->
                    <div id="posCartList" class="mb-3" style="min-height: 120px; max-height: 280px; overflow-y: auto; scrollbar-width: thin;">
                        <!-- Injected via JavaScript renderPosCart() -->
                    </div>

                    <div class="border-top pt-2.5 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-secondary small fw-semibold">Total Tagihan Kasir:</span>
                            <span class="fs-4 fw-bold text-primary tokped-price" id="posTotalDisplay">Rp 0</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center text-muted" style="font-size: 0.72rem;">
                            <span id="posItemsCountDisplay">0 item</span>
                            <span>PPN 0% (Bebas Pajak Koperasi)</span>
                        </div>
                    </div>

                    <!-- Payment Method Selector -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary mb-1">Metode Bayar Pembeli:</label>
                        <select class="form-select form-select-sm mb-2 fw-semibold text-dark" id="posPaymentMethod" onchange="onPosPaymentMethodChanged(this.value)">
                            <option value="CASH">1. Tunai di Kasir (Cash)</option>
                            <option value="BANK_TRANSFER">2. Transfer Bank / QRIS Gerai</option>
                            <option value="CREDIT">3. Piutang Anggota (Tempo 7 Hari Bunga 0%)</option>
                        </select>

                        <!-- A. CASH PAYMENT CONTAINER -->
                        <div id="posCashContainer">
                            <div class="input-group input-group-sm mb-1.5">
                                <span class="input-group-text bg-light small">Diterima Rp</span>
                                <input type="number" class="form-control fw-bold font-monospace" id="posCashReceived" value="0" min="0" step="0.01" oninput="calculatePosChange(); validatePosTransaction()">
                            </div>
                            <div class="d-flex gap-1.5 flex-wrap mb-2">
                                <button type="button" class="btn btn-light border btn-sm pos-btn-compact text-secondary" onclick="setExactCash()">Uang Pas</button>
                                <button type="button" class="btn btn-light border btn-sm pos-btn-compact text-secondary" onclick="setCashAmount(50000)">Rp 50k</button>
                                <button type="button" class="btn btn-light border btn-sm pos-btn-compact text-secondary" onclick="setCashAmount(100000)">Rp 100k</button>
                                <button type="button" class="btn btn-light border btn-sm pos-btn-compact text-secondary" onclick="setCashAmount(150000)">Rp 150k</button>
                                <button type="button" class="btn btn-light border btn-sm pos-btn-compact text-secondary" onclick="setCashAmount(200000)">Rp 200k</button>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-2 rounded-2 bg-light border small">
                                <span class="text-secondary fw-semibold">Uang Kembalian:</span>
                                <span class="fw-bold text-success fs-6 font-monospace" id="posChangeDisplay">Rp 0</span>
                            </div>
                        </div>

                        <!-- B. BANK TRANSFER / QRIS CONTAINER -->
                        <div id="posTransferContainer" class="d-none">
                            <div class="mb-2">
                                <label class="form-label text-muted d-block mb-1" style="font-size: 0.72rem;">Nomor Referensi / Catatan Verifikasi Transfer:</label>
                                <input type="text" class="form-control form-control-sm font-monospace text-uppercase" id="posTransferRef" placeholder="Contoh: QRIS-94812 / BCA-81204" oninput="validatePosTransaction()">
                            </div>
                            <div class="form-check p-2.5 rounded-2 bg-light border mb-2">
                                <input class="form-check-input ms-0 me-2" type="checkbox" id="posTransferConfirmed" onchange="validatePosTransaction()">
                                <label class="form-check-label text-dark small fw-semibold" for="posTransferConfirmed">
                                    Dana sudah dipastikan masuk ke rekening koperasi
                                </label>
                                <small class="text-muted d-block mt-0.5" style="font-size: 0.68rem;">Kasir wajib memverifikasi mutasi / struk QRIS kasir sebelum menyelesaikan transaksi.</small>
                            </div>
                        </div>

                        <!-- C. CREDIT PIUTANG ANGGOTA CONTAINER -->
                        <div id="posCreditContainer" class="d-none">
                            <div class="mb-2">
                                <label class="form-label text-muted d-block mb-1" style="font-size: 0.72rem;">Pilih / Cari Anggota Koperasi:</label>
                                <select class="form-select form-select-sm fw-semibold" id="posMemberSelect" onchange="onPosMemberSelected(this.value)">
                                    <option value="">-- Cari Nomor Anggota / Nama Karyawan --</option>
                                    @foreach($membersDirectory as $member)
                                    <option value="{{ $member['id'] }}">{{ $member['unit'] }} | {{ $member['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Mini Credit Status Card -->
                            <div id="posMemberCreditCard" class="d-none border rounded-2 p-2.5 bg-light mb-2">
                                <div class="d-flex justify-content-between align-items-center mb-1.5 pb-1.5 border-bottom">
                                    <div>
                                        <span class="fw-bold text-dark d-block small" id="cardMemberName">-</span>
                                        <small class="text-muted" style="font-size: 0.68rem;" id="cardMemberUnit">-</small>
                                    </div>
                                    <span class="badge" id="cardMemberStatusBadge">AKTIF</span>
                                </div>
                                <div class="row g-1 mb-2 text-center" style="font-size: 0.72rem;">
                                    <div class="col-6">
                                        <div class="p-1.5 rounded-1 bg-white border">
                                            <span class="text-muted d-block" style="font-size: 0.65rem;">Status Tagihan:</span>
                                            <span class="fw-bold" id="cardMemberOverdueStatus">Lancar</span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-1.5 rounded-1 bg-white border">
                                            <span class="text-muted d-block" style="font-size: 0.65rem;">Sisa Limit:</span>
                                            <span class="fw-bold text-success font-monospace" id="cardMemberLimitDisplay">Rp 0</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Alert Kelayakan / Alasan Penolakan -->
                                <div id="cardCreditAlert" class="alert py-2 px-2.5 mb-0 small" style="font-size: 0.72rem;">
                                    <!-- Dynamic message injected via JS -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Button Selesaikan Transaksi -->
                    <button type="button" class="btn btn-success w-100 py-2.5 fw-semibold d-inline-flex align-items-center justify-content-center gap-1.5 shadow-xs" id="btnCompletePosSale" onclick="openPosReceiptModal()">
                        <i class="bi bi-printer"></i>
                        <span>Selesaikan Transaksi & Cetak Struk</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 3: VERIFIKASI ANTRIAN PICKUP ONLINE -->
    <div class="tab-pane fade {{ $activeTab === 'pickup' ? 'show active' : '' }}" id="pickup-pane" role="tabpanel">
        <div class="card border-0 shadow-sm bg-white">
            <div class="card-header bg-white py-3 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h5 class="card-title fw-bold text-dark mb-0 fs-6">Daftar Antrian Pengambilan Barang (Pickup)</h5>
                    <small class="text-muted" style="font-size: 0.75rem;">Cocokkan kode pickup yang ditunjukkan pembeli lalu serahkan fisik barang</small>
                </div>
                <div style="max-width: 300px;" class="w-100">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="bi bi-qr-code"></i></span>
                        <input type="text" id="pickupFilterInput" class="form-control font-monospace text-uppercase" placeholder="Cari Kode PKP-XXXXX..." oninput="filterPickupQueue(this.value)">
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                    <thead class="table-light">
                        <tr>
                            <th>Kode Pickup</th>
                            <th>ID & Nama Anggota</th>
                            <th>Rincian Barang</th>
                            <th>Total Bayar</th>
                            <th>Metode Bayar</th>
                            <th class="text-center">Aksi Serah Terima</th>
                        </tr>
                    </thead>
                    <tbody id="pickupQueueTableBody">
                        @foreach($pickupQueue as $index => $q)
                            @php
                                $payStatus = $q['payment_status'] ?? ($q['payment_method'] === 'BANK_TRANSFER' ? 'PENDING_VERIFICATION' : ($q['payment_method'] === 'CREDIT' ? 'CREDIT_APPROVED' : 'PENDING_CASH'));
                                $canHandover = $q['can_handover'];
                            @endphp
                            <tr id="pickupRow-{{ $q['pickup_code'] }}" data-code="{{ strtolower($q['pickup_code']) }}" data-member="{{ strtolower($q['member_name']) }}">
                                <td>
                                    <span class="badge bg-primary px-2.5 py-1 fs-7 font-monospace fw-bold">
                                        {{ $q['pickup_code'] }}
                                    </span>
                                    <small class="text-muted d-block" style="font-size: 0.68rem;">{{ $q['created_at'] }}</small>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark">{{ $q['member_name'] }}</span>
                                    <small class="text-muted d-block font-monospace">{{ $q['member_id'] }}</small>
                                </td>
                                <td>
                                    <span class="text-secondary">{{ $q['items_summary'] }}</span>
                                </td>
                                <td class="fw-bold text-dark font-monospace">
                                    Rp {{ number_format($q['total_amount'], 0, ',', '.') }}
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        @if($q['payment_method'] === 'CASH')
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">Tunai di Kasir</span>
                                            <small class="text-muted" style="font-size: 0.68rem;">Bayar saat serah terima</small>
                                        @elseif($q['payment_method'] === 'CREDIT')
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Piutang 7 Hari</span>
                                            <small class="text-success" style="font-size: 0.68rem;"><i class="bi bi-check-circle"></i> Plafon Disetujui</small>
                                        @else
                                            @if($payStatus === 'VERIFIED')
                                                <span class="badge bg-success text-white" id="badgeTransfer-{{ $q['pickup_code'] }}">
                                                    <i class="bi bi-check-circle-fill me-0.5"></i> Transfer Terverifikasi
                                                </span>
                                                <small class="text-muted font-monospace" style="font-size: 0.68rem;">{{ $q['transfer_ref'] ?? '-' }}</small>
                                            @elseif($payStatus === 'PAYMENT_REJECTED')
                                                <span class="badge bg-danger text-white" id="badgeTransfer-{{ $q['pickup_code'] }}">
                                                    <i class="bi bi-x-circle-fill me-0.5"></i> Transfer Ditolak
                                                </span>
                                                <small class="text-danger" style="font-size: 0.68rem;">Mutasi tidak ditemukan</small>
                                            @else
                                                <span class="badge bg-warning text-dark" id="badgeTransfer-{{ $q['pickup_code'] }}">
                                                    <i class="bi bi-clock-history me-0.5"></i> Menunggu Verifikasi
                                                </span>
                                                <button type="button" class="btn btn-outline-primary btn-sm py-0.5 px-2 mt-0.5 d-inline-flex align-items-center gap-1" id="btnVerify-{{ $q['pickup_code'] }}" @disabled(!$q['pending_payment_id']) style="font-size: 0.7rem;" onclick="openVerifyTransferModal(@js($q['pickup_code']), @js($q['member_name']), @js($q['member_id']), {{ $q['pending_payment_amount'] ?? 0 }}, @js($q['transfer_ref'] ?? ''))">
                                                    <i class="bi bi-receipt-cutoff"></i>
                                                    <span>Verifikasi Transfer</span>
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex align-items-center justify-content-center gap-1.5 flex-wrap">
                                        @if(!$q['pending_payment_id'] && $q['remaining_amount'] > 0 && $q['payment_method'] !== 'CREDIT')
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="recordQueuedPayment(@js($q['pickup_code']))">Catat pembayaran</button>
                                        @endif
                                        <button type="button" class="btn btn-sm {{ $canHandover ? 'btn-outline-success' : 'btn-outline-secondary opacity-50' }} px-2.5 py-1 fw-semibold d-inline-flex align-items-center gap-1 handover-btn" id="btnHandover-{{ $q['pickup_code'] }}" {{ $canHandover ? '' : 'disabled' }} title="{{ $canHandover ? 'Serahkan barang ke pembeli' : 'Wajib verifikasi pembayaran transfer terlebih dahulu' }}" onclick="openPickupHandoverModal(@js($q['pickup_code']), @js($q['member_name']), @js($q['member_id']), @js($q['items_summary']), {{ $q['total_amount'] }}, @js($q['payment_method']))">
                                            <i class="bi bi-check2-circle"></i>
                                            <span>Serah Terima</span>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger px-2 py-1 cancel-btn" id="btnCancel-{{ $q['pickup_code'] }}" title="Batalkan Pesanan (Lepas Reservasi Stok & Credit Hold)" onclick="openCancelOrderModal(@js($q['pickup_code']), @js($q['member_name']), @js($q['items_summary']), {{ $q['total_amount'] }})">
                                            <i class="bi bi-x-circle"></i>
                                            <span class="d-none d-md-inline">Batal</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        <tr id="pickupEmptyRow" class="d-none">
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-3 d-block text-secondary opacity-50 mb-1"></i>
                                <span class="small">Tidak ada antrean pesanan pickup yang cocok dengan pencarian.</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- ==============================================================================
     MODAL 1: SCANNER BARCODE KAMERA & HARDWARE GERAI (html5-qrcode Nyata)
     ============================================================================== -->
<div class="modal fade" id="barcodeScannerModal" tabindex="-1" aria-labelledby="barcodeScannerModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white py-3 px-3 px-md-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-1.5 rounded-2 bg-primary text-white d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                        <i class="bi bi-upc-scan fs-6"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold mb-0 fs-6" id="barcodeScannerModalLabel">Scanner Barcode Kamera Gerai KOPEG</h6>
                        <small class="text-white-50" style="font-size: 0.72rem;">Mendukung EAN-13, EAN-8, Code-128, QR Code, dan File Gambar</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span id="sessionScanCountBadge" class="badge bg-success-subtle text-success border border-success-subtle fw-bold" style="font-size: 0.72rem;">
                        0 Barang Ter-scan
                    </span>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup" onclick="stopBarcodeScanner()"></button>
                </div>
            </div>

            <div class="modal-body p-3 p-md-4">
                <!-- 1. Scanner Viewport Container with Real-time Laser Line -->
                <div class="scanner-viewport-box mb-3" id="scannerViewportBox">
                    <div id="qr-reader" style="width: 100%;"></div>
                    <!-- Animated Laser Beam -->
                    <div class="scanner-laser-line" id="scannerLaserBeam"></div>
                    <!-- Reticle Frame -->
                    <div class="scanner-reticle" id="scannerReticle"></div>
                    <!-- Green Flash Effect on Hit -->
                    <div class="scanner-flash-overlay" id="scannerFlashOverlay"></div>
                </div>

                <!-- 2. Status & Controls Row -->
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <!-- Status Banner -->
                    <div id="scannerStatusAlert" class="alert alert-info py-1.5 px-3 small d-flex align-items-center gap-2 mb-0 flex-grow-1">
                        <div class="spinner-border spinner-border-sm text-primary flex-shrink-0" id="scannerSpinner" role="status"></div>
                        <span id="scannerStatusText">Mengaktifkan modul kamera... Arahkan barcode ke kotak bidik.</span>
                    </div>

                    <!-- Mode Scan Beruntun (Multi-Scan Switch) -->
                    <div class="form-check form-switch small mb-0 px-2 py-1 bg-light rounded border flex-shrink-0" title="Secara default, scan barcode akan langsung keluar otomatis seperti di GoPay. Aktifkan jika ingin scan banyak barang tanpa menutup kamera.">
                        <input class="form-check-input ms-0 me-2" type="checkbox" id="toggleMultiScan">
                        <label class="form-check-label fw-semibold text-dark" for="toggleMultiScan" style="font-size: 0.74rem;">
                            Scan Beruntun (Tetap di Kamera)
                        </label>
                    </div>
                </div>

                <!-- 3. Error Banner: Ramah jika akses ditolak atau tidak ada kamera -->
                <div id="scannerErrorAlert" class="alert alert-danger py-2.5 px-3 small d-none mb-3">
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi bi-exclamation-octagon-fill fs-5 text-danger flex-shrink-0 mt-0.5"></i>
                        <div class="flex-grow-1">
                            <strong class="d-block mb-1" id="scannerErrorTitle">Akses Kamera Belum Tersedia</strong>
                            <span id="scannerErrorMessage" class="d-block text-secondary mb-2">
                                Izin kamera tidak diberikan atau perangkat komputer kasir tidak memiliki webcam.
                            </span>
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="button" class="btn btn-outline-danger btn-sm py-1 px-2.5 fw-semibold" onclick="retryCameraAccess()">
                                    <i class="bi bi-arrow-clockwise me-1"></i> Coba Akses Kamera Lagi
                                </button>
                                <button type="button" class="btn btn-primary btn-sm py-1 px-2.5 fw-semibold" onclick="document.getElementById('barcodeFileInput').click()">
                                    <i class="bi bi-image me-1"></i> Pindai dari File Gambar Barcode
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. Pilihan Cepat Barcode Demo Instan (100% Berfungsi Kapan Saja) -->
                <div class="p-2.5 rounded-3 bg-light border mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-1">
                        <small class="text-secondary fw-bold text-uppercase d-inline-flex align-items-center gap-1.5" style="font-size: 0.72rem;">
                            <i class="bi bi-lightning-charge-fill text-warning"></i>
                            <span>Pilihan Cepat & Uji Coba Barcode:</span>
                        </small>
                        <div class="d-flex gap-1.5 align-items-center">
                            <button type="button" class="btn btn-warning btn-sm py-0.5 px-2.5 fw-bold text-dark shadow-xs" style="font-size: 0.72rem;" onclick="openTestBarcodeGalleryModal()">
                                <i class="bi bi-grid-3x3-gap-fill me-1"></i> Buka Lembar Barcode Siap-Scan
                            </button>
                            <span class="badge bg-white text-primary border small" style="font-size: 0.65rem;">
                                <i class="bi bi-hand-index-thumb me-1"></i> Klik untuk Scan Instan
                            </span>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-1.5">
                        <button type="button" class="btn btn-outline-primary btn-sm px-2 py-1 font-monospace shadow-xs" style="font-size: 0.74rem;" onclick="simulateBarcodeScan('89999991001', 'Beras Premium Ramos 5 Kg')">
                            <i class="bi bi-upc me-1"></i> 89999991001 (Beras Ramos)
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm px-2 py-1 font-monospace shadow-xs" style="font-size: 0.74rem;" onclick="simulateBarcodeScan('89999991002', 'Minyak Goreng Pouch 2L')">
                            <i class="bi bi-upc me-1"></i> 89999991002 (Minyak 2L)
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm px-2 py-1 font-monospace shadow-xs" style="font-size: 0.74rem;" onclick="simulateBarcodeScan('89999991003', 'Gula Pasir Kristal 1kg')">
                            <i class="bi bi-upc me-1"></i> 89999991003 (Gula 1kg)
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm px-2 py-1 font-monospace shadow-xs" style="font-size: 0.74rem;" onclick="simulateBarcodeScan('89999991005', 'Telur Ayam Fresh 1kg')">
                            <i class="bi bi-upc me-1"></i> 89999991005 (Telur 1kg)
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm px-2 py-1 font-monospace shadow-xs" style="font-size: 0.74rem;" onclick="simulateBarcodeScan('89999991008', 'Kopi Arabika 250g')">
                            <i class="bi bi-upc me-1"></i> 89999991008 (Kopi 250g)
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm px-2 py-1 font-monospace shadow-xs" style="font-size: 0.74rem;" onclick="simulateBarcodeScan('89999991011', 'Deterjen Bubuk 800g')">
                            <i class="bi bi-upc me-1"></i> 89999991011 (Deterjen)
                        </button>
                    </div>
                </div>

                <!-- 5. Input Manual Barcode / SKU di Meja Kasir -->
                <div class="p-2.5 rounded-2 bg-light border">
                    <label class="form-label text-secondary fw-semibold d-block mb-1" style="font-size: 0.72rem;">Ketik Manual SKU / Barcode di Meja Kasir:</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-upc"></i></span>
                        <input type="text" id="modalManualBarcodeInput" class="form-control font-monospace" placeholder="Ketik SKU atau nomor barcode (contoh: SEM-001 / 89999991001)..." onkeydown="if(event.key === 'Enter'){ submitModalManualBarcode(); }">
                        <button type="button" class="btn btn-primary fw-semibold px-3" onclick="submitModalManualBarcode()">
                            + Masukkan
                        </button>
                    </div>
                </div>

                <!-- Hidden file input & temp reader untuk scan gambar barcode -->
                <input type="file" id="barcodeFileInput" class="d-none" accept="image/*" onchange="handleBarcodeFile(this)">
                <div id="qr-reader-file-temp" class="d-none"></div>
            </div>

            <div class="modal-footer bg-light py-2.5 px-3 justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1.5" id="btnSwitchCamera" onclick="switchScannerCamera()">
                        <i class="bi bi-arrow-repeat"></i>
                        <span>Ganti Kamera</span>
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1.5" onclick="document.getElementById('barcodeFileInput').click()">
                        <i class="bi bi-image"></i>
                        <span>Scan dari Gambar</span>
                    </button>
                    <button type="button" class="btn btn-outline-warning text-dark fw-semibold btn-sm d-inline-flex align-items-center gap-1.5" onclick="openTestBarcodeGalleryModal()">
                        <i class="bi bi-upc-scan text-dark"></i>
                        <span>Lembar Barcode Siap-Scan</span>
                    </button>
                </div>
                <small class="text-muted" style="font-size: 0.72rem;">
                    <i class="bi bi-camera-video me-1"></i> Kamera aktif &bull; Arahkan ke barcode komoditas
                </small>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 2: STRUK RESMI & KONFIRMASI TRANSAKSI POS KASIR
     ============================================================================== -->
<div class="modal fade" id="posReceiptModal" tabindex="-1" aria-labelledby="posReceiptModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white py-3 px-3 px-md-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill fs-5"></i>
                    <h6 class="modal-title fw-bold mb-0" id="posReceiptModalLabel">Transaksi Berhasil & Struk Kasir</h6>
                </div>
            </div>
            <div class="modal-body p-3 p-md-4">
                <!-- Struk Digital Card -->
                <div class="border rounded-3 p-3 bg-light mb-3 font-monospace" style="font-size: 0.78rem;">
                    <div class="text-center pb-2 mb-2 border-bottom border-dashed">
                        <h6 class="fw-bold mb-0 font-sans text-dark">KOPEG MART BANDUNG</h6>
                        <small class="text-muted d-block">Koperasi Konsumen Pegawai POLMAN Bandung</small>
                        <small class="text-muted d-block">Jl. Kanayakan No. A11 Dago, Bandung</small>
                        <div class="d-flex justify-content-between text-muted mt-1 small">
                            <span>No: <strong class="text-dark"><span id="receiptOrderNo">-</span></strong></span>
                            <span id="receiptDate">16 Nov 2026</span>
                        </div>
                    </div>

                    <!-- Kasir & Pelanggan Info -->
                    <div class="mb-2 pb-2 border-bottom border-dashed">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Kasir:</span>
                            <span class="fw-bold text-dark">{{ session('user.name', auth()->user()->email) }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Pelanggan:</span>
                            <span class="fw-bold text-dark" id="receiptCustomerName">Pembeli Umum</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Metode Bayar:</span>
                            <span class="badge bg-primary" id="receiptPaymentBadge">TUNAI</span>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div class="mb-2 pb-2 border-bottom border-dashed" id="receiptItemsContainer">
                        <!-- Populated via JS -->
                    </div>

                    <!-- Financial Total & Details -->
                    <div class="mb-2">
                        <div class="d-flex justify-content-between fs-6 fw-bold">
                            <span>TOTAL:</span>
                            <span class="text-primary" id="receiptTotalAmount">Rp 0</span>
                        </div>
                        <div id="receiptCashDetails" class="pt-1">
                            <div class="d-flex justify-content-between text-muted">
                                <span>Tunai Diterima:</span>
                                <span id="receiptCashReceived">Rp 0</span>
                            </div>
                            <div class="d-flex justify-content-between text-success fw-bold">
                                <span>Kembalian:</span>
                                <span id="receiptChangeAmount">Rp 0</span>
                            </div>
                        </div>
                        <div id="receiptTransferDetails" class="d-none pt-1">
                            <div class="d-flex justify-content-between text-muted">
                                <span>No. Referensi:</span>
                                <span class="fw-bold text-dark" id="receiptTransferRef">-</span>
                            </div>
                            <div class="d-flex justify-content-between text-success">
                                <span>Status Mutasi:</span>
                                <span>TERVERIFIKASI KASIR</span>
                            </div>
                        </div>
                        <div id="receiptCreditDetails" class="d-none pt-1">
                            <div class="d-flex justify-content-between text-muted">
                                <span>ID Anggota:</span>
                                <span class="fw-bold text-dark" id="receiptCreditMemberId">-</span>
                            </div>
                            <div class="d-flex justify-content-between text-danger">
                                <span>Jatuh Tempo:</span>
                                <span class="fw-bold" id="receiptCreditDueDate">23 Nov 2026 (7 Hari)</span>
                            </div>
                        </div>
                    </div>

                    <div class="text-center pt-2 border-top border-dashed text-muted" style="font-size: 0.7rem;">
                        Terima kasih telah berbelanja di KoperasiKu.<br>
                        Barang yang sudah dibeli tidak dapat ditukar kecuali cacat pabrik.
                    </div>
                </div>

                <div class="alert alert-success py-2 px-3 small d-flex align-items-center gap-2 mb-0">
                    <i class="bi bi-printer-fill fs-5"></i>
                    <span>Struk transaksi resmi telah diterbitkan dan stok barang otomatis terpotong.</span>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 px-3 justify-content-between">
                <button type="button" class="btn btn-outline-dark btn-sm d-inline-flex align-items-center gap-1.5" onclick="window.print()">
                    <i class="bi bi-printer"></i>
                    <span>Cetak Struk Fisik</span>
                </button>
                <button type="button" class="btn btn-success btn-sm fw-semibold px-3" data-bs-dismiss="modal" onclick="resetPosCartAfterSale()">
                    Selesai & Transaksi Baru
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 3: KONFIRMASI SERAH TERIMA PICKUP PESANAN ONLINE
     ============================================================================== -->
<div class="modal fade" id="pickupConfirmModal" tabindex="-1" aria-labelledby="pickupConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-3 px-3 px-md-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-box-seam fs-5"></i>
                    <h6 class="modal-title fw-bold mb-0" id="pickupConfirmModalLabel">Konfirmasi Penyerahan Barang</h6>
                </div>
            </div>
            <div class="modal-body p-3 p-md-4">
                <div class="p-3 rounded-2 bg-light border mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                        <span class="text-muted small">Kode Pickup Anggota:</span>
                        <span class="badge bg-primary px-3 py-1 fs-6 font-monospace fw-bold" id="confirmPickupCode">PKP-00000</span>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted d-block" style="font-size: 0.7rem;">Penerima / Anggota:</small>
                        <span class="fw-bold text-dark" id="confirmPickupMemberName">-</span>
                        <small class="text-secondary d-block font-monospace" id="confirmPickupMemberId">-</small>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted d-block" style="font-size: 0.7rem;">Rincian Barang yang Diambil:</small>
                        <div class="p-2 rounded bg-white border text-secondary small fw-semibold" id="confirmPickupItems">
                            -
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <span class="text-secondary small fw-semibold">Total Nilai Pesanan:</span>
                        <span class="fw-bold text-dark font-monospace fs-6" id="confirmPickupAmount">Rp 0</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <span class="text-muted small">Status Pembayaran:</span>
                        <span class="badge" id="confirmPickupPaymentBadge">-</span>
                    </div>
                </div>

                <label for="pickupCodeInput" class="form-label">Masukkan kode yang ditunjukkan anggota</label>
                <input id="pickupCodeInput" class="form-control mb-2" maxlength="100" autocomplete="off" placeholder="PKP-...">
                <div id="pickupError" class="alert alert-danger d-none" role="alert"></div>
                <div class="form-check p-2.5 rounded-2 bg-warning bg-opacity-10 border border-warning border-opacity-25 mb-3">
                    <input class="form-check-input ms-0 me-2" type="checkbox" id="checkPhysicalHandover">
                    <label class="form-check-label text-dark small fw-semibold" for="checkPhysicalHandover">
                        Fisik sembako sudah diperiksa dan diserahkan lengkap ke pembeli.
                    </label>
                    <small class="text-muted d-block mt-0.5" style="font-size: 0.68rem;">Konfirmasi ini akan memotong stok fisik on-hand gerai secara permanen.</small>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 px-3 justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
                    Batal
                </button>
                <button type="button" class="btn btn-success btn-sm fw-semibold px-3 d-inline-flex align-items-center gap-1.5" id="btnExecuteHandover" onclick="executePickupHandover()">
                    <i class="bi bi-check2-circle"></i>
                    <span>Konfirmasi Serah Terima Selesai</span>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="queuedPaymentModal" tabindex="-1" aria-label="Catat pembayaran">
<div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5>Catat pembayaran pesanan</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div><form id="queuedPaymentForm"><div class="modal-body"><p id="queuedPaymentInfo"></p><label for="queuedPaymentAmount" class="form-label">Nominal diterima</label><input type="number" class="form-control" id="queuedPaymentAmount" min="0.01" step="0.01" required><div id="queuedPaymentRefBox" class="mt-2"><label for="queuedPaymentRef" class="form-label">Referensi transfer</label><input id="queuedPaymentRef" class="form-control" maxlength="100"></div><div id="queuedPaymentError" class="alert alert-danger d-none mt-2" role="alert"></div></div><div class="modal-footer"><button type="submit" id="queuedPaymentSubmit" class="btn btn-primary">Simpan pembayaran</button></div></form></div></div></div>
<!-- ==============================================================================
     MODAL 4: SUKSES SERAH TERIMA PICKUP (TOAST / CONFIRMATION)
     ============================================================================== -->
<div class="modal fade" id="pickupSuccessModal" tabindex="-1" aria-labelledby="pickupSuccessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow text-center p-3">
            <div class="p-3">
                <div class="p-3 rounded-circle bg-success bg-opacity-10 text-success d-inline-flex align-items-center justify-content-center mb-2" style="width: 56px; height: 56px;">
                    <i class="bi bi-check-lg fs-2"></i>
                </div>
                <h6 class="fw-bold text-dark mb-1">Serah Terima Berhasil!</h6>
                <p class="text-muted small mb-3" id="pickupSuccessMessage">
                    Pesanan telah diserahkan dan dibukukan selesai.
                </p>
                <button type="button" class="btn btn-success btn-sm w-100 fw-semibold" data-bs-dismiss="modal">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 5: VERIFIKASI PEMBAYARAN TRANSFER BANK / QRIS KASIR
     ============================================================================== -->
<div class="modal fade" id="verifyTransferModal" tabindex="-1" aria-labelledby="verifyTransferModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white py-3 px-3 px-md-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-receipt-cutoff text-primary fs-5"></i>
                    <h6 class="modal-title fw-bold mb-0" id="verifyTransferModalLabel">Verifikasi Pembayaran Transfer Bank</h6>
                </div>
            </div>
            <div class="modal-body p-3 p-md-4">
                <!-- Info Ringkas Pesanan -->
                <div class="d-flex justify-content-between align-items-center p-2.5 rounded-2 bg-light border mb-3">
                    <div>
                        <span class="badge bg-primary font-monospace" id="vtfPickupCode">PKP-00000</span>
                        <span class="fw-bold text-dark d-block small mt-1" id="vtfMemberName">-</span>
                        <small class="text-muted font-monospace" id="vtfMemberId">-</small>
                    </div>
                    <div class="text-end">
                        <small class="text-muted d-block" style="font-size: 0.7rem;">Nominal Tagihan:</small>
                        <span class="fs-5 fw-extrabold text-primary font-monospace" id="vtfTotalAmount">Rp 0</span>
                    </div>
                </div>

                <!-- Pratinjau Bukti Transfer Simulasi -->
                <div class="border rounded-3 p-3 bg-light mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-1.5 border-bottom">
                        <span class="small fw-bold text-dark"><i class="bi bi-image text-secondary me-1"></i> Bukti Transfer Pembeli (M-Banking)</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.65rem;">Lampiran Pembeli</span>
                    </div>
                    <div class="p-2.5 rounded bg-white border font-monospace small" style="font-size: 0.75rem;">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Bank Penerima:</span>
                            <span class="fw-semibold text-dark">Bank Mandiri (KOPEG POLMAN)</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">No. Rekening:</span>
                            <span class="text-dark">131-00-881290-1</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Nama Pengirim:</span>
                            <span class="fw-semibold text-dark" id="vtfSenderName">-</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Nominal Transfer:</span>
                            <span class="fw-bold text-success" id="vtfTransferProofAmount">Rp 0</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Status M-Banking:</span>
                            <span class="text-success fw-bold">BERHASIL / MATCH</span>
                        </div>
                    </div>
                </div>

                <!-- Input Cek Referensi Mutasi Kasir -->
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary mb-1">Nomor Referensi Mutasi Rekening Kasir:</label>
                    <input type="text" class="form-control form-control-sm font-monospace text-uppercase" id="vtfRefInput" placeholder="Contoh: TRX-BCA-51922 atau RRN-QRIS-9102">
                    <small class="text-muted d-block mt-0.5" style="font-size: 0.7rem;">Nomor referensi unik yang tertera pada notifikasi mutasi bank gerai.</small>
                </div>

                <div class="form-check p-2.5 rounded-2 bg-light border mb-2">
                    <input class="form-check-input ms-0 me-2" type="checkbox" id="vtfConfirmFundsCheck">
                    <label class="form-check-label text-dark small fw-semibold" for="vtfConfirmFundsCheck">
                        Saya mengonfirmasi bahwa dana telah resmi masuk ke rekening koperasi.
                    </label>
                </div>

                <!-- Box Alasan Penolakan (Collapse jika kasir ingin menolak) -->
                <div id="vtfRejectBox" class="p-2.5 rounded-2 bg-danger bg-opacity-10 border border-danger border-opacity-25 mt-2 d-none">
                    <label class="form-label small fw-bold text-danger mb-1">Alasan Penolakan Bukti Transfer:</label>
                    <select class="form-select form-select-sm mb-2" id="vtfRejectReason">
                        <option value="Dana belum masuk ke mutasi rekening koperasi">Dana belum masuk ke mutasi rekening koperasi</option>
                        <option value="Nominal pada bukti transfer tidak sesuai total pesanan">Nominal pada bukti transfer tidak sesuai total pesanan</option>
                        <option value="Bukti transfer tidak jelas / terindikasi palsu">Bukti transfer tidak jelas / terindikasi palsu</option>
                        <option value="Rekening tujuan salah (bukan rekening resmi koperasi)">Rekening tujuan salah (bukan rekening resmi koperasi)</option>
                    </select>
                    <button type="button" class="btn btn-danger btn-sm w-100 fw-semibold" onclick="executeRejectTransfer()">
                        Konfirmasi Tolak Pembayaran Ini
                    </button>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 px-3 justify-content-between">
                <button type="button" class="btn btn-outline-danger btn-sm" id="btnToggleRejectTransfer" onclick="toggleRejectTransferBox()">
                    <i class="bi bi-x-circle me-1"></i> Tolak Bukti
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
                        Batal
                    </button>
                    <button type="button" class="btn btn-success btn-sm fw-semibold px-3" onclick="executeApproveTransfer()">
                        <i class="bi bi-check-circle me-1"></i> Setujui & Verifikasi Dana
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 6: KONFIRMASI PEMBATALAN PESANAN PICKUP (PRD AC-12)
     ============================================================================== -->
<div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger text-white py-3 px-3 px-md-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-octagon-fill fs-5"></i>
                    <h6 class="modal-title fw-bold mb-0" id="cancelOrderModalLabel">Konfirmasi Pembatalan Pesanan</h6>
                </div>
            </div>
            <div class="modal-body p-3 p-md-4">
                <div class="p-3 rounded-2 bg-light border mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small">Kode Pickup:</span>
                        <span class="badge bg-danger font-monospace fs-6" id="cancelPickupCode">PKP-00000</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small">Pemesan:</span>
                        <strong class="text-dark" id="cancelMemberName">-</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small">Barang:</span>
                        <span class="text-secondary small" id="cancelItemsSummary">-</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-1 border-top">
                        <span class="text-muted small">Total Tagihan:</span>
                        <span class="fw-bold text-dark font-monospace" id="cancelTotalAmount">Rp 0</span>
                    </div>
                </div>

                <!-- Aturan PRD AC-12 Notice -->
                <div class="alert alert-warning py-2.5 px-3 small d-flex align-items-start gap-2 mb-3">
                    <i class="bi bi-info-circle-fill text-warning-emphasis fs-5 flex-shrink-0 mt-0.5"></i>
                    <div>
                        <strong>Ketentuan Pembatalan (PRD AC-12):</strong>
                        <div class="text-secondary mt-0.5">
                            Pembatalan sebelum serah terima akan secara otomatis <strong>melepaskan reservasi stok barang</strong> kembali ke rak gerai dan <strong>melepaskan penahanan limit kredit (credit hold)</strong> anggota tanpa membentuk tagihan piutang.
                        </div>
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label small fw-bold text-secondary mb-1">Alasan Pembatalan Pesanan:</label>
                    <select class="form-select form-select-sm" id="cancelOrderReason">
                        <option value="Pembeli tidak kunjung datang mengambil pesanan (Expired)">Pembeli tidak kunjung datang mengambil pesanan (Expired)</option>
                        <option value="Permintaan pembatalan langsung oleh anggota">Permintaan pembatalan langsung oleh anggota</option>
                        <option value="Bukti transfer pembayaran ditolak / dana tidak masuk">Bukti transfer pembayaran ditolak / dana tidak masuk</option>
                        <option value="Kesalahan pesanan sembako / stok rusak di rak">Kesalahan pesanan sembako / stok rusak di rak</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 px-3 justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
                    Batal (Kembali)
                </button>
                <button type="button" class="btn btn-danger btn-sm fw-semibold px-3" onclick="executeCancelOrder()">
                    <i class="bi bi-trash3 me-1"></i> Ya, Batalkan Pesanan Ini
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 7: PRATINJAU BARCODE & QR KODE PRODUK (SINGLE PRODUCT)
     ============================================================================== -->
<div class="modal fade" id="singleProductBarcodeModal" tabindex="-1" aria-labelledby="singleProductBarcodeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white py-3 px-3 px-md-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-1.5 rounded-2 bg-primary text-white d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                        <i class="bi bi-upc-scan fs-6"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold mb-0 fs-6" id="singleProductBarcodeModalLabel">Label Barcode Komoditas</h6>
                        <small class="text-white-50" style="font-size: 0.72rem;">Format standar Code-128 & QR Code untuk scan kamera</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="badge bg-light text-secondary border px-2.5 py-1 mb-2 font-monospace" id="spbSkuBadge">-</div>
                <h5 class="fw-bold text-dark mb-1" id="spbProductName">-</h5>
                <p class="text-primary fw-bold font-monospace mb-3" id="spbPriceBadge">Rp 0</p>

                <!-- Nav Tabs Barcode 1D / QR 2D -->
                <ul class="nav nav-pills nav-fill bg-light p-1 rounded-2 mb-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active py-1.5 px-3 fw-semibold small" id="tab-spb-barcode" data-bs-toggle="pill" data-bs-target="#pane-spb-barcode" type="button" role="tab">
                            <i class="bi bi-upc me-1"></i> Barcode 1D (Code-128)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-1.5 px-3 fw-semibold small" id="tab-spb-qr" data-bs-toggle="pill" data-bs-target="#pane-spb-qr" type="button" role="tab">
                            <i class="bi bi-qr-code me-1"></i> QR Code 2D
                        </button>
                    </li>
                </ul>

                <div class="tab-content mb-3">
                    <div class="tab-pane fade show active p-3 bg-white border rounded-3 shadow-xs" id="pane-spb-barcode" role="tabpanel">
                        <div class="p-2 bg-white rounded text-center d-flex justify-content-center">
                            <svg id="singleBarcodeSvg" style="max-width: 100%; height: auto;"></svg>
                        </div>
                        <small class="text-muted d-block mt-2" style="font-size: 0.72rem;">
                            Format standar Code-128 dengan Quiet Zone putih untuk pembacaan akurat kamera.
                        </small>
                    </div>
                    <div class="tab-pane fade p-3 bg-white border rounded-3 shadow-xs text-center" id="pane-spb-qr" role="tabpanel">
                        <div class="p-2 bg-white rounded d-inline-block text-center">
                            <canvas id="singleBarcodeQrCanvas"></canvas>
                        </div>
                        <small class="text-muted d-block mt-2" style="font-size: 0.72rem;">
                            Format QR Code 2D cepat discan pada segala resolusi kamera & pencahayaan.
                        </small>
                    </div>
                </div>

                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm px-3 fw-semibold" onclick="testScanCurrentSingleBarcode()">
                        <i class="bi bi-cart-plus me-1"></i> Uji Scan Masuk Keranjang
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i> Cetak Label
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 8: LEMBAR KATALOG BARCODE & QR PRODUK SIAP-SCAN
     ============================================================================== -->
<div class="modal fade" id="testBarcodeGalleryModal" tabindex="-1" aria-labelledby="testBarcodeGalleryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white py-3 px-3 px-md-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-1.5 rounded-2 bg-warning text-dark d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                        <i class="bi bi-qr-code-scan fs-6"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold mb-0 fs-6" id="testBarcodeGalleryModalLabel">Katalog Barcode & QR Siap-Scan Kamera Gerai</h6>
                        <small class="text-white-50" style="font-size: 0.72rem;">Arahkan kamera HP atau webcam kasir ke barcode di bawah ini untuk menguji deteksi scan nyata</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-light btn-sm py-1 px-2.5" onclick="renderAllGalleryBarcodes()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh Barcode
                    </button>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
            </div>
            <div class="modal-body p-3 p-md-4 bg-light">
                <div class="alert alert-primary py-2.5 px-3 small d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-camera-video-fill fs-5 text-primary flex-shrink-0"></i>
                    <div>
                        <strong>Petunjuk Pengujian Barcode Kamera:</strong>
                        Buka modal ini di layar kedua / smartphone, lalu arahkan kamera scanner kasir ke barcode atau QR code di bawah. Setiap barcode telah dikonfigurasi dengan standar Code-128 dan QR Code kontras tinggi.
                    </div>
                </div>

                <div class="row g-3" id="galleryBarcodeListContainer">
                    @foreach($stockItems as $idx => $item)
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="card h-100 border shadow-xs rounded-3 bg-white">
                                <div class="card-header bg-white border-bottom py-2 px-3 d-flex justify-content-between align-items-center">
                                    <span class="badge bg-light text-secondary border font-monospace">{{ $item['sku'] }}</span>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle fw-bold">Rp {{ number_format($item['sell_price'], 0, ',', '.') }}</span>
                                </div>
                                <div class="card-body p-3 text-center">
                                    <h6 class="fw-bold text-dark mb-1 text-truncate" title="{{ $item['name'] }}">{{ $item['name'] }}</h6>
                                    <small class="text-muted d-block font-monospace mb-2" style="font-size: 0.72rem;">Barcode: {{ $item['barcode'] }}</small>
                                    
                                    <!-- Barcode SVG Container -->
                                    <div class="p-2 bg-white border rounded mb-2 text-center" style="min-height: 85px;">
                                        <svg class="gallery-barcode-svg" id="galleryBarcodeSvg_{{ $idx }}" data-barcode="{{ $item['barcode'] }}" style="max-width: 100%; height: auto;"></svg>
                                    </div>

                                    <!-- QR Code Canvas Container -->
                                    <div class="d-flex align-items-center justify-content-center gap-2 mb-2 p-1.5 bg-light rounded border">
                                        <canvas class="gallery-qr-canvas" id="galleryQrCanvas_{{ $idx }}" data-barcode="{{ $item['barcode'] }}"></canvas>
                                        <div class="text-start" style="font-size: 0.7rem;">
                                            <span class="d-block fw-bold text-dark">Mode QR 2D</span>
                                            <span class="text-muted">Alternatif scan cepat jika kamera webcam beresolusi rendah</span>
                                        </div>
                                    </div>
                                    
                                    <button type="button" class="btn btn-outline-primary btn-sm w-100 fw-semibold" onclick="simulateBarcodeScan(@js($item['barcode']), @js($item['name']))">
                                        <i class="bi bi-cart-plus me-1"></i> Scan Masuk Keranjang
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer bg-light py-2.5 px-3 justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-dark btn-sm" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Cetak Lembar Barcode
                </button>
                <small class="text-muted" style="font-size: 0.72rem;">
                    <i class="bi bi-info-circle me-1"></i> Barcode siap diarahkan langsung ke scanner kamera gerai
                </small>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================================
     GOPAY-STYLE SCAN CONFIRMATION BOTTOM SHEET / FLOATING CARD (Frontend-Design)
     ============================================================================== -->
<div id="gopayScanSuccessSheet" class="gopay-scan-sheet d-none" aria-live="polite">
    <div class="gopay-sheet-card shadow-xl">
        <!-- Drag Handle (Mobile) -->
        <div class="gopay-drag-handle d-md-none"></div>

        <div class="p-3 p-md-3.5">
            <!-- Top Status Row -->
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-1.5">
                    <span id="gopayStatusBadge" class="badge bg-success text-white fw-bold px-2 py-0.5 rounded-pill" style="font-size: 0.68rem; letter-spacing: 0.03em;">
                        <i class="bi bi-upc-scan me-1"></i> SCAN BERHASIL
                    </span>
                    <span class="text-muted small" id="gopayScanType" style="font-size: 0.72rem;">Komoditas Kasir</span>
                </div>
                <button type="button" class="btn-close btn-sm p-1" onclick="dismissGopayScanSheet()" aria-label="Tutup"></button>
            </div>

            <!-- Content Card -->
            <div class="d-flex align-items-center gap-3 mb-2.5 p-2.5 bg-light rounded-3 border">
                <div class="gopay-icon-badge flex-shrink-0" id="gopayIconBadge">
                    <i class="bi bi-check-lg"></i>
                </div>
                <div class="flex-grow-1 min-w-0">
                    <h6 class="fw-extrabold text-dark mb-1 text-truncate" id="gopayItemTitle" style="font-size: 0.95rem;">
                        -
                    </h6>
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <span class="badge bg-white text-secondary border font-monospace" id="gopayItemCode" style="font-size: 0.7rem;">
                            -
                        </span>
                        <span class="fw-extrabold text-success font-monospace" id="gopayItemPrice" style="font-size: 0.95rem;">
                            Rp 0
                        </span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle fw-semibold" id="gopayItemQtyBadge" style="font-size: 0.68rem;">
                            +1 ke Keranjang
                        </span>
                    </div>
                    <small class="text-secondary d-block text-truncate" id="gopayItemSubtitle" style="font-size: 0.72rem;">
                        Otomatis masuk ke kalkulasi kasir gerai.
                    </small>
                </div>
            </div>

            <!-- Action Buttons Row -->
            <div class="d-flex align-items-center justify-content-between gap-2 pt-1 border-top">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-primary btn-sm px-3 fw-semibold d-inline-flex align-items-center gap-1.5" id="gopaySheetActionBtn">
                        <i class="bi bi-camera-fill"></i>
                        <span>Scan Lagi</span>
                    </button>
                    <button type="button" class="btn btn-outline-dark btn-sm px-2.5 fw-semibold d-inline-flex align-items-center gap-1" onclick="focusPosCart()">
                        <i class="bi bi-cart3"></i>
                        <span>Lihat Keranjang</span>
                    </button>
                </div>
                <small class="text-muted d-none d-sm-inline" style="font-size: 0.72rem;">
                    <kbd class="bg-light text-muted border px-1">ESC</kbd> untuk menutup
                </small>
            </div>
        </div>

        <!-- Animated Progress Countdown Bar -->
        <div class="gopay-progress-bar" id="gopayProgressBar"></div>
    </div>
</div>

<!-- FLOATING LIVE SCAN TOAST -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1090;">
    <div id="posScanToast" class="toast align-items-center text-bg-dark border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center gap-2 py-2 px-3 text-white">
                <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center p-1.5 flex-shrink-0" style="width: 32px; height: 32px;">
                    <i class="bi bi-check-lg fs-5"></i>
                </div>
                <div>
                    <strong class="d-block" id="toastItemName">Produk Berhasil Di-scan</strong>
                    <small class="text-white-50" id="toastItemDetail">Ditambahkan ke keranjang kasir</small>
                </div>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<!-- Library html5-qrcode CDN untuk Barcode Scanning Kamera Nyata -->


<script>
// ==============================================================================
// 1. DATA MASTER & STATE KASIR
// ==============================================================================
const stockCatalogue = @js($stockItems);
const realPickupQueue = @js($pickupQueue);

const membersDirectory = @js($membersDirectory);
let posCart = [];
const escapeHtml = value => String(value).replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"})[c]);

let selectedPickupOrder = null;

// ==============================================================================
// 2. KERANJANG KASIR & KALKULASI FINANSIAL
// ==============================================================================
function renderPosCart() {
    const list = document.getElementById('posCartList');
    if (!list) return;

    if (posCart.length === 0) {
        list.innerHTML = `
            <div class="text-center py-4 text-muted">
                <i class="bi bi-cart-x fs-3 d-block text-secondary opacity-50 mb-1"></i>
                <span class="small">Keranjang kasir kosong.<br>Scan barcode atau pilih sembako di samping.</span>
            </div>
        `;
        document.getElementById('posTotalDisplay').textContent = 'Rp 0';
        document.getElementById('posItemsCountDisplay').textContent = '0 item';
        calculatePosChange();
        validatePosTransaction();
        return;
    }

    let total = 0;
    let totalItems = 0;
    let html = '';

    posCart.forEach((item, index) => {
        const subtotal = item.price * item.qty;
        total += subtotal;
        totalItems += item.qty;

        html += `
            <div class="d-flex justify-content-between align-items-center gap-2 py-2.5 border-bottom small">
                <div class="lh-sm min-w-0 flex-grow-1">
                    <span class="fw-semibold text-dark d-block text-truncate" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</span>
                    <small class="text-muted tokped-price" style="font-size: 0.72rem;">${escapeHtml(item.sku)} &bull; Rp ${item.price.toLocaleString('id-ID')}</small>
                </div>
                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                    <div class="pos-qty-stepper">
                        <button type="button" onclick="updatePosQty(${index}, -1)" aria-label="Kurangi jumlah">&minus;</button>
                        <span>${item.qty}</span>
                        <button type="button" onclick="updatePosQty(${index}, 1)" aria-label="Tambah jumlah">+</button>
                    </div>
                    <span class="fw-bold text-dark tokped-price text-end text-nowrap" style="min-width: 84px; font-size: 0.82rem;">
                        Rp ${subtotal.toLocaleString('id-ID')}
                    </span>
                    <button type="button" class="btn btn-link pos-trash-btn d-inline-flex align-items-center justify-content-center" onclick="removePosItem(${index})" title="Hapus" aria-label="Hapus item">
                        <i class="bi bi-trash3"></i>
                    </button>
                </div>
            </div>
        `;
    });

    list.innerHTML = html;
    document.getElementById('posTotalDisplay').textContent = `Rp ${total.toLocaleString('id-ID')}`;
    document.getElementById('posItemsCountDisplay').textContent = `${totalItems} barang (${posCart.length} SKU)`;

    calculatePosChange();
    validatePosTransaction();
}

function posAddToCart(name, price, sku) {
    if (cashierBusy) return false;
    const product = stockCatalogue.find(p => p.sku === sku);
    const existing = posCart.find(i => i.sku === sku);
    if (!product || (existing?.qty || 0) >= Number(product.available)) { showCashierError('Produk tidak tersedia atau stok tidak cukup.'); return false; }
    if (existing) { existing.qty += 1; existing.price = Number(product.sell_price); }
    else posCart.push({sku, product_id:product.id || sku, name:product.name, price:Number(product.sell_price), qty:1});
    renderPosCart(); return true;
}
function updatePosQty(index, delta) {
    if (cashierBusy || !posCart[index]) return;
    if (delta > 0) { const item = posCart[index]; posAddToCart(item.name,item.price,item.sku); return; }
    posCart[index].qty += delta;
    if (posCart[index].qty <= 0) posCart.splice(index,1);
    renderPosCart();
}

function removePosItem(index) {
    if (cashierBusy) return;
    if (posCart[index]) {
        posCart.splice(index, 1);
        renderPosCart();
    }
}

function clearPosCart() {
    if (cashierBusy) return;
    posCart = [];
    renderPosCart();
}

function getPosTotal() {
    return posCart.reduce((sum, item) => sum + Math.round(item.price * 100) * item.qty, 0) / 100;
}

function calculatePosChange() {
    const total = getPosTotal();
    const received = Number(document.getElementById('posCashReceived').value) || 0;
    const change = received - total;
    const display = document.getElementById('posChangeDisplay');

    if (total === 0) {
        display.textContent = 'Rp 0';
        display.className = 'fw-bold text-secondary fs-6 font-monospace';
        return;
    }

    if (change >= 0) {
        display.textContent = `Rp ${change.toLocaleString('id-ID')}`;
        display.className = 'fw-bold text-success fs-6 font-monospace';
    } else {
        display.textContent = `Kurang Rp ${Math.abs(change).toLocaleString('id-ID')}`;
        display.className = 'fw-bold text-danger fs-6 font-monospace';
    }
}

function setExactCash() {
    if (cashierBusy) return;
    document.getElementById('posCashReceived').value = getPosTotal();
    calculatePosChange();
    validatePosTransaction();
}

function setCashAmount(amt) {
    if (cashierBusy) return;
    document.getElementById('posCashReceived').value = amt;
    calculatePosChange();
    validatePosTransaction();
}

// ==============================================================================
// 3. DINAMIKA METODE PEMBAYARAN & KELAYAKAN PIUTANG ANGGOTA
// ==============================================================================
function onPosPaymentMethodChanged(method) {
    const cashBox = document.getElementById('posCashContainer');
    const transferBox = document.getElementById('posTransferContainer');
    const creditBox = document.getElementById('posCreditContainer');

    cashBox.classList.add('d-none');
    transferBox.classList.add('d-none');
    creditBox.classList.add('d-none');

    if (method === 'CASH') {
        cashBox.classList.remove('d-none');
        calculatePosChange();
    } else if (method === 'BANK_TRANSFER') {
        transferBox.classList.remove('d-none');
    } else if (method === 'CREDIT') {
        creditBox.classList.remove('d-none');
        const sel = document.getElementById('posMemberSelect').value;
        if (sel) onPosMemberSelected(sel);
    }

    validatePosTransaction();
}

function onPosMemberSelected(memberId) {
    const card = document.getElementById('posMemberCreditCard');
    const alertBox = document.getElementById('cardCreditAlert');

    if (!memberId) {
        card.classList.add('d-none');
        validatePosTransaction();
        return;
    }

    const member = membersDirectory.find(m => m.id === memberId);
    if (!member) {
        card.classList.add('d-none');
        validatePosTransaction();
        return;
    }

    card.classList.remove('d-none');
    document.getElementById('cardMemberName').textContent = `${member.name} (${member.id})`;
    document.getElementById('cardMemberUnit').textContent = member.unit;

    // Status Badge
    const badge = document.getElementById('cardMemberStatusBadge');
    if (member.status === 'TERVERIFIKASI') {
        badge.className = 'badge bg-success-subtle text-success border border-success-subtle';
        badge.textContent = 'AKTIF TERVERIFIKASI';
    } else {
        badge.className = 'badge bg-danger text-white';
        badge.textContent = 'DIBEKUKAN';
    }

    // Overdue status
    const overdueEl = document.getElementById('cardMemberOverdueStatus');
    if (member.is_overdue) {
        overdueEl.className = 'fw-bold text-danger';
        overdueEl.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-0.5"></i> Overdue ${member.overdue_days} Hari`;
    } else {
        overdueEl.className = 'fw-bold text-success';
        overdueEl.innerHTML = `<i class="bi bi-check-circle me-0.5"></i> Lancar (0 Tunggakan)`;
    }

    // Available limit display
    document.getElementById('cardMemberLimitDisplay').textContent = `Rp ${member.available_limit.toLocaleString('id-ID')}`;

    // Kelayakan Check
    const totalPos = getPosTotal();
    alertBox.className = 'alert py-2 px-2.5 mb-0 small';

    if (member.status === 'DIBEKUKAN') {
        alertBox.classList.add('alert-danger');
        alertBox.innerHTML = `<strong>⛔ Kredit Ditolak:</strong> Fasilitas piutang anggota saat ini dinonaktifkan oleh manajer koperasi.`;
    } else if (member.is_overdue) {
        alertBox.classList.add('alert-danger');
        alertBox.innerHTML = `<strong>⛔ Kredit Ditolak (Credit Hold):</strong> Anggota memiliki tagihan tertunggak lewat jatuh tempo (${member.overdue_days} hari). Lunasi tagihan sebelumnya sebelum membuka transaksi piutang baru.`;
    } else if (member.available_limit < totalPos) {
        alertBox.classList.add('alert-danger');
        alertBox.innerHTML = `<strong>⛔ Kredit Ditolak:</strong> Sisa limit belanja (Rp ${member.available_limit.toLocaleString('id-ID')}) tidak mencukupi untuk total tagihan transaksi ini (Rp ${totalPos.toLocaleString('id-ID')}).`;
    } else {
        alertBox.classList.add('alert-success');
        alertBox.innerHTML = `<strong>✅ Fasilitas Disetujui:</strong> Sisa limit mencukupi (Tersedia Rp ${member.available_limit.toLocaleString('id-ID')}). Jatuh tempo: 7 hari kalender bunga 0%.`;
    }

    validatePosTransaction();
}

function validatePosTransaction() {
    const btn = document.getElementById('btnCompletePosSale');
    const method = document.getElementById('posPaymentMethod').value;
    const total = getPosTotal();

    if (cashierBusy || total <= 0) {
        btn.disabled = true;
        btn.title = "Keranjang kasir masih kosong";
        return;
    }

    if (method === 'CASH') {
        const received = Number(document.getElementById('posCashReceived').value) || 0;
        if (received < total) {
            btn.disabled = true;
            btn.title = "Uang diterima masih kurang dari total tagihan";
            return;
        }
        btn.disabled = false;
        btn.title = "Klik untuk menyelesaikan transaksi";
    } else if (method === 'BANK_TRANSFER') {
        const confirmed = document.getElementById('posTransferConfirmed')?.checked;
        if (!confirmed || !document.getElementById('posTransferRef').value.trim()) {
            btn.disabled = true;
            btn.title = "Harap konfirmasi bahwa dana transfer/QRIS telah masuk";
            return;
        }
        btn.disabled = false;
        btn.title = "Klik untuk menyelesaikan transaksi";
    } else if (method === 'CREDIT') {
        const memberId = document.getElementById('posMemberSelect').value;
        if (!memberId) {
            btn.disabled = true;
            btn.title = "Pilih anggota koperasi terlebih dahulu";
            return;
        }
        const member = membersDirectory.find(m => m.id === memberId);
        if (!member || member.status === 'DIBEKUKAN' || member.is_overdue || member.available_limit < total) {
            btn.disabled = true;
            btn.title = "Kelayakan kredit anggota tidak memenuhi syarat";
            return;
        }
        btn.disabled = false;
        btn.title = "Klik untuk menyelesaikan transaksi";
    }
}

// ==============================================================================
// 4. MODAL STRUK POS KASIR (REPLACES BROWSER ALERT)
// ==============================================================================
async function openPosReceiptModal() {
    const total = getPosTotal();
    if (total <= 0 || cashierBusy) return;
    if (document.getElementById('btnCompletePosSale').disabled) return;

    const method = document.getElementById('posPaymentMethod').value;
    const payload = {payment_choice: method, items: posCart.map(item => ({product_id: item.product_id, quantity: item.qty})),
        member_id: method === 'CREDIT' ? document.getElementById('posMemberSelect').value : null,
        transfer_verified: method === 'BANK_TRANSFER' && document.getElementById('posTransferConfirmed').checked,
        external_reference: method === 'BANK_TRANSFER' ? document.getElementById('posTransferRef').value.trim() : null,
        expected_total: total.toFixed(2), cash_received: method === 'CASH' ? document.getElementById('posCashReceived').value : null};
    let result;
    const btn = document.getElementById('btnCompletePosSale');
    btn.disabled = true;
    const receivedCash = Number(document.getElementById('posCashReceived').value);
    const controls = [...document.querySelectorAll('#pos-pane input, #pos-pane select')].filter(el => !el.disabled);
    controls.forEach(el => el.disabled = true);
    try { result = await submitCashier('/cashier/pos-sale', payload); } catch (err) { showCashierError(err.message); validatePosTransaction(); return; } finally { controls.forEach(el => el.disabled = false); }
    document.getElementById('receiptOrderNo').textContent = result.order_no;
    const now = new Date(result.completed_at);
    const dateStr = now.toLocaleString('id-ID', {timeZone:'Asia/Jakarta', day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit'}) + ' WIB';

    document.getElementById('receiptDate').textContent = dateStr;
    document.getElementById('receiptTotalAmount').textContent = `Rp ${total.toLocaleString('id-ID')}`;

    // Itemized table
    const container = document.getElementById('receiptItemsContainer');
    let itemsHtml = '';
    posCart.forEach(item => {
        itemsHtml += `
            <div class="d-flex justify-content-between mb-1">
                <span>${escapeHtml(item.name)} (${item.qty}x)</span>
                <span>Rp ${(item.price * item.qty).toLocaleString('id-ID')}</span>
            </div>
        `;
    });
    container.innerHTML = itemsHtml;

    // Payment details
    const cashDet = document.getElementById('receiptCashDetails');
    const transDet = document.getElementById('receiptTransferDetails');
    const credDet = document.getElementById('receiptCreditDetails');
    const badge = document.getElementById('receiptPaymentBadge');

    cashDet.classList.add('d-none');
    transDet.classList.add('d-none');
    credDet.classList.add('d-none');

    if (method === 'CASH') {
        badge.className = 'badge bg-success';
        badge.textContent = 'TUNAI (CASH)';
        document.getElementById('receiptCustomerName').textContent = 'Pembeli Umum / Langsung';
        cashDet.classList.remove('d-none');
        const received = receivedCash;
        document.getElementById('receiptCashReceived').textContent = `Rp ${received.toLocaleString('id-ID')}`;
        document.getElementById('receiptChangeAmount').textContent = `Rp ${(received - total).toLocaleString('id-ID')}`;
    } else if (method === 'BANK_TRANSFER') {
        badge.className = 'badge bg-primary';
        badge.textContent = 'TRANSFER / QRIS';
        document.getElementById('receiptCustomerName').textContent = 'Pembeli Non-Tunai';
        transDet.classList.remove('d-none');
        const ref = document.getElementById('posTransferRef').value.trim() || '-';
        document.getElementById('receiptTransferRef').textContent = ref;
    } else if (method === 'CREDIT') {
        badge.className = 'badge bg-warning text-dark';
        badge.textContent = 'PIUTANG ANGGOTA 7 HARI';
        const memberId = document.getElementById('posMemberSelect').value;
        const member = membersDirectory.find(m => m.id === memberId);
        document.getElementById('receiptCustomerName').textContent = member ? `${member.name}` : 'Anggota Koperasi';
        credDet.classList.remove('d-none');
        document.getElementById('receiptCreditMemberId').textContent = memberId;
        
        // Due date 7 days from now
        const dueDate = new Date(new Date(result.completed_at).getTime() + 7 * 86400000);
        document.getElementById('receiptCreditDueDate').textContent = dueDate.toLocaleDateString('id-ID', { timeZone:'Asia/Jakarta', day: '2-digit', month: 'short', year: 'numeric' }) + ' (Tempo 7 Hari)';

        // Deduct limit in mock session

    }

    posCart = [];
    renderPosCart();
    document.getElementById('posReceiptModal').addEventListener('hidden.bs.modal', () => location.assign('/cashier/pos'), {once:true});
    const modal = new bootstrap.Modal(document.getElementById('posReceiptModal'));
    modal.show();
}

function resetPosCartAfterSale() {
    posCart = [];
    document.getElementById('posBarcodeField').value = '';
    document.getElementById('posCashReceived').value = '0';
    document.getElementById('posTransferRef').value = '';
    if (document.getElementById('posTransferConfirmed')) document.getElementById('posTransferConfirmed').checked = false;
    renderPosCart();
}

// ==============================================================================
// 5. MODAL SERAH TERIMA PICKUP (REPLACES BROWSER CONFIRM/ALERT)
// ==============================================================================
function openPickupHandoverModal(pickupCode, memberName, memberId, itemsSummary, totalAmount, paymentMethod) {
    const live = realPickupQueue.find(o => o.pickup_code === pickupCode);
    if (!live?.can_handover) { showCashierError('Pesanan belum siap diserahkan.'); return; }
    document.getElementById('btnExecuteHandover').disabled = false;
    selectedPickupOrder = {
        code: pickupCode,
        name: memberName,
        id: memberId,
        items: itemsSummary,
        amount: totalAmount,
        method: paymentMethod
    };

    document.getElementById('confirmPickupCode').textContent = pickupCode;
    document.getElementById('confirmPickupMemberName').textContent = memberName;
    document.getElementById('confirmPickupMemberId').textContent = memberId;
    document.getElementById('confirmPickupItems').textContent = itemsSummary;
    document.getElementById('confirmPickupAmount').textContent = `Rp ${totalAmount.toLocaleString('id-ID')}`;

    const badge = document.getElementById('confirmPickupPaymentBadge');
    if (paymentMethod === 'CASH') {
        badge.className = 'badge bg-success-subtle text-success border border-success-subtle';
        badge.textContent = 'Lunas Tunai di Kasir';
    } else if (paymentMethod === 'CREDIT') {
        badge.className = 'badge bg-warning-subtle text-warning-emphasis border border-warning-subtle';
        badge.textContent = 'Piutang Anggota (Tempo 7 Hari)';
    } else {
        badge.className = 'badge bg-primary-subtle text-primary border border-primary-subtle';
        badge.textContent = 'Lunas Transfer Bank';
    }

    document.getElementById('checkPhysicalHandover').checked = false;
    document.getElementById('pickupCodeInput').value = '';
    inlineError('pickupError', '');

    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('pickupConfirmModal'));
    modal.show();
}

const cashierRequestKeys = new Map();
let cashierBusy = false;
async function submitCashier(url, payload) {
    if (cashierBusy) throw new Error('Transaksi sedang diproses.');
    cashierBusy = true;
    const signature = url + JSON.stringify(payload);
    const key = cashierRequestKeys.get(signature) || ('cashier-' + Date.now() + '-' + Math.random().toString(36).slice(2));
    cashierRequestKeys.set(signature, key);
    try {
        const response = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json', 'Accept':'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''},
            body: JSON.stringify({...payload, idempotency_key:key})});
        const result = await response.json().catch(() => ({message:'Respons server tidak valid. Muat ulang sesi jika diperlukan.'}));
        if (!response.ok || !result.success) throw new Error(result.message || 'Transaksi ditolak server.');
        cashierRequestKeys.delete(signature);
        return result.data;
    } finally { cashierBusy = false; }
}
function inlineError(id, message) {
    const el = document.getElementById(id);
    el.textContent = message;
    el.classList.toggle('d-none', !message);
}
function showCashierError(message) { showScanToast('Transaksi belum berhasil', message); }
async function executePickupHandover() {
    if (cashierBusy) return;
    const order = realPickupQueue.find(o => o.pickup_code === selectedPickupOrder?.code);
    const code = document.getElementById('pickupCodeInput').value.trim().toUpperCase();
    if (!order || !code || code !== order.pickup_code.toUpperCase()) { inlineError('pickupError', 'Kode pickup tidak cocok.'); return; }
    if (!document.getElementById('checkPhysicalHandover').checked) { inlineError('pickupError', 'Konfirmasi serah terima wajib dicentang.'); return; }
    const btn = document.getElementById('btnExecuteHandover');
    btn.disabled = true; inlineError('pickupError', '');
    try {
        await submitCashier('/api/v1/orders/'+order.id+'/confirm-pickup', {pickup_code:order.pickup_code, handover_confirmed:true});
        const modal = document.getElementById('pickupConfirmModal');
        modal.addEventListener('hidden.bs.modal', () => {
            const success = document.getElementById('pickupSuccessModal');
            document.getElementById('pickupSuccessMessage').textContent = order.pickup_code + ' telah diserahkan dan dibukukan selesai.';
            success.addEventListener('hidden.bs.modal', () => location.assign('/cashier/antrian'), {once:true});
            bootstrap.Modal.getOrCreateInstance(success).show();
        }, {once:true});
        bootstrap.Modal.getOrCreateInstance(modal).hide();
    } catch(err) { inlineError('pickupError', err.message); btn.disabled = false; }
}
let queuedPaymentOrder = null;
function recordQueuedPayment(code) {
    const order = realPickupQueue.find(o => o.pickup_code === code);
    if (!order || order.pending_payment_id || order.remaining_amount <= 0 || order.payment_method === 'CREDIT') { showCashierError('Pesanan tidak dapat menerima pembayaran.'); return; }
    queuedPaymentOrder = order;
    document.getElementById('queuedPaymentInfo').textContent = order.pickup_code + ' — ' + order.member_name;
    const amount = document.getElementById('queuedPaymentAmount');
    amount.value = order.remaining_amount; amount.max = order.remaining_amount;
    const transfer = order.payment_method === 'BANK_TRANSFER';
    const ref = document.getElementById('queuedPaymentRef');
    ref.value = ''; ref.required = transfer;
    document.getElementById('queuedPaymentRefBox').classList.toggle('d-none', !transfer);
    inlineError('queuedPaymentError', '');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('queuedPaymentModal')).show();
}
document.getElementById('queuedPaymentForm').addEventListener('submit', async e => {
    e.preventDefault();
    if (cashierBusy || !queuedPaymentOrder) return;
    const order = queuedPaymentOrder;
    const method = order.payment_method === 'BANK_TRANSFER' ? 'BANK_TRANSFER' : 'CASH';
    const amount = document.getElementById('queuedPaymentAmount').value;
    const ref = document.getElementById('queuedPaymentRef').value.trim();
    const btn = document.getElementById('queuedPaymentSubmit');
    btn.disabled = true; inlineError('queuedPaymentError', '');
    try { await submitCashier('/api/v1/orders/'+order.id+'/payments', {method, amount, external_reference:method === 'BANK_TRANSFER' ? ref : null}); location.assign('/cashier/antrian'); }
    catch(err) { inlineError('queuedPaymentError', err.message); btn.disabled = false; }
});

function filterCashierStock(rawQuery) {
    const query = (rawQuery || '').toLowerCase().trim();
    let matchCount = 0;
    document.querySelectorAll('.stock-item-row').forEach(row => {
        const name = row.getAttribute('data-name') || '';
        const sku = row.getAttribute('data-sku') || '';
        if (!query || name.includes(query) || sku.includes(query)) {
            row.classList.remove('d-none');
            matchCount++;
        } else {
            row.classList.add('d-none');
        }
    });

    const emptyRow = document.getElementById('stockEmptyRow');
    if (emptyRow) {
        if (matchCount === 0) emptyRow.classList.remove('d-none');
        else emptyRow.classList.add('d-none');
    }
}

function filterPickupQueue(query) {
    const q = (query || '').toLowerCase().trim();
    let matchCount = 0;
    document.querySelectorAll('#pickupQueueTableBody tr:not(#pickupEmptyRow)').forEach(row => {
        const code = row.getAttribute('data-code') || '';
        const member = row.getAttribute('data-member') || '';
        if (!q || code.includes(q) || member.includes(q)) {
            row.classList.remove('d-none');
            matchCount++;
        } else {
            row.classList.add('d-none');
        }
    });

    const emptyRow = document.getElementById('pickupEmptyRow');
    if (emptyRow) {
        if (matchCount === 0) emptyRow.classList.remove('d-none');
        else emptyRow.classList.add('d-none');
    }
}

// ==============================================================================
// VERIFIKASI PEMBAYARAN TRANSFER BANK / QRIS (FR-08)
// ==============================================================================
let currentVerifyTransfer = null;

function openVerifyTransferModal(code, memberName, memberId, amount, ref) {
    currentVerifyTransfer = { code, memberName, memberId, amount, ref };
    document.getElementById('vtfPickupCode').textContent = code;
    document.getElementById('vtfMemberName').textContent = memberName;
    document.getElementById('vtfMemberId').textContent = memberId;
    document.getElementById('vtfTotalAmount').textContent = `Rp ${amount.toLocaleString('id-ID')}`;
    document.getElementById('vtfSenderName').textContent = memberName;
    document.getElementById('vtfTransferProofAmount').textContent = `Rp ${amount.toLocaleString('id-ID')}`;
    document.getElementById('vtfRefInput').value = ref || '';
    document.getElementById('vtfConfirmFundsCheck').checked = false;
    document.getElementById('vtfRejectBox').classList.add('d-none');

    const modal = new bootstrap.Modal(document.getElementById('verifyTransferModal'));
    modal.show();
}

function toggleRejectTransferBox() {
    const box = document.getElementById('vtfRejectBox');
    box.classList.toggle('d-none');
}

async function verifyQueuedTransfer(action) {
    if (!currentVerifyTransfer) return;
    const order = realPickupQueue.find(o => o.pickup_code === currentVerifyTransfer.code);
    if (!order?.pending_payment_id) { showCashierError('Tidak ada pembayaran transfer pending.'); return; }
    if (action === 'VERIFY' && !document.getElementById('vtfConfirmFundsCheck').checked) { showCashierError('Pastikan dana telah diterima.'); return; }
    try { await submitCashier('/api/v1/payments/'+order.pending_payment_id+'/verify', {action}); location.assign('/cashier/antrian'); }
    catch(err) { showCashierError(err.message); }
}
function executeApproveTransfer() { return verifyQueuedTransfer('VERIFY'); }
function executeRejectTransfer() { return verifyQueuedTransfer('REJECT'); }

// ==============================================================================
// PEMBATALAN PESANAN PICKUP (PRD AC-12)
// ==============================================================================
let currentCancelOrder = null;

function openCancelOrderModal(code, memberName, items, amount) {
    currentCancelOrder = { code, memberName, items, amount };
    document.getElementById('cancelPickupCode').textContent = code;
    document.getElementById('cancelMemberName').textContent = memberName;
    document.getElementById('cancelItemsSummary').textContent = items;
    document.getElementById('cancelTotalAmount').textContent = `Rp ${amount.toLocaleString('id-ID')}`;

    const modal = new bootstrap.Modal(document.getElementById('cancelOrderModal'));
    modal.show();
}

async function executeCancelOrder() {
    if (!currentCancelOrder) return;
    const order = realPickupQueue.find(o => o.pickup_code === currentCancelOrder.code);
    if (!order) return;
    try { await submitCashier('/api/v1/orders/'+order.id+'/cancel', {}); location.assign('/cashier/antrian'); }
    catch(err) { showCashierError(err.message); }
}

// ==============================================================================
// 6. ENGINE SCANNER BARCODE KAMERA & HARDWARE GERAI (html5-qrcode Real Engine)
// ==============================================================================
let html5QrCode = null;
let currentCameraId = null;
let availableCameras = [];
let isScannerRunning = false;
let lastScannedCode = '';
let lastScannedTimestamp = 0;
let sessionScanCount = 0;

// Sound & Haptic Feedback khas E-Wallet (GoPay Style Chime)
function playGopayChime() {
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();

        // Nada 1: 880 Hz (A5), nada awal
        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(880, ctx.currentTime);
        gain1.gain.setValueAtTime(0.2, ctx.currentTime);
        gain1.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.1);
        osc1.connect(gain1);
        gain1.connect(ctx.destination);
        osc1.start(ctx.currentTime);
        osc1.stop(ctx.currentTime + 0.1);

        // Nada 2: 1760 Hz (A6), nada harmonik kedua e-wallet sukses
        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'sine';
        osc2.frequency.setValueAtTime(1760, ctx.currentTime + 0.06);
        gain2.gain.setValueAtTime(0.22, ctx.currentTime + 0.06);
        gain2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.22);
        osc2.connect(gain2);
        gain2.connect(ctx.destination);
        osc2.start(ctx.currentTime + 0.06);
        osc2.stop(ctx.currentTime + 0.22);
    } catch (e) {
        // Audio policy or blocked
    }
}

function playScanBeep() {
    playGopayChime();
}

function flashScannerVisual() {
    const overlay = document.getElementById('scannerFlashOverlay');
    if (overlay) {
        overlay.classList.add('active');
        setTimeout(() => overlay.classList.remove('active'), 250);
    }
}

function showScanToast(title, subtitle) {
    document.getElementById('toastItemName').textContent = title;
    document.getElementById('toastItemDetail').textContent = subtitle;
    const toastEl = document.getElementById('posScanToast');
    if (toastEl) { const icon = toastEl.querySelector('.rounded-circle'); const error = title === 'Transaksi belum berhasil'; if (icon) { icon.classList.toggle('bg-danger', error); icon.classList.toggle('bg-success', !error); icon.querySelector('i').className = error ? 'bi bi-exclamation-lg fs-5' : 'bi bi-check-lg fs-5'; } }
    if (toastEl && window.bootstrap) {
        const toast = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 2800 });
        toast.show();
    }
}

// GoPay-Style Bottom Sheet Manager
let gopayDismissTimeout = null;

function showGopayScanSheet(data) {
    const sheetEl = document.getElementById('gopayScanSuccessSheet');
    if (!sheetEl) return;

    if (gopayDismissTimeout) {
        clearTimeout(gopayDismissTimeout);
    }

    const typeEl = document.getElementById('gopayScanType');
    if (typeEl) typeEl.textContent = data.type || 'Komoditas Kasir';
    
    const badgeEl = document.getElementById('gopayStatusBadge');
    if (badgeEl) {
        badgeEl.className = `badge ${data.badgeClass || 'bg-success text-white'} fw-bold px-2 py-0.5 rounded-pill`;
        badgeEl.innerHTML = `<i class="bi bi-upc-scan me-1"></i> ${data.badgeText || 'SCAN BERHASIL'}`;
    }

    const titleEl = document.getElementById('gopayItemTitle');
    if (titleEl) titleEl.textContent = data.title || '-';

    const codeEl = document.getElementById('gopayItemCode');
    if (codeEl) codeEl.textContent = data.code || '-';

    const priceEl = document.getElementById('gopayItemPrice');
    if (priceEl) priceEl.textContent = data.price || 'Rp 0';

    const qtyEl = document.getElementById('gopayItemQtyBadge');
    if (qtyEl) qtyEl.textContent = data.qtyBadge || '+1 ke Keranjang';

    const subEl = document.getElementById('gopayItemSubtitle');
    if (subEl) subEl.textContent = data.subtitle || 'Barang otomatis ditambahkan ke keranjang kasir.';

    // Action CTA Button
    const actionBtn = document.getElementById('gopaySheetActionBtn');
    if (actionBtn) {
        actionBtn.innerHTML = `<i class="bi ${data.actionBtnIcon || 'bi-camera-fill'}"></i> <span>${data.actionBtnText || 'Scan Lagi'}</span>`;
        actionBtn.onclick = data.actionCallback || function() {
            dismissGopayScanSheet();
            openBarcodeScannerModal();
        };
    }

    // Reset progress bar animation
    const progressEl = document.getElementById('gopayProgressBar');
    if (progressEl) {
        progressEl.classList.remove('active');
        void progressEl.offsetWidth; // trigger reflow
        progressEl.classList.add('active');
    }

    // Tampilkan sheet
    sheetEl.classList.remove('d-none');
    sheetEl.style.transform = 'translateY(0)';
    sheetEl.style.opacity = '1';

    // Auto dismiss setelah 5 detik
    gopayDismissTimeout = setTimeout(() => {
        dismissGopayScanSheet();
    }, 5000);
}

function dismissGopayScanSheet() {
    const sheetEl = document.getElementById('gopayScanSuccessSheet');
    if (!sheetEl) return;
    sheetEl.style.transform = 'translateY(20px)';
    sheetEl.style.opacity = '0';
    setTimeout(() => {
        sheetEl.classList.add('d-none');
    }, 280);
}

function focusPosCart() {
    dismissGopayScanSheet();
    const cartCard = document.querySelector('.card.border-0.shadow-sm.bg-white.mb-4');
    if (cartCard) {
        cartCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        cartCard.classList.add('border-primary');
        setTimeout(() => cartCard.classList.remove('border-primary'), 2000);
    }
}

function openBarcodeScannerModal() {
    dismissGopayScanSheet();

    const errorAlert = document.getElementById('scannerErrorAlert');
    const statusAlert = document.getElementById('scannerStatusAlert');
    const statusText = document.getElementById('scannerStatusText');
    const spinner = document.getElementById('scannerSpinner');

    errorAlert.classList.add('d-none');
    statusAlert.classList.remove('d-none');
    if (spinner) spinner.classList.remove('d-none');
    statusText.textContent = "Menginisialisasi modul kamera gerai...";

    const modalEl = document.getElementById('barcodeScannerModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    
    // Pastikan pemanggilan stream kamera dieksekusi setelah modal selesai bertransisi
    const onShown = () => {
        modalEl.removeEventListener('shown.bs.modal', onShown);
        startBarcodeScanner();
    };
    modalEl.addEventListener('shown.bs.modal', onShown);
    modal.show();
}

function retryCameraAccess() {
    const errorAlert = document.getElementById('scannerErrorAlert');
    const statusAlert = document.getElementById('scannerStatusAlert');
    const statusText = document.getElementById('scannerStatusText');
    const spinner = document.getElementById('scannerSpinner');

    errorAlert.classList.add('d-none');
    statusAlert.classList.remove('d-none');
    if (spinner) spinner.classList.remove('d-none');
    statusText.textContent = "Mencoba ulang akses kamera perangkat...";
    startBarcodeScanner();
}

async function startBarcodeScanner() {
    if (isScannerRunning) return;

    const errorAlert = document.getElementById('scannerErrorAlert');
    const statusAlert = document.getElementById('scannerStatusAlert');
    const statusText = document.getElementById('scannerStatusText');
    const errorMessage = document.getElementById('scannerErrorMessage');
    const errorTitle = document.getElementById('scannerErrorTitle');
    const spinner = document.getElementById('scannerSpinner');

    errorAlert.classList.add('d-none');
    statusAlert.classList.remove('d-none');
    if (spinner) spinner.classList.remove('d-none');
    statusText.textContent = "Mengakses stream kamera gerai...";

    try {
        const Html5QrcodeClass = window.Html5Qrcode || (typeof Html5Qrcode !== 'undefined' ? Html5Qrcode : null);
        if (!Html5QrcodeClass) {
            throw new Error("Library scanner barcode belum terpasang atau gagal dimuat.");
        }

        // 1. Cek keamanan konteks browser (HTTPS atau localhost wajib untuk getUserMedia)
        const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
        if (!window.isSecureContext && !isLocal) {
            throw new Error("Akses kamera diblokir peramban karena belum menggunakan HTTPS atau localhost. Silakan buka via http://localhost:8000 atau gunakan opsi Scan dari Gambar.");
        }

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error("Perangkat atau peramban ini tidak mendukung API kamera (navigator.mediaDevices.getUserMedia).");
        }

        // 2. Format retail yang didukung
        const Formats = window.Html5QrcodeSupportedFormats || (typeof Html5QrcodeSupportedFormats !== 'undefined' ? Html5QrcodeSupportedFormats : null);
        const supportedFormats = Formats ? [
            Formats.QR_CODE,
            Formats.CODE_128,
            Formats.EAN_13,
            Formats.EAN_8,
            Formats.CODE_39,
            Formats.UPC_A,
            Formats.UPC_E
        ] : [0, 5, 9, 10, 3, 14, 15];

        // 3. Bersihkan instance scanner sebelumnya jika ada
        if (html5QrCode) {
            try {
                if (isScannerRunning) {
                    await html5QrCode.stop();
                }
                await html5QrCode.clear();
            } catch (cleanupErr) {
                console.warn("Cleanup old scanner:", cleanupErr);
            }
            isScannerRunning = false;
            html5QrCode = null;
        }

        const qrReaderContainer = document.getElementById('qr-reader');
        if (qrReaderContainer) {
            qrReaderContainer.innerHTML = '';
        }

        html5QrCode = new Html5QrcodeClass("qr-reader", { 
            formatsToSupport: supportedFormats, 
            verbose: false,
            experimentalFeatures: {
                useBarCodeDetectorIfSupported: true
            }
        });

        // 4. Deteksi daftar kamera hardware yang terhubung
        try {
            availableCameras = await Html5QrcodeClass.getCameras();
            const btnSwitch = document.getElementById('btnSwitchCamera');
            if (btnSwitch) {
                btnSwitch.classList.toggle('d-none', !availableCameras || availableCameras.length <= 1);
            }
        } catch (camErr) {
            console.warn("Query kamera awal:", camErr);
        }

        // 5. Konfigurasi scan viewfinder aman (selalu berada di dalam dimensi container)
        const scanConfig = {
            fps: 20,
            qrbox: (viewfinderWidth, viewfinderHeight) => {
                const w = Math.max(160, Math.floor(viewfinderWidth * 0.88));
                const h = Math.max(120, Math.floor(viewfinderHeight * 0.65));
                return {
                    width: Math.min(w, Math.max(100, viewfinderWidth - 10)),
                    height: Math.min(h, Math.max(80, viewfinderHeight - 10))
                };
            },
            aspectRatio: 1.333333,
            disableFlip: false
        };

        // 6. Strategi pemilihan kamera multi-tier (Mencegah OverconstrainedError)
        let started = false;

        // Strategi 1: Gunakan deviceId kamera tertentu jika tersedia
        if (availableCameras && availableCameras.length > 0) {
            let targetId = currentCameraId;
            if (!targetId || !availableCameras.some(c => c.id === targetId)) {
                // Prioritaskan kamera belakang di mobile jika ada label back/environment
                const backCam = availableCameras.find(c => /back|rear|belakang|environment/i.test(c.label));
                targetId = backCam ? backCam.id : availableCameras[0].id;
                currentCameraId = targetId;
            }

            try {
                await html5QrCode.start(
                    targetId,
                    scanConfig,
                    onBarcodeScanSuccess,
                    () => {} // silence noise
                );
                started = true;
            } catch (camIdErr) {
                console.warn("Start kamera dengan deviceId gagal, mencoba fallback facingMode...", camIdErr);
            }
        }

        // Strategi 2: Coba kamera belakang (environment)
        if (!started) {
            try {
                await html5QrCode.start(
                    { facingMode: "environment" },
                    scanConfig,
                    onBarcodeScanSuccess,
                    () => {}
                );
                started = true;
            } catch (envErr) {
                console.warn("Kamera environment tidak ada, mencoba kamera user/depan laptop...", envErr);
            }
        }

        // Strategi 3: Coba kamera depan (user - webcam laptop/PC)
        if (!started) {
            try {
                await html5QrCode.start(
                    { facingMode: "user" },
                    scanConfig,
                    onBarcodeScanSuccess,
                    () => {}
                );
                started = true;
            } catch (userErr) {
                console.warn("Kamera user gagal, mencoba default stream...", userErr);
            }
        }

        // Strategi 4: Default video stream tanpa batasan constraint
        if (!started) {
            await html5QrCode.start(
                {},
                scanConfig,
                onBarcodeScanSuccess,
                () => {}
            );
            started = true;
        }

        isScannerRunning = true;
        if (spinner) spinner.classList.add('d-none');
        statusText.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-record-circle-fill text-danger me-1"></i> Kamera Aktif:</span> Dekatkan barcode sembako atau QR ke dalam kotak bidik.`;

    } catch (err) {
        console.warn("Camera start exception:", err);
        isScannerRunning = false;
        statusAlert.classList.add('d-none');
        errorAlert.classList.remove('d-none');

        const errStr = String(err.name || '') + ' ' + String(err.message || '');
        if (errStr.includes('NotAllowedError') || errStr.toLowerCase().includes('permission')) {
            errorTitle.textContent = "Izin Akses Kamera Ditolak Browser";
            errorMessage.textContent = "Peramban memblokir akses ke webcam/kamera. Silakan klik ikon perizinan di address bar peramban untuk mengizinkan kamera, lalu klik tombol coba lagi di bawah.";
        } else if (errStr.includes('HTTPS') || errStr.includes('localhost') || errStr.includes('isSecureContext')) {
            errorTitle.textContent = "Batasan Keamanan Peramban (Bukan HTTPS)";
            errorMessage.textContent = err.message || "Akses kamera browser mewajibkan HTTPS atau alamat localhost. Buka via http://localhost:8000 atau gunakan scan dari file gambar.";
        } else if (errStr.includes('NoCameraFound') || errStr.toLowerCase().includes('notfound')) {
            errorTitle.textContent = "Kamera / Webcam Tidak Ditemukan";
            errorMessage.textContent = "Perangkat kasir tidak mendeteksi modul webcam. Silakan tancapkan kamera USB fisik atau gunakan fitur Scan dari File Gambar.";
        } else {
            errorTitle.textContent = "Kamera Belum Dapat Dibuka";
            errorMessage.textContent = (err.message || 'Gagal memulai stream video kamera') + ". Silakan gunakan opsi Scan dari File Gambar atau input manual.";
        }
    }
}

function onBarcodeScanSuccess(decodedText) {
    const now = Date.now();
    // Debounce 1.2 detik agar tidak men-scan ganda dalam milidetik yang sama
    if (decodedText === lastScannedCode && (now - lastScannedTimestamp) < 1200) {
        return;
    }

    lastScannedCode = decodedText;
    lastScannedTimestamp = now;

    playGopayChime();
    flashScannerVisual();
    if (navigator.vibrate) {
        try { navigator.vibrate([40, 40, 60]); } catch(e) {}
    }

    sessionScanCount++;
    const countBadge = document.getElementById('sessionScanCountBadge');
    if (countBadge) {
        countBadge.textContent = `${sessionScanCount} Barang Ter-scan`;
    }

    // Cek apakah mode multi-scan aktif
    const multiScan = document.getElementById('toggleMultiScan')?.checked ?? false;
    if (!multiScan) {
        // Keluar otomatis dari modal seperti di GoPay
        stopBarcodeScanner();
        const modalEl = document.getElementById('barcodeScannerModal');
        const modalInstance = bootstrap.Modal.getInstance(modalEl);
        if (modalInstance) modalInstance.hide();
        
        handleIncomingBarcode(decodedText, { source: 'camera', showGopaySheet: true });
    } else {
        // Tetap di kamera untuk scan beruntun
        handleIncomingBarcode(decodedText, { source: 'camera', showGopaySheet: false });
    }
}

async function stopBarcodeScanner() {
    if (html5QrCode && isScannerRunning) {
        try {
            await html5QrCode.stop();
        } catch (err) {
            console.warn("Stop scanner error:", err);
        } finally {
            isScannerRunning = false;
        }
    }
}

async function switchScannerCamera() {
    if (!availableCameras || availableCameras.length <= 1) {
        showScanToast("Info Kamera", "Hanya ada 1 kamera yang terdeteksi pada perangkat kasir.");
        return;
    }
    await stopBarcodeScanner();
    let currentIndex = availableCameras.findIndex(c => c.id === currentCameraId);
    let nextIndex = (currentIndex + 1) % availableCameras.length;
    currentCameraId = availableCameras[nextIndex].id;
    setTimeout(() => {
        startBarcodeScanner();
    }, 300);
}

// Simulasi scan instan untuk tombol demo
window.simulateBarcodeScan = function(code, name) {
    playGopayChime();
    flashScannerVisual();
    if (navigator.vibrate) {
        try { navigator.vibrate([40, 40, 60]); } catch(e) {}
    }
    sessionScanCount++;
    const countBadge = document.getElementById('sessionScanCountBadge');
    if (countBadge) {
        countBadge.textContent = `${sessionScanCount} Barang Ter-scan`;
    }
    handleIncomingBarcode(code, { source: 'demo', showGopaySheet: true });
};

// Scan dari berkas gambar / foto barcode (Langsung keluar otomatis seperti di GoPay)
async function handleBarcodeFile(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];

    try {
        // Hentikan kamera jika sedang aktif agar tidak bentrok
        if (html5QrCode && isScannerRunning) {
            try { await html5QrCode.stop(); } catch(e) {}
            isScannerRunning = false;
        }

        const Html5QrcodeClass = window.Html5Qrcode || (typeof Html5Qrcode !== 'undefined' ? Html5Qrcode : null);
        if (!Html5QrcodeClass) throw new Error("Library scanner barcode belum terpasang.");

        const Formats = window.Html5QrcodeSupportedFormats || (typeof Html5QrcodeSupportedFormats !== 'undefined' ? Html5QrcodeSupportedFormats : null);
        const supportedFormats = Formats ? [
            Formats.QR_CODE,
            Formats.CODE_128,
            Formats.EAN_13,
            Formats.EAN_8,
            Formats.CODE_39,
            Formats.UPC_A,
            Formats.UPC_E
        ] : [0, 5, 9, 10, 3, 14, 15];

        let fileScanner = new Html5QrcodeClass("qr-reader-file-temp", {
            formatsToSupport: supportedFormats,
            verbose: false
        });

        const decodedText = await fileScanner.scanFile(file, true);
        try { await fileScanner.clear(); } catch(e) {}

        // 1. LANGSUNG TUTUP MODAL SCANNER SEPERTI DI GOPAY
        const modalEl = document.getElementById('barcodeScannerModal');
        const modalInstance = bootstrap.Modal.getInstance(modalEl);
        if (modalInstance) {
            modalInstance.hide();
        }

        // 2. MAIN SUARA CHIME & VIBRATION
        playGopayChime();
        if (navigator.vibrate) {
            try { navigator.vibrate([60, 40, 80]); } catch(e) {}
        }

        // 3. PROSES DATA BARCODE & TAMPILKAN GOPAY BOTTOM SHEET
        handleIncomingBarcode(decodedText, { source: 'file', showGopaySheet: true });

    } catch (err) {
        console.warn("Scan file barcode error:", err);
        showScanToast("Gagal Membaca File Gambar", "Pastikan foto barcode tajam, tidak miring ekstrem, dan memiliki kontras yang cukup.");
    } finally {
        input.value = '';
    }
}

function submitModalManualBarcode() {
    const val = document.getElementById('modalManualBarcodeInput').value.trim();
    if (!val) return;

    playGopayChime();
    sessionScanCount++;
    const countBadge = document.getElementById('sessionScanCountBadge');
    if (countBadge) {
        countBadge.textContent = `${sessionScanCount} Barang Ter-scan`;
    }

    document.getElementById('modalManualBarcodeInput').value = '';

    const multiScan = document.getElementById('toggleMultiScan')?.checked ?? false;
    if (!multiScan) {
        stopBarcodeScanner();
        const modalEl = document.getElementById('barcodeScannerModal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
        handleIncomingBarcode(val, { source: 'manual', showGopaySheet: true });
    } else {
        handleIncomingBarcode(val, { source: 'manual', showGopaySheet: false });
    }
}

function submitManualBarcodeField() {
    const val = document.getElementById('posBarcodeField').value.trim();
    if (!val) return;
    playGopayChime();
    handleIncomingBarcode(val, { source: 'input', showGopaySheet: true });
    document.getElementById('posBarcodeField').value = '';
}

let lookupBusy = false;
async function handleIncomingBarcode(code, options = {}) {
    const clean = String(code || '').trim();
    if (!clean || lookupBusy || cashierBusy) return;
    lookupBusy = true;
    try {
        const pickup = /^(PKP|ORD)-/i.test(clean);
        const url = pickup ? '/cashier/pickup-lookup?code=' : '/api/products/lookup?barcode=';
        const response = await fetch(url + encodeURIComponent(clean), {headers:{Accept:'application/json'}, cache:'no-store'});
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Kode tidak ditemukan.');
        const data = result.data;
        if (pickup) {
            if (!['PENDING_PAYMENT','PENDING_PICKUP','READY_FOR_PICKUP'].includes(data.status)) { showCashierError('Pesanan ' + data.pickup_code + ' berstatus ' + data.status); return; }
            const index = realPickupQueue.findIndex(o => o.id === data.id);
            if (index >= 0) realPickupQueue[index] = data; else realPickupQueue.push(data);
            bootstrap.Tab.getOrCreateInstance(document.getElementById('pickup-tab')).show();
            const action = () => {
                dismissGopayScanSheet();
                if (data.can_handover) openPickupHandoverModal(data.pickup_code,data.member_name,data.member_id,data.items_summary,data.total_amount,data.payment_method);
                else if (data.pending_payment_id) openVerifyTransferModal(data.pickup_code,data.member_name,data.member_id,data.pending_payment_amount,data.transfer_ref);
                else if (data.payment_method !== 'CREDIT') recordQueuedPayment(data.pickup_code);
                else showCashierError('Fasilitas kredit pesanan tidak siap.');
            };
            const scanner = document.getElementById('barcodeScannerModal');
            if (scanner?.classList.contains('show') || scanner?.style.display === 'block') {
                scanner.addEventListener('hidden.bs.modal', action, {once:true});
                stopBarcodeScanner();
                bootstrap.Modal.getOrCreateInstance(scanner).hide();
            } else action();
            return;
        }
        const i = stockCatalogue.findIndex(p => p.sku === data.sku);
        const product = {...(stockCatalogue[i] || {}), id:data.id, sku:data.sku, barcode:data.barcode, name:data.name, sell_price:Number(data.price), available:Number(data.available_stock), unit:data.unit};
        if (i >= 0) stockCatalogue[i] = product; else stockCatalogue.push(product);
        if (posAddToCart(product.name,product.sell_price,product.sku)) showScanToast(product.name, 'Ditambahkan ke keranjang POS.');
    } catch (err) { showCashierError(err.message); }
    finally { lookupBusy = false; }
}

// Global Keydown Listener untuk Barcode Scanner Gun Fisik (USB / Bluetooth HID)
let barcodeGunBuffer = '';
let lastGunKeyTime = Date.now();

window.addEventListener('keydown', (e) => {
    const activeEl = document.activeElement;
    if (['posBarcodeField','modalManualBarcodeInput'].includes(activeEl?.id)) { barcodeGunBuffer = ''; return; }
    const isTypingInOtherInput = activeEl && 
        (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA' || activeEl.isContentEditable) &&
        activeEl.id !== 'posBarcodeField' && 
        activeEl.id !== 'modalManualBarcodeInput';

    if (isTypingInOtherInput) return;

    const now = Date.now();
    // Barcode scanner hardware mengetik dengan interval sangat cepat (< 65ms per karakter)
    if (now - lastGunKeyTime > 65) {
        barcodeGunBuffer = '';
    }
    lastGunKeyTime = now;

    if (e.key === 'Enter') {
        if (barcodeGunBuffer.length >= 3) {
            e.preventDefault();
            playScanBeep();
            handleIncomingBarcode(barcodeGunBuffer);
            barcodeGunBuffer = '';
        }
    } else if (e.key.length === 1) {
        barcodeGunBuffer += e.key;
    }
});

// ==============================================================================
// MODUL RENDERING BARCODE & QR KODE FISIK (JSBARCODE & QRCODE)
// ==============================================================================
function renderAllGalleryBarcodes() {
    const jsb = window.JsBarcode || (typeof JsBarcode !== 'undefined' ? JsBarcode : null);
    const qrc = window.QRCode || (typeof QRCode !== 'undefined' ? QRCode : null);

    if (jsb) {
        document.querySelectorAll('.gallery-barcode-svg').forEach(svg => {
            const code = svg.getAttribute('data-barcode');
            if (code) {
                try {
                    jsb(svg, code, {
                        format: "CODE128",
                        width: 1.8,
                        height: 52,
                        displayValue: true,
                        fontSize: 12,
                        margin: 8,
                        lineColor: "#000000",
                        background: "#ffffff"
                    });
                } catch (e) {
                    console.warn("JsBarcode error for:", code, e);
                }
            }
        });
    }

    if (qrc) {
        document.querySelectorAll('.gallery-qr-canvas').forEach(canvas => {
            const code = canvas.getAttribute('data-barcode');
            if (code) {
                try {
                    qrc.toCanvas(canvas, code, {
                        width: 80,
                        margin: 1,
                        color: {
                            dark: '#000000',
                            light: '#ffffff'
                        }
                    });
                } catch (e) {
                    console.warn("QRCode error for:", code, e);
                }
            }
        });
    }
}

function openTestBarcodeGalleryModal() {
    const modalEl = document.getElementById('testBarcodeGalleryModal');
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

let currentSingleBarcodeData = { sku: '', barcode: '', name: '', price: 0 };

function showProductBarcodeModal(sku, barcode, name, price) {
    currentSingleBarcodeData = { sku, barcode, name, price };
    
    const skuBadge = document.getElementById('spbSkuBadge');
    if (skuBadge) skuBadge.textContent = sku;
    const prodName = document.getElementById('spbProductName');
    if (prodName) prodName.textContent = name;
    const priceBadge = document.getElementById('spbPriceBadge');
    if (priceBadge) priceBadge.textContent = price ? `Rp ${Number(price).toLocaleString('id-ID')}` : '';

    const modalEl = document.getElementById('singleProductBarcodeModal');
    if (modalEl) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }

    setTimeout(() => {
        renderSingleBarcode(barcode);
    }, 200);
}

function renderSingleBarcode(barcode) {
    const jsb = window.JsBarcode || (typeof JsBarcode !== 'undefined' ? JsBarcode : null);
    const qrc = window.QRCode || (typeof QRCode !== 'undefined' ? QRCode : null);

    if (jsb) {
        try {
            jsb("#singleBarcodeSvg", barcode, {
                format: "CODE128",
                width: 2.2,
                height: 70,
                displayValue: true,
                fontSize: 14,
                margin: 10,
                lineColor: "#000000",
                background: "#ffffff"
            });
        } catch (e) {
            console.warn("JsBarcode single error", e);
        }
    }

    if (qrc) {
        const canvas = document.getElementById('singleBarcodeQrCanvas');
        if (canvas) {
            try {
                qrc.toCanvas(canvas, barcode, {
                    width: 140,
                    margin: 2,
                    color: {
                        dark: '#000000',
                        light: '#ffffff'
                    }
                });
            } catch (e) {
                console.warn("QRCode single error", e);
            }
        }
    }
}

function testScanCurrentSingleBarcode() {
    if (!currentSingleBarcodeData.barcode) return;
    const modalEl = document.getElementById('singleProductBarcodeModal');
    if (modalEl) {
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
    }
    
    simulateBarcodeScan(currentSingleBarcodeData.barcode, currentSingleBarcodeData.name);
}

// Inisialisasi Keranjang Kasir Awal & Modal Listeners
document.addEventListener('DOMContentLoaded', () => {
    renderPosCart();

    const scannerModalEl = document.getElementById('barcodeScannerModal');
    if (scannerModalEl) {
        scannerModalEl.addEventListener('hidden.bs.modal', () => {
            stopBarcodeScanner();
        });
    }

    // Dismiss GoPay Sheet dengan tombol Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            dismissGopayScanSheet();
        }
    });

    const galleryModalEl = document.getElementById('testBarcodeGalleryModal');
    if (galleryModalEl) {
        galleryModalEl.addEventListener('shown.bs.modal', () => {
            renderAllGalleryBarcodes();
        });
    }

    const singleModalEl = document.getElementById('singleProductBarcodeModal');
    if (singleModalEl) {
        singleModalEl.addEventListener('shown.bs.modal', () => {
            if (currentSingleBarcodeData.barcode) {
                renderSingleBarcode(currentSingleBarcodeData.barcode);
            }
        });
    }
});
</script>
@endsection
