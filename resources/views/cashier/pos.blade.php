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
        height: 280px !important;
        max-height: 280px !important;
        object-fit: cover !important;
        border-radius: 12px !important;
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
        border-radius: 12px;
        overflow: hidden;
        border: 2px solid #1e293b;
        min-height: 220px;
        max-height: 280px;
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
    .pos-barcode-group {
        display: flex;
        align-items: stretch;
        flex-wrap: nowrap;
        gap: 0.5rem;
    }
    .pos-barcode-group > .btn,
    .pos-barcode-group > .form-control {
        height: 42px;
        min-height: 42px;
        line-height: 1.25;
        box-sizing: border-box;
        margin: 0;
        border-radius: 8px !important;
    }
    .pos-barcode-group > .btn {
        flex-shrink: 0;
        white-space: nowrap;
    }
    .pos-barcode-group > .form-control {
        flex: 1 1 auto;
        min-width: 0;
    }
    .tokped-product-card {
        border-radius: 10px;
    }
    .pos-search-dropdown {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        right: 0;
        z-index: 1050;
        background: #ffffff;
        border: 1px solid #d6dce3;
        border-radius: 10px;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.14);
        max-height: 300px;
        overflow-y: auto;
        scrollbar-width: thin;
    }
    .pos-search-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        width: 100%;
        padding: 10px 14px;
        border: 0;
        border-bottom: 1px solid #f1f5f9;
        background: transparent;
        text-align: left;
        cursor: pointer;
    }
    .pos-search-item:last-child {
        border-bottom: 0;
    }
    .pos-search-item:hover,
    .pos-search-item.active {
        background: #e6f7ef;
    }
    .pos-table-qty-wrap {
        display: inline-flex;
        align-items: center;
        border: 1px solid #d6dce3;
        border-radius: 8px;
        overflow: hidden;
        background: #ffffff;
        height: 32px;
    }
    .pos-table-qty-wrap button {
        border: 0 !important;
        background: transparent;
        color: #00aa5b;
        width: 30px;
        height: 30px;
        min-height: 30px !important;
        padding: 0 !important;
        font-weight: 700;
        line-height: 1;
        border-radius: 0 !important;
    }
    .pos-table-qty-wrap button:hover {
        background: #e6f7ef;
    }
    .pos-table-qty-wrap input {
        width: 44px;
        height: 30px;
        min-height: 30px !important;
        border: 0 !important;
        border-left: 1px solid #e5e7e9 !important;
        border-right: 1px solid #e5e7e9 !important;
        border-radius: 0 !important;
        text-align: center;
        font-weight: 700;
        font-size: 0.82rem;
        padding: 0 4px !important;
        -moz-appearance: textfield;
    }
    .pos-table-qty-wrap input::-webkit-outer-spin-button,
    .pos-table-qty-wrap input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    .pos-total-hero {
        background: #e6f7ef;
        border: 1px solid #b5e8cf;
        border-radius: 10px;
        padding: 14px 16px;
    }
    .stock-header-controls .btn,
    .stock-header-controls .input-group,
    .stock-header-controls .input-group-text,
    .stock-header-controls .form-control,
    .pickup-header-controls .form-select,
    .pickup-header-controls .input-group,
    .pickup-header-controls .input-group-text,
    .pickup-header-controls .form-control,
    .pickup-header-controls .btn {
        height: 38px;
        min-height: 38px !important;
        box-sizing: border-box;
    }
    .stock-header-controls .input-group-text,
    .pickup-header-controls .input-group-text {
        border-radius: 8px 0 0 8px !important;
    }
    .stock-header-controls .form-control,
    .pickup-header-controls .form-control {
        border-radius: 0 8px 8px 0 !important;
    }
    .ui-polished .table.stock-monitor-table th {
        padding: 0.6rem 0.75rem !important;
        font-size: 0.72rem;
    }
    .ui-polished .table.stock-monitor-table td {
        padding: 0.5rem 0.75rem !important;
        vertical-align: middle;
    }
    @media (min-width: 992px) {
        .pos-receipt-sticky {
            position: sticky;
            top: 76px;
            max-height: calc(100vh - 96px);
            overflow-y: auto;
            scrollbar-width: thin;
        }
    }
</style>
@endpush

@section('header')
<div>
    <h4 class="fw-bold mb-1 text-dark">Portal Kasir & Operasional Gerai</h4>
    <p class="text-secondary small mb-0">Proses transaksi kasir langsung, verifikasi pesanan pickup, dan pantau ketersediaan stok gerai.</p>
</div>
@endsection

@section('content')
@php
    // Muat katalog produk aktif langsung dari DB tanpa menyertakan kolom HPP (current_cost).
    $safeStockItems = [];
    if (\Illuminate\Support\Facades\Schema::hasTable('products')) {
        $dbProducts = \App\Models\Product::query()
            ->select(['id', 'sku', 'barcode', 'name', 'category', 'selling_price', 'unit'])
            ->with('inventory:product_id,on_hand,reserved')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($dbProducts->isNotEmpty()) {
            $safeStockItems = $dbProducts->map(function ($p) {
                $onHand = (int) ($p->inventory?->on_hand ?? 0);
                $reserved = (int) ($p->inventory?->reserved ?? 0);
                $available = max(0, $onHand - $reserved);

                return [
                    'id' => (string) $p->id,
                    'sku' => (string) $p->sku,
                    'barcode' => (string) ($p->barcode ?? ''),
                    'name' => (string) $p->name,
                    'category' => (string) ($p->category ?? 'Umum'),
                    'sell_price' => (float) $p->selling_price,
                    'on_hand' => $onHand,
                    'reserved' => $reserved,
                    'available' => $available,
                    'unit' => (string) ($p->unit ?? 'Pcs'),
                    'status' => $available > 10 ? 'SAFE' : 'LOW',
                ];
            })->values()->all();
        }
    }

    if (empty($safeStockItems)) {
        $safeStockItems = collect($stockItems ?? [])
            ->map(fn ($item) => \Illuminate\Support\Arr::except((array) $item, ['cost_price', 'current_cost', 'hpp_snapshot']))
            ->values()
            ->all();
    }

    $realStockSummary = [
        'total_sku' => count($safeStockItems),
        'safe_stock_count' => collect($safeStockItems)->where('status', 'SAFE')->count(),
        'low_stock_count' => collect($safeStockItems)->where('status', 'LOW')->count(),
    ];
    $cashierDisplayName = session('user.name', Auth::user()?->member?->full_name ?? Auth::user()?->email ?? 'Petugas Kasir');
@endphp

<!-- RINGKASAN STATUS STOK GERAI (Tokopedia Seller Stat Cards) -->
<div class="row g-3 g-xl-4 mb-4">
    <div class="col-6 col-md-3">
        <div class="tokped-stat-card h-100 d-flex align-items-start justify-content-between gap-2">
            <div class="min-w-0">
                <small class="text-muted fw-semibold d-block mb-1" style="font-size: 0.74rem;">Total SKU Barang</small>
                <h4 class="fw-bold text-dark mb-0 fs-5 tokped-price">{{ $realStockSummary['total_sku'] }} Item</h4>
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
                <h4 class="fw-bold text-success mb-0 fs-5 tokped-price">{{ $realStockSummary['safe_stock_count'] }} SKU</h4>
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
                <h4 class="fw-bold text-danger mb-0 fs-5 tokped-price">{{ $realStockSummary['low_stock_count'] }} SKU</h4>
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
                <h4 class="fw-bold text-dark mb-0 fs-5 tokped-price" id="statPickupOrdersCount">0 Pesanan</h4>
                <small class="text-primary d-block text-truncate mt-1" id="statPickupOrdersSub" style="font-size: 0.7rem;"><i class="bi bi-bag-check-fill me-1"></i>Memuat antrian...</small>
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

<div class="tab-content" id="cashierTabContent">
    
    <!-- MONITORING STOK BARANG REAL-TIME (Sidebar: Monitoring Stok Barang) -->
    <div class="tab-pane fade {{ $activeTab === 'stock' ? 'show active' : '' }}" id="stock-pane" role="tabpanel">
        <div class="card border-0 shadow-sm bg-white mb-4 overflow-hidden">
            <div class="card-header bg-white px-3 px-md-4 py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="min-w-0">
                    <h5 class="card-title fw-bold text-dark mb-0 fs-6">Master Persediaan & Stok Barang Gerai</h5>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap flex-sm-nowrap stock-header-controls">
                    <div class="input-group input-group-sm" style="width: 240px; max-width: 100%;">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" id="stockSearchInput" class="form-control" placeholder="Cari SKU atau nama barang..." oninput="filterCashierStock(this.value)">
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 stock-monitor-table" style="font-size: 0.82rem;">
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
                        @foreach($safeStockItems as $item)
                            <tr class="stock-item-row" data-name="{{ strtolower($item['name']) }}" data-sku="{{ strtolower($item['sku']) }}" data-barcode="{{ strtolower($item['barcode'] ?? '') }}">
                                <td class="py-2">
                                    <div class="d-flex align-items-center gap-1.5">
                                        <span class="fw-bold font-monospace text-dark">{{ $item['sku'] }}</span>
                                        <button type="button" class="btn btn-sm btn-link p-0 text-primary" style="min-height: 22px;" onclick="showProductBarcodeModal('{{ $item['sku'] }}', '{{ $item['barcode'] }}', '{{ addslashes($item['name']) }}', {{ $item['sell_price'] }})" title="Lihat Barcode Fisik & QR">
                                            <i class="bi bi-upc-scan" style="font-size: 0.82rem;"></i>
                                        </button>
                                    </div>
                                    @if(!empty($item['barcode']))
                                        <small class="text-muted d-block font-monospace lh-1 mt-0.5" style="font-size: 0.7rem;">{{ $item['barcode'] }}</small>
                                    @endif
                                </td>
                                <td class="py-2">
                                    <span class="fw-semibold text-dark">{{ $item['name'] }}</span>
                                </td>
                                <td class="py-2">
                                    <span class="badge bg-light text-secondary border">{{ $item['category'] }}</span>
                                </td>
                                <td class="py-2 text-center fw-bold">{{ $item['on_hand'] }} {{ $item['unit'] }}</td>
                                <td class="py-2 text-center text-primary fw-bold">{{ $item['reserved'] }} {{ $item['unit'] }}</td>
                                <td class="py-2 text-center">
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 fw-bold" style="font-size: 0.75rem;">
                                        {{ $item['available'] }} {{ $item['unit'] }}
                                    </span>
                                </td>
                                <td class="py-2 text-end fw-bold text-dark text-nowrap tokped-price">
                                    Rp {{ number_format($item['sell_price'], 0, ',', '.') }}
                                </td>
                                <td class="py-2 text-center">
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

    <!-- POINT OF SALE (POS) TRANSAKSI KASIR MINIMARKET -->
    <div class="tab-pane fade {{ $activeTab === 'pos' ? 'show active' : '' }}" id="pos-pane" role="tabpanel">
        <div class="row g-3 g-lg-4 align-items-start">
            <!-- Left (col-lg-8): Area Scan Barcode & Daftar Belanjaan Kasir -->
            <div class="col-12 col-lg-8">
                <!-- Baris Atas: Card Scan Barcode & Pencarian Barang -->
                <div class="card border-0 shadow-sm bg-white p-3 p-md-4 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold text-dark mb-0 small">
                            <i class="bi bi-upc-scan text-primary me-1"></i> Scan Barcode & Pencarian Barang
                        </h6>
                        <span class="badge bg-light text-secondary border" style="font-size: 0.68rem;">
                            <i class="bi bi-shield-check text-success me-0.5"></i> Mode Kasir Langsung
                        </span>
                    </div>

                    <div class="position-relative" id="posSearchContainer">
                        <div class="d-flex align-items-stretch gap-2 pos-barcode-group mb-2">
                            <!-- Tombol Buka Kamera Scanner Nyata (html5-qrcode) -->
                            <button type="button" class="btn btn-outline-primary rounded-3 fw-semibold d-inline-flex align-items-center justify-content-center gap-1.5 px-3" onclick="openBarcodeScannerModal()" title="Buka Kamera Barcode Scanner">
                                <i class="bi bi-camera-fill"></i>
                                <span class="d-none d-sm-inline">Scan Kamera</span>
                            </button>
                            <input type="text" id="posBarcodeField" class="form-control rounded-3" placeholder="Scan barcode, SKU, atau ketik nama barang..." autofocus autocomplete="off" oninput="onPosSearchInput(this.value)" onkeydown="onPosSearchKeydown(event)">
                            <button type="button" class="btn btn-primary rounded-3 fw-semibold px-3 d-inline-flex align-items-center justify-content-center" onclick="submitManualBarcodeField()">
                                + Tambah
                            </button>
                        </div>
                        <div id="posSearchDropdown" class="pos-search-dropdown d-none" role="listbox" aria-label="Hasil pencarian cepat barang"></div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-1 text-muted" style="font-size: 0.72rem;">
                        <span><i class="bi bi-info-circle me-1"></i>Scan barcode langsung, tekan <strong>Enter</strong>, atau ketik nama barang untuk memilih cepat.</span>
                        <span class="text-primary fw-semibold">Support Scanner USB / Kamera</span>
                    </div>
                </div>

                <!-- Bagian Utama: Daftar Belanjaan Kasir (Tabel Transaksi) -->
                <div class="card border-0 shadow-sm bg-white overflow-hidden">
                    <div class="card-header bg-white px-3 px-md-4 py-3 border-bottom d-flex justify-content-between align-items-center gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-cart-check-fill text-success fs-5"></i>
                            <div>
                                <h6 class="fw-bold text-dark mb-0 small">Daftar Belanjaan Kasir</h6>
                                <small class="text-muted" style="font-size: 0.72rem;">Daftar item transaksi aktif di meja kasir</small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-light text-dark border font-monospace" id="posTableCountBadge">0 item</span>
                            <button type="button" class="btn btn-outline-danger btn-sm pos-btn-compact" onclick="clearPosCart()" title="Kosongkan Daftar Belanjaan">
                                <i class="bi bi-trash3 me-1"></i>Reset
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="posCartTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3 text-center" style="width: 48px;">No</th>
                                    <th style="width: 155px;">SKU / Barcode</th>
                                    <th>Nama Barang</th>
                                    <th class="text-end" style="width: 115px;">Harga</th>
                                    <th class="text-center" style="width: 130px;">Qty</th>
                                    <th class="text-end" style="width: 125px;">Subtotal</th>
                                    <th class="text-center pe-3" style="width: 72px;">Hapus</th>
                                </tr>
                            </thead>
                            <tbody id="posCartTableBody">
                                <!-- Injected via JavaScript renderPosCart() -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right (col-lg-4): Panel Pembayaran & Ringkasan (Sticky) -->
            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm bg-white p-3 p-md-4 pos-receipt-sticky">
                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-2 d-flex justify-content-between align-items-center">
                        <span class="d-flex align-items-center gap-1.5">
                            <i class="bi bi-receipt text-primary"></i>
                            <span>Keranjang Meja Kasir</span>
                        </span>
                        <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.7rem;">#POS-9421</span>
                    </h6>

                    <!-- Total Tagihan Box Besar -->
                    <div class="pos-total-hero mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-uppercase fw-bold text-success" style="font-size: 0.72rem; letter-spacing: 0.04em;">Total Tagihan</span>
                            <span class="badge bg-white text-secondary border" style="font-size: 0.68rem;">PPN 0%</span>
                        </div>
                        <div class="fs-2 fw-bold text-dark tokped-price lh-sm" id="posTotalDisplay">Rp 0</div>
                        <div class="text-muted mt-1" style="font-size: 0.74rem;" id="posItemsCountDisplay">0 item</div>
                    </div>

                    <!-- Payment Method Selector -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary mb-1">Metode Pembayaran:</label>
                        <select class="form-select form-select-sm mb-2 fw-semibold text-dark" id="posPaymentMethod" onchange="onPosPaymentMethodChanged(this.value)">
                            <option value="CASH">1. Tunai di Kasir (Cash)</option>
                            <option value="BANK_TRANSFER">2. Transfer Bank / QRIS Gerai</option>
                            <option value="CREDIT">3. Piutang Anggota (Tempo 7 Hari Bunga 0%)</option>
                        </select>

                        <!-- A. CASH PAYMENT CONTAINER -->
                        <div id="posCashContainer">
                            <label class="form-label text-muted d-block mb-1" style="font-size: 0.72rem;">Nominal Diterima dari Pembeli:</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light small">Diterima Rp</span>
                                <input type="number" class="form-control fw-bold font-monospace" id="posCashReceived" value="0" min="0" oninput="calculatePosChange(); validatePosTransaction();">
                            </div>
                            <div class="d-flex flex-wrap gap-2 mt-2 mb-2">
                                <button type="button" class="btn btn-light border btn-sm pos-btn-compact text-secondary" onclick="setExactCash()">Uang Pas</button>
                                <button type="button" class="btn btn-light border btn-sm pos-btn-compact text-secondary" onclick="setCashAmount(50000)">Rp 50k</button>
                                <button type="button" class="btn btn-light border btn-sm pos-btn-compact text-secondary" onclick="setCashAmount(100000)">Rp 100k</button>
                                <button type="button" class="btn btn-light border btn-sm pos-btn-compact text-secondary" onclick="setCashAmount(150000)">Rp 150k</button>
                                <button type="button" class="btn btn-light border btn-sm pos-btn-compact text-secondary" onclick="setCashAmount(200000)">Rp 200k</button>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-2.5 rounded-2 bg-light border small">
                                <span class="text-secondary fw-semibold">Kembalian:</span>
                                <span class="fw-bold text-success fs-5 font-monospace" id="posChangeDisplay">Rp 0</span>
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
                                <label class="form-label text-muted d-block mb-1" style="font-size: 0.72rem;">Cari Anggota Koperasi (NIK / No. Anggota / UUID):</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control font-monospace text-uppercase" id="posMemberSearchInput" placeholder="Contoh: EMP-001 atau MEM-001" onkeydown="if(event.key==='Enter'){event.preventDefault();lookupPosCreditMember();}">
                                    <button type="button" class="btn btn-outline-primary fw-semibold" id="btnLookupPosMember" onclick="lookupPosCreditMember()">
                                        Cek Limit
                                    </button>
                                </div>
                                <input type="hidden" id="posMemberSelect" value="">
                                <small id="posMemberLookupError" class="text-danger d-none mt-1" style="font-size: 0.7rem;"></small>
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

                    <!-- Alert Error POS -->
                    <div id="posErrorAlert" class="alert alert-danger py-2 px-2.5 small d-none mb-2" role="alert"></div>

                    <!-- Button Selesaikan Transaksi -->
                    <button type="button" class="btn btn-success w-100 py-2.5 fw-semibold d-inline-flex align-items-center justify-content-center gap-1.5 shadow-xs" id="btnCompletePosSale" onclick="openPosReceiptModal()">
                        <i class="bi bi-printer"></i>
                        <span>Selesaikan Transaksi & Cetak Struk</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- VERIFIKASI ANTRIAN PICKUP ONLINE (Live API /api/v1/cashier/orders) -->
    <div class="tab-pane fade {{ $activeTab === 'pickup' ? 'show active' : '' }}" id="pickup-pane" role="tabpanel">
        <div class="card border-0 shadow-sm bg-white p-3 p-md-4 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3 pb-3 border-bottom">
                <div class="min-w-0">
                    <h5 class="card-title fw-bold text-dark mb-0 fs-6">Daftar Antrian Pengambilan Barang (Pickup)</h5>
                    <small class="text-muted" style="font-size: 0.75rem;">Data langsung dari server. Cocokkan kode pickup sebelum menyerahkan barang.</small>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2 pickup-header-controls">
                    <select id="pickupStatusFilter" class="form-select form-select-sm" style="width: auto; min-width: 175px;" onchange="loadCashierOrders()">
                        <option value="">Semua Antrian Aktif</option>
                        <option value="PENDING_PAYMENT">Menunggu Pembayaran</option>
                        <option value="PENDING_PICKUP">Siap Diambil (Kredit)</option>
                        <option value="READY_FOR_PICKUP">Siap Diambil (Lunas)</option>
                    </select>
                    <div class="input-group input-group-sm" style="width: 210px; max-width: 100%;">
                        <span class="input-group-text bg-light"><i class="bi bi-qr-code"></i></span>
                        <input type="text" id="pickupFilterInput" class="form-control font-monospace text-uppercase" placeholder="Cari PKP- / ORD-..." oninput="filterPickupQueue(this.value)">
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1.5 text-nowrap flex-shrink-0 px-3" onclick="loadCashierOrders()">
                        <i class="bi bi-arrow-clockwise"></i>
                        <span>Muat Ulang</span>
                    </button>
                </div>
            </div>

            <div id="pickupQueueErrorAlert" class="alert alert-danger mb-3 py-2 px-3 small d-none" role="alert"></div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                    <thead class="table-light">
                        <tr>
                            <th>Kode Pickup</th>
                            <th>Order & Anggota</th>
                            <th>Rincian Barang</th>
                            <th>Total Bayar</th>
                            <th>Metode & Pembayaran</th>
                            <th class="text-end">Aksi Serah Terima</th>
                        </tr>
                    </thead>
                    <tbody id="pickupQueueTableBody">
                        <tr id="pickupLoadingRow">
                            <td colspan="6" class="text-center py-4 text-muted small">
                                <span class="spinner-border spinner-border-sm me-1 text-primary" role="status"></span>
                                Memuat antrian pesanan dari server...
                            </td>
                        </tr>
                        <tr id="pickupEmptyRow" class="d-none">
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-3 d-block text-secondary opacity-50 mb-1"></i>
                                <span class="small">Tidak ada antrean pesanan pickup yang aktif.</span>
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
        <div class="modal-content border-0 shadow-lg overflow-hidden">
            <div class="modal-header bg-dark text-white py-3 px-3 px-md-4 d-flex justify-content-between align-items-center gap-2">
                <div class="d-flex align-items-center gap-2 min-w-0">
                    <div class="p-1.5 rounded-2 bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px;">
                        <i class="bi bi-upc-scan fs-6"></i>
                    </div>
                    <div class="min-w-0">
                        <h6 class="modal-title fw-bold mb-0 fs-6 text-truncate" id="barcodeScannerModalLabel">Scanner Barcode Kamera Gerai KOPEG</h6>
                        <small class="text-white-50 d-block text-truncate" style="font-size: 0.72rem;">Mendukung EAN-13, EAN-8, Code-128, QR Code, dan File Gambar</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                    <span id="sessionScanCountBadge" class="badge bg-success-subtle text-success border border-success-subtle fw-bold text-nowrap" style="font-size: 0.72rem;">
                        0 Barang Ter-scan
                    </span>
                    <button type="button" class="btn-close btn-close-white m-0" data-bs-dismiss="modal" aria-label="Tutup" onclick="stopBarcodeScanner()"></button>
                </div>
            </div>

            <div class="modal-body p-3 p-md-4">
                <!-- 1. Scanner Viewport Container with Real-time Laser Line -->
                <div class="scanner-viewport-box" id="scannerViewportBox">
                    <div id="qr-reader" style="width: 100%;"></div>
                    <!-- Animated Laser Beam -->
                    <div class="scanner-laser-line" id="scannerLaserBeam"></div>
                    <!-- Reticle Frame -->
                    <div class="scanner-reticle" id="scannerReticle"></div>
                    <!-- Green Flash Effect on Hit -->
                    <div class="scanner-flash-overlay" id="scannerFlashOverlay"></div>
                </div>

                <!-- 2. Status & Controls Row -->
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                    <!-- Status Banner -->
                    <div id="scannerStatusAlert" class="alert alert-info py-2 px-3 small d-flex align-items-center gap-2 mb-0 flex-grow-1" style="min-width: 240px;">
                        <div class="spinner-border spinner-border-sm text-primary flex-shrink-0" id="scannerSpinner" role="status"></div>
                        <span id="scannerStatusText">Mengaktifkan modul kamera... Arahkan barcode ke kotak bidik.</span>
                    </div>

                    <!-- Mode Scan Beruntun (Multi-Scan Switch) -->
                    <div class="form-check form-switch small mb-0 px-3 py-2 bg-light rounded-3 border flex-shrink-0 d-flex align-items-center gap-2" title="Secara default, scan barcode akan langsung keluar otomatis. Aktifkan jika ingin scan banyak barang tanpa menutup kamera.">
                        <input class="form-check-input m-0" type="checkbox" id="toggleMultiScan">
                        <label class="form-check-label fw-semibold text-dark mb-0" for="toggleMultiScan" style="font-size: 0.76rem; cursor: pointer;">
                            Scan Beruntun (Tetap di Kamera)
                        </label>
                    </div>
                </div>

                <!-- 3. Error Banner: Ramah jika akses ditolak atau tidak ada kamera -->
                <div id="scannerErrorAlert" class="alert alert-danger py-2.5 px-3 small d-none mt-3 mb-0">
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

                <!-- Hidden file input & temp reader untuk scan gambar barcode -->
                <input type="file" id="barcodeFileInput" class="d-none" accept="image/*" onchange="handleBarcodeFile(this)">
                <div id="qr-reader-file-temp" class="d-none"></div>
            </div>

            <div class="modal-footer bg-light py-2.5 px-3 px-md-4 d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1.5" id="btnSwitchCamera" onclick="switchScannerCamera()">
                        <i class="bi bi-arrow-repeat"></i>
                        <span>Ganti Kamera</span>
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1.5" onclick="document.getElementById('barcodeFileInput').click()">
                        <i class="bi bi-image"></i>
                        <span>Scan dari Gambar</span>
                    </button>
                </div>
                <button type="button" class="btn btn-secondary btn-sm px-3 fw-semibold" data-bs-dismiss="modal" onclick="stopBarcodeScanner()">
                    Tutup
                </button>
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
                            <span>No: <strong class="text-dark" id="receiptOrderNo">-</strong></span>
                            <span id="receiptDate">-</span>
                        </div>
                    </div>

                    <!-- Kasir & Pelanggan Info -->
                    <div class="mb-2 pb-2 border-bottom border-dashed">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Kasir:</span>
                            <span class="fw-bold text-dark">{{ $cashierDisplayName }}</span>
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

                <div class="form-check p-2.5 rounded-2 bg-warning bg-opacity-10 border border-warning border-opacity-25 mb-3">
                    <input class="form-check-input ms-0 me-2" type="checkbox" id="checkPhysicalHandover" checked>
                    <label class="form-check-label text-dark small fw-semibold" for="checkPhysicalHandover">
                        Fisik sembako sudah diperiksa dan diserahkan lengkap ke pembeli.
                    </label>
                    <small class="text-muted d-block mt-0.5" style="font-size: 0.68rem;">Konfirmasi ini akan memotong stok fisik on-hand gerai secara permanen.</small>
                </div>
                <div id="pickupHandoverErrorAlert" class="alert alert-danger py-2 px-2.5 small d-none mb-0" role="alert"></div>
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

                <div id="vtfErrorAlert" class="alert alert-danger py-2 px-2.5 small d-none mt-2 mb-2" role="alert"></div>

                <!-- Box Alasan Penolakan (Collapse jika kasir ingin menolak) -->
                <div id="vtfRejectBox" class="p-2.5 rounded-2 bg-danger bg-opacity-10 border border-danger border-opacity-25 mt-2 d-none">
                    <label class="form-label small fw-bold text-danger mb-1">Alasan Penolakan Bukti Transfer:</label>
                    <select class="form-select form-select-sm mb-2" id="vtfRejectReason">
                        <option value="Dana belum masuk ke mutasi rekening koperasi">Dana belum masuk ke mutasi rekening koperasi</option>
                        <option value="Nominal pada bukti transfer tidak sesuai total pesanan">Nominal pada bukti transfer tidak sesuai total pesanan</option>
                        <option value="Bukti transfer tidak jelas / terindikasi palsu">Bukti transfer tidak jelas / terindikasi palsu</option>
                        <option value="Rekening tujuan salah (bukan rekening resmi koperasi)">Rekening tujuan salah (bukan rekening resmi koperasi)</option>
                    </select>
                    <button type="button" class="btn btn-danger btn-sm w-100 fw-semibold" id="btnExecuteRejectTransfer" onclick="executeRejectTransfer()">
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
                    <button type="button" class="btn btn-success btn-sm fw-semibold px-3" id="btnExecuteApproveTransfer" onclick="executeApproveTransfer()">
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
                        <strong>Ketentuan Pembatalan Pesanan:</strong>
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
                <div id="cancelOrderErrorAlert" class="alert alert-danger py-2 px-2.5 small d-none mb-0 mt-2" role="alert"></div>
            </div>
            <div class="modal-footer bg-light py-2 px-3 justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
                    Batal (Kembali)
                </button>
                <button type="button" class="btn btn-danger btn-sm fw-semibold px-3" id="btnExecuteCancelOrder" onclick="executeCancelOrder()">
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
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
const stockCatalogue = @json($safeStockItems);
let posCart = [];
let selectedPosCreditMember = null;
let currentPosIdempotencyKey = null;
let liveCashierOrders = [];
let selectedPickupOrder = null;
let currentVerifyTransfer = null;
let currentCancelOrder = null;

function escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function generateIdemKey(prefix) {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
        return `${prefix}-${window.crypto.randomUUID()}`;
    }
    return `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

async function apiRequest(url, method = 'GET', payload = null) {
    const options = {
        method,
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
    };
    if (payload !== null) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(payload);
    }

    const res = await fetch(url, options);
    let data = {};
    try {
        data = await res.json();
    } catch (_) {
        throw new Error(`Respons server tidak valid (HTTP ${res.status}).`);
    }

    if (!res.ok || data.success === false) {
        throw new Error(data.message || `Permintaan ditolak oleh server (HTTP ${res.status}).`);
    }

    return data;
}

function showInlineAlert(elementId, message) {
    const el = document.getElementById(elementId);
    if (!el) return;
    el.textContent = message;
    el.classList.remove('d-none');
}

function hideInlineAlert(elementId) {
    const el = document.getElementById(elementId);
    if (!el) return;
    el.textContent = '';
    el.classList.add('d-none');
}

// Keranjang kasir minimarket (tabel transaksi) dan kalkulasi total
let posSearchMatches = [];
let posSearchActiveIndex = -1;

function hidePosSearchDropdown() {
    const dropdown = document.getElementById('posSearchDropdown');
    if (dropdown) {
        dropdown.classList.add('d-none');
        dropdown.innerHTML = '';
    }
    posSearchMatches = [];
    posSearchActiveIndex = -1;
}

function renderPosSearchDropdown() {
    const dropdown = document.getElementById('posSearchDropdown');
    if (!dropdown) return;

    if (posSearchMatches.length === 0) {
        dropdown.innerHTML = `
            <div class="px-3 py-2.5 text-muted small">
                <i class="bi bi-search me-1"></i> Tidak ada barang cocok di daftar cepat. Tekan <strong>Enter</strong> untuk lookup barcode ke server.
            </div>
        `;
        dropdown.classList.remove('d-none');
        return;
    }

    dropdown.innerHTML = posSearchMatches.map((item, idx) => {
        const isActive = idx === posSearchActiveIndex ? 'active' : '';
        const avail = Number(item.available || 0);
        const stockClass = avail <= 0 ? 'text-danger' : (avail <= 10 ? 'text-warning' : 'text-success');
        const codeLabel = item.barcode ? `${escapeHtml(item.sku)} &bull; ${escapeHtml(item.barcode)}` : escapeHtml(item.sku);
        return `
            <button type="button" class="pos-search-item ${isActive}" role="option" aria-selected="${idx === posSearchActiveIndex}" onmousedown="event.preventDefault(); selectPosSearchItem(${idx});">
                <div class="min-w-0">
                    <div class="fw-semibold text-dark small text-truncate">${escapeHtml(item.name)}</div>
                    <div class="text-muted font-monospace" style="font-size: 0.72rem;">${codeLabel} &bull; <span class="${stockClass} fw-semibold">Stok ${avail}</span></div>
                </div>
                <span class="fw-bold text-dark tokped-price text-nowrap small">Rp ${Number(item.sell_price || 0).toLocaleString('id-ID')}</span>
            </button>
        `;
    }).join('');
    dropdown.classList.remove('d-none');
}

function onPosSearchInput(rawVal) {
    const q = (rawVal || '').trim().toLowerCase();
    if (!q) {
        hidePosSearchDropdown();
        return;
    }

    posSearchMatches = stockCatalogue.filter(item =>
        (item.name && String(item.name).toLowerCase().includes(q)) ||
        (item.sku && String(item.sku).toLowerCase().includes(q)) ||
        (item.barcode && String(item.barcode).toLowerCase().includes(q))
    ).slice(0, 8);
    posSearchActiveIndex = posSearchMatches.length > 0 ? 0 : -1;
    renderPosSearchDropdown();
}

function onPosSearchKeydown(event) {
    const dropdown = document.getElementById('posSearchDropdown');
    const isDropdownOpen = dropdown && !dropdown.classList.contains('d-none') && posSearchMatches.length > 0;

    if (event.key === 'ArrowDown' && isDropdownOpen) {
        event.preventDefault();
        posSearchActiveIndex = (posSearchActiveIndex + 1) % posSearchMatches.length;
        renderPosSearchDropdown();
        return;
    }
    if (event.key === 'ArrowUp' && isDropdownOpen) {
        event.preventDefault();
        posSearchActiveIndex = (posSearchActiveIndex - 1 + posSearchMatches.length) % posSearchMatches.length;
        renderPosSearchDropdown();
        return;
    }
    if (event.key === 'Escape') {
        hidePosSearchDropdown();
        return;
    }
    if (event.key === 'Enter') {
        event.preventDefault();
        const inputVal = (document.getElementById('posBarcodeField')?.value || '').trim().toLowerCase();
        const exactMatch = stockCatalogue.find(item =>
            (item.barcode && String(item.barcode).toLowerCase() === inputVal) ||
            (item.sku && String(item.sku).toLowerCase() === inputVal)
        );
        if (exactMatch || inputVal.startsWith('pkp-') || inputVal.startsWith('ord-')) {
            submitManualBarcodeField();
            return;
        }
        if (isDropdownOpen && posSearchActiveIndex >= 0 && posSearchMatches[posSearchActiveIndex]) {
            selectPosSearchItem(posSearchActiveIndex);
            return;
        }
        submitManualBarcodeField();
    }
}

function selectPosSearchItem(index) {
    const item = posSearchMatches[index];
    if (!item) return;

    const field = document.getElementById('posBarcodeField');
    if (field) {
        field.value = '';
        field.focus();
    }
    hidePosSearchDropdown();
    playGopayChime();
    handleIncomingBarcode(item.barcode || item.sku, { source: 'dropdown', showGopaySheet: false });
}

document.addEventListener('click', (e) => {
    const container = document.getElementById('posSearchContainer');
    if (container && !container.contains(e.target)) {
        hidePosSearchDropdown();
    }
});

function renderPosCart() {
    const tbody = document.getElementById('posCartTableBody');
    const tableBadge = document.getElementById('posTableCountBadge');
    if (!tbody) return;

    if (posCart.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-5 text-muted">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light border mb-2" style="width: 56px; height: 56px;">
                        <i class="bi bi-upc-scan fs-3 text-success"></i>
                    </div>
                    <div class="fw-semibold text-dark small mb-1">Belum ada barang yang di-scan</div>
                    <div class="small text-muted mx-auto" style="max-width: 420px; font-size: 0.76rem;">
                        Arahkan kursor ke kolom scan di atas, gunakan barcode scanner USB / <strong>Scan Kamera</strong>, atau ketik nama maupun SKU barang untuk menambahkan item ke tabel transaksi.
                    </div>
                </td>
            </tr>
        `;
        if (tableBadge) tableBadge.textContent = '0 item';
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
        const barcodeLine = item.barcode
            ? `<small class="text-muted font-monospace d-block" style="font-size: 0.68rem;">${escapeHtml(item.barcode)}</small>`
            : '';

        html += `
            <tr>
                <td class="ps-3 text-center text-muted fw-semibold small">${index + 1}</td>
                <td>
                    <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.72rem;">${escapeHtml(item.sku)}</span>
                    ${barcodeLine}
                </td>
                <td>
                    <div class="fw-semibold text-dark small">${escapeHtml(item.name)}</div>
                </td>
                <td class="text-end text-nowrap tokped-price small">
                    Rp ${item.price.toLocaleString('id-ID')}
                </td>
                <td class="text-center">
                    <div class="pos-table-qty-wrap">
                        <button type="button" onclick="updatePosQty(${index}, -1)" aria-label="Kurangi jumlah">&minus;</button>
                        <input type="number" min="1" value="${item.qty}" aria-label="Jumlah ${escapeHtml(item.name)}" onchange="setPosQty(${index}, this.value)">
                        <button type="button" onclick="updatePosQty(${index}, 1)" aria-label="Tambah jumlah">+</button>
                    </div>
                </td>
                <td class="text-end fw-bold text-dark text-nowrap tokped-price small">
                    Rp ${subtotal.toLocaleString('id-ID')}
                </td>
                <td class="text-center pe-3">
                    <button type="button" class="btn btn-link pos-trash-btn d-inline-flex align-items-center justify-content-center" onclick="removePosItem(${index})" title="Hapus item" aria-label="Hapus item">
                        <i class="bi bi-trash3"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
    if (tableBadge) tableBadge.textContent = `${totalItems} item (${posCart.length} SKU)`;
    document.getElementById('posTotalDisplay').textContent = `Rp ${total.toLocaleString('id-ID')}`;
    document.getElementById('posItemsCountDisplay').textContent = `${totalItems} barang (${posCart.length} SKU)`;

    if (selectedPosCreditMember) {
        renderPosCreditMemberCard();
    }
    calculatePosChange();
    validatePosTransaction();
}

function posAddToCart(name, price, sku = 'SKU-CUSTOM', productId = null, barcode = '') {
    currentPosIdempotencyKey = null;
    hideInlineAlert('posErrorAlert');

    let resolvedBarcode = barcode || '';
    if (!resolvedBarcode && Array.isArray(stockCatalogue)) {
        const found = stockCatalogue.find(i => (productId && i.id === productId) || i.sku === sku);
        if (found && found.barcode) {
            resolvedBarcode = String(found.barcode);
        }
    }

    const existing = posCart.find(i => (productId && i.productId === productId) || i.sku === sku);
    if (existing) {
        existing.qty += 1;
        if (!existing.barcode && resolvedBarcode) {
            existing.barcode = resolvedBarcode;
        }
    } else {
        posCart.push({ productId, sku, barcode: resolvedBarcode, name, price: Number(price), qty: 1 });
    }
    renderPosCart();
}

function setPosQty(index, rawValue) {
    if (!posCart[index]) return;
    currentPosIdempotencyKey = null;
    hideInlineAlert('posErrorAlert');
    const parsed = parseInt(rawValue, 10);
    if (isNaN(parsed) || parsed <= 0) {
        posCart.splice(index, 1);
    } else {
        posCart[index].qty = parsed;
    }
    renderPosCart();
}

function updatePosQty(index, delta) {
    if (posCart[index]) {
        currentPosIdempotencyKey = null;
        hideInlineAlert('posErrorAlert');
        posCart[index].qty += delta;
        if (posCart[index].qty <= 0) {
            posCart.splice(index, 1);
        }
        renderPosCart();
    }
}

function removePosItem(index) {
    if (posCart[index]) {
        currentPosIdempotencyKey = null;
        hideInlineAlert('posErrorAlert');
        posCart.splice(index, 1);
        renderPosCart();
    }
}

function clearPosCart() {
    posCart = [];
    currentPosIdempotencyKey = null;
    hideInlineAlert('posErrorAlert');
    renderPosCart();
    document.getElementById('posBarcodeField')?.focus();
}

function getPosTotal() {
    return posCart.reduce((sum, item) => sum + (item.price * item.qty), 0);
}

function calculatePosChange() {
    const total = getPosTotal();
    const received = parseInt(document.getElementById('posCashReceived').value, 10) || 0;
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
    document.getElementById('posCashReceived').value = getPosTotal();
    calculatePosChange();
    validatePosTransaction();
}

function setCashAmount(amt) {
    document.getElementById('posCashReceived').value = amt;
    calculatePosChange();
    validatePosTransaction();
}

// Dinamika metode pembayaran & cek kelayakan piutang anggota via API
function onPosPaymentMethodChanged(method) {
    currentPosIdempotencyKey = null;
    hideInlineAlert('posErrorAlert');
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
        if (selectedPosCreditMember) {
            renderPosCreditMemberCard();
        }
    }

    validatePosTransaction();
}

async function lookupPosCreditMember() {
    const input = document.getElementById('posMemberSearchInput');
    const query = (input?.value || '').trim();
    const errEl = document.getElementById('posMemberLookupError');
    const hiddenMember = document.getElementById('posMemberSelect');
    const card = document.getElementById('posMemberCreditCard');
    const btn = document.getElementById('btnLookupPosMember');

    if (errEl) {
        errEl.textContent = '';
        errEl.classList.add('d-none');
    }

    if (!query) {
        selectedPosCreditMember = null;
        if (hiddenMember) hiddenMember.value = '';
        if (card) card.classList.add('d-none');
        validatePosTransaction();
        return;
    }

    if (btn) btn.disabled = true;
    try {
        const res = await apiRequest(`/api/v1/cashier/receivables?member_id=${encodeURIComponent(query)}`);
        const member = res.data.member;
        const summary = res.data.credit_summary;

        selectedPosCreditMember = {
            id: member.id,
            code: member.employee_number || member.member_number || member.id,
            name: member.full_name,
            unit: member.department || 'Anggota Koperasi',
            credit_account_status: summary.credit_account_status,
            available_limit: Number(summary.available_limit || 0),
            has_overdue: Boolean(summary.has_overdue),
            eligible_for_credit: Boolean(summary.eligible_for_credit),
        };

        if (hiddenMember) hiddenMember.value = member.id;
        renderPosCreditMemberCard();
    } catch (err) {
        selectedPosCreditMember = null;
        if (hiddenMember) hiddenMember.value = '';
        if (card) card.classList.add('d-none');
        if (errEl) {
            errEl.textContent = err.message || 'Anggota tidak ditemukan.';
            errEl.classList.remove('d-none');
        }
    } finally {
        if (btn) btn.disabled = false;
        validatePosTransaction();
    }
}

function renderPosCreditMemberCard() {
    const card = document.getElementById('posMemberCreditCard');
    const alertBox = document.getElementById('cardCreditAlert');
    const member = selectedPosCreditMember;

    if (!member || !card) {
        if (card) card.classList.add('d-none');
        return;
    }

    card.classList.remove('d-none');
    document.getElementById('cardMemberName').textContent = `${member.name} (${member.code})`;
    document.getElementById('cardMemberUnit').textContent = member.unit;

    const badge = document.getElementById('cardMemberStatusBadge');
    const isAccountActive = member.credit_account_status === 'ACTIVE';
    if (isAccountActive) {
        badge.className = 'badge bg-success-subtle text-success border border-success-subtle';
        badge.textContent = 'AKTIF TERVERIFIKASI';
    } else {
        badge.className = 'badge bg-danger text-white';
        badge.textContent = 'DIBEKUKAN';
    }

    const overdueEl = document.getElementById('cardMemberOverdueStatus');
    if (member.has_overdue) {
        overdueEl.className = 'fw-bold text-danger';
        overdueEl.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-0.5"></i> Ada Tunggakan Overdue`;
    } else {
        overdueEl.className = 'fw-bold text-success';
        overdueEl.innerHTML = `<i class="bi bi-check-circle me-0.5"></i> Lancar (0 Tunggakan)`;
    }

    document.getElementById('cardMemberLimitDisplay').textContent = `Rp ${member.available_limit.toLocaleString('id-ID')}`;

    const totalPos = getPosTotal();
    alertBox.className = 'alert py-2 px-2.5 mb-0 small';

    if (!isAccountActive) {
        alertBox.classList.add('alert-danger');
        alertBox.innerHTML = `<strong>Kredit Ditolak:</strong> Fasilitas piutang anggota sedang diblokir manajer.`;
    } else if (member.has_overdue) {
        alertBox.classList.add('alert-danger');
        alertBox.innerHTML = `<strong>Kredit Ditolak:</strong> Anggota memiliki tagihan lewat jatuh tempo. Lunasi tunggakan terlebih dahulu.`;
    } else if (member.available_limit < totalPos) {
        alertBox.classList.add('alert-danger');
        alertBox.innerHTML = `<strong>Kredit Ditolak:</strong> Sisa limit (Rp ${member.available_limit.toLocaleString('id-ID')}) tidak mencukupi tagihan (Rp ${totalPos.toLocaleString('id-ID')}).`;
    } else {
        alertBox.classList.add('alert-success');
        alertBox.innerHTML = `<strong>Fasilitas Disetujui:</strong> Sisa limit mencukupi. Jatuh tempo: 7 hari kalender.`;
    }
}

function validatePosTransaction() {
    const btn = document.getElementById('btnCompletePosSale');
    const method = document.getElementById('posPaymentMethod').value;
    const total = getPosTotal();

    if (total <= 0) {
        btn.disabled = true;
        btn.title = "Keranjang kasir masih kosong";
        return;
    }

    if (method === 'CASH') {
        const received = parseInt(document.getElementById('posCashReceived').value, 10) || 0;
        if (received < total) {
            btn.disabled = true;
            btn.title = "Uang diterima masih kurang dari total tagihan";
            return;
        }
        btn.disabled = false;
        btn.title = "Klik untuk menyelesaikan transaksi";
    } else if (method === 'BANK_TRANSFER') {
        const confirmed = document.getElementById('posTransferConfirmed')?.checked;
        if (!confirmed) {
            btn.disabled = true;
            btn.title = "Harap konfirmasi bahwa dana transfer/QRIS telah masuk";
            return;
        }
        btn.disabled = false;
        btn.title = "Klik untuk menyelesaikan transaksi";
    } else if (method === 'CREDIT') {
        if (!selectedPosCreditMember) {
            btn.disabled = true;
            btn.title = "Cari dan pilih anggota koperasi terlebih dahulu";
            return;
        }
        if (selectedPosCreditMember.credit_account_status !== 'ACTIVE' || selectedPosCreditMember.has_overdue || selectedPosCreditMember.available_limit < total) {
            btn.disabled = true;
            btn.title = "Kelayakan kredit anggota tidak memenuhi syarat";
            return;
        }
        btn.disabled = false;
        btn.title = "Klik untuk menyelesaikan transaksi";
    }
}

async function openPosReceiptModal() {
    const total = getPosTotal();
    if (total <= 0) return;

    const btn = document.getElementById('btnCompletePosSale');
    const method = document.getElementById('posPaymentMethod').value;
    const transferRef = document.getElementById('posTransferRef')?.value.trim() || null;

    if (method === 'CREDIT' && !selectedPosCreditMember?.id) {
        showInlineAlert('posErrorAlert', 'Silakan cari dan pilih anggota yang valid untuk transaksi piutang.');
        return;
    }

    hideInlineAlert('posErrorAlert');
    if (!currentPosIdempotencyKey) {
        currentPosIdempotencyKey = generateIdemKey('pos');
    }

    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status"></span> Memproses POS...`;

    try {
        const posItems = posCart.map(item => ({
            product_id: item.productId || item.sku,
            quantity: item.qty,
        }));

        const payload = {
            payment_choice: method,
            member_id: method === 'CREDIT' ? selectedPosCreditMember.id : null,
            external_reference: method === 'BANK_TRANSFER' ? transferRef : null,
            idempotency_key: currentPosIdempotencyKey,
            items: posItems,
        };

        const result = await apiRequest('/api/v1/pos/sales', 'POST', payload);
        const saleData = result.data || {};
        currentPosIdempotencyKey = null;

        document.getElementById('receiptOrderNo').textContent = saleData.order_no || '-';
        const completedDate = saleData.completed_at ? new Date(saleData.completed_at) : new Date();
        document.getElementById('receiptDate').textContent = completedDate.toLocaleDateString('id-ID', {
            day: '2-digit', month: 'short', year: 'numeric'
        }) + ' ' + completedDate.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' WIB';
        document.getElementById('receiptTotalAmount').textContent = `Rp ${Number(saleData.total_amount ?? total).toLocaleString('id-ID')}`;

        const container = document.getElementById('receiptItemsContainer');
        container.innerHTML = posCart.map(item => `
            <div class="d-flex justify-content-between mb-1">
                <span>${escapeHtml(item.name)} (${item.qty}x)</span>
                <span>Rp ${(item.price * item.qty).toLocaleString('id-ID')}</span>
            </div>
        `).join('');

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
            const received = parseInt(document.getElementById('posCashReceived').value, 10) || total;
            document.getElementById('receiptCashReceived').textContent = `Rp ${received.toLocaleString('id-ID')}`;
            document.getElementById('receiptChangeAmount').textContent = `Rp ${(received - total).toLocaleString('id-ID')}`;
        } else if (method === 'BANK_TRANSFER') {
            badge.className = 'badge bg-primary';
            badge.textContent = 'TRANSFER / QRIS';
            document.getElementById('receiptCustomerName').textContent = 'Pembeli Non-Tunai';
            transDet.classList.remove('d-none');
            document.getElementById('receiptTransferRef').textContent = transferRef || '-';
        } else if (method === 'CREDIT') {
            badge.className = 'badge bg-warning text-dark';
            badge.textContent = 'PIUTANG ANGGOTA 7 HARI';
            document.getElementById('receiptCustomerName').textContent = selectedPosCreditMember.name;
            credDet.classList.remove('d-none');
            document.getElementById('receiptCreditMemberId').textContent = selectedPosCreditMember.code;
            const dueDate = new Date();
            dueDate.setDate(dueDate.getDate() + 7);
            document.getElementById('receiptCreditDueDate').textContent = dueDate.toLocaleDateString('id-ID', {
                day: '2-digit', month: 'short', year: 'numeric'
            }) + ' (Tempo 7 Hari)';
            selectedPosCreditMember.available_limit = Math.max(0, selectedPosCreditMember.available_limit - total);
            renderPosCreditMemberCard();
        }

        const modal = new bootstrap.Modal(document.getElementById('posReceiptModal'));
        modal.show();
    } catch (err) {
        showInlineAlert('posErrorAlert', err.message || 'Gagal mencatat transaksi POS.');
    } finally {
        btn.innerHTML = originalHtml;
        validatePosTransaction();
    }
}

function resetPosCartAfterSale() {
    posCart = [];
    currentPosIdempotencyKey = null;
    hideInlineAlert('posErrorAlert');
    document.getElementById('posBarcodeField').value = '';
    document.getElementById('posCashReceived').value = '0';
    document.getElementById('posTransferRef').value = '';
    if (document.getElementById('posTransferConfirmed')) document.getElementById('posTransferConfirmed').checked = false;
    renderPosCart();
}

// Antrian order pickup live dari GET /api/v1/cashier/orders
async function loadCashierOrders() {
    const tbody = document.getElementById('pickupQueueTableBody');
    const loadingRow = document.getElementById('pickupLoadingRow');
    const emptyRow = document.getElementById('pickupEmptyRow');
    const statCountEl = document.getElementById('statPickupOrdersCount');
    const statSubEl = document.getElementById('statPickupOrdersSub');
    const statusFilter = document.getElementById('pickupStatusFilter')?.value || '';

    hideInlineAlert('pickupQueueErrorAlert');
    if (loadingRow) loadingRow.classList.remove('d-none');

    try {
        const query = statusFilter ? `?status=${encodeURIComponent(statusFilter)}` : '';
        const res = await apiRequest(`/api/v1/cashier/orders${query}`);
        liveCashierOrders = Array.isArray(res.data) ? res.data : [];

        if (statCountEl) {
            statCountEl.textContent = `${liveCashierOrders.length} Pesanan`;
        }
        if (statSubEl) {
            const totalUnits = liveCashierOrders.reduce((sum, o) => {
                const items = Array.isArray(o.items) ? o.items : [];
                return sum + items.reduce((s, i) => s + Number(i.quantity || 0), 0);
            }, 0);
            statSubEl.innerHTML = `<i class="bi bi-bag-check-fill me-1"></i>${totalUnits} unit menunggu proses`;
        }

        renderPickupQueueRows(liveCashierOrders);
    } catch (err) {
        if (statCountEl) statCountEl.textContent = '0 Pesanan';
        if (statSubEl) statSubEl.innerHTML = `<i class="bi bi-exclamation-circle me-1"></i>Gagal memuat`;
        if (tbody) {
            tbody.querySelectorAll('tr.live-order-row').forEach(tr => tr.remove());
        }
        if (emptyRow) emptyRow.classList.remove('d-none');
        showInlineAlert('pickupQueueErrorAlert', err.message || 'Gagal memuat antrian pesanan dari server.');
    } finally {
        if (loadingRow) loadingRow.classList.add('d-none');
    }
}

function formatOrderItemsSummary(order) {
    const items = Array.isArray(order.items) ? order.items : [];
    if (items.length === 0) return '-';
    return items.map(item => {
        const matched = stockCatalogue.find(p => p.id === item.product_id || p.sku === item.product_id);
        const label = matched ? matched.name : `Produk (${String(item.product_id).slice(0, 8)})`;
        return `${label} (${item.quantity}x)`;
    }).join(', ');
}

function formatPickupMemberDisplay(order) {
    const memberName = order.member_name || order.member?.full_name || order.member?.name || null;
    const memberCode = order.member_no || order.member_number || order.member?.member_number || order.member?.employee_number || null;
    if (memberName && memberCode) {
        return `${memberName} (${memberCode})`;
    }
    if (memberName) {
        const shortCode = order.member_id ? `#${String(order.member_id).slice(-6).toUpperCase()}` : '';
        return shortCode ? `${memberName} (${shortCode})` : memberName;
    }
    if (memberCode) {
        return `Anggota ${memberCode}`;
    }
    if (order.member_id) {
        const raw = String(order.member_id);
        const shortCode = raw.length > 12 ? `#${raw.slice(-6).toUpperCase()}` : raw;
        return `Anggota ${shortCode}`;
    }
    return 'Non-Anggota';
}

function renderPickupQueueRows(orders) {
    const tbody = document.getElementById('pickupQueueTableBody');
    const emptyRow = document.getElementById('pickupEmptyRow');
    if (!tbody) return;

    tbody.querySelectorAll('tr.live-order-row').forEach(tr => tr.remove());

    if (!orders || orders.length === 0) {
        if (emptyRow) emptyRow.classList.remove('d-none');
        return;
    }

    if (emptyRow) emptyRow.classList.add('d-none');

    const rowsHtml = orders.map(order => {
        const pickupCode = order.pickup_code || order.order_no || order.id;
        const orderId = order.id;
        const memberDisplay = formatPickupMemberDisplay(order);
        const itemsSummary = formatOrderItemsSummary(order);
        const totalAmount = Number(order.total_amount || 0);
        const method = order.payment_choice || 'CASH';
        const status = order.status || 'PENDING_PAYMENT';
        const createdAt = order.created_at ? new Date(order.created_at).toLocaleString('id-ID') : '-';

        let paymentBadgeHtml = '';
        let canHandover = false;

        if (method === 'CASH') {
            if (status === 'READY_FOR_PICKUP') {
                paymentBadgeHtml = `<span class="badge bg-success-subtle text-success border border-success-subtle">Tunai Lunas</span>`;
                canHandover = true;
            } else {
                paymentBadgeHtml = `<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Tunai di Kasir (Belum Bayar)</span>
                    <small class="text-muted" style="font-size: 0.68rem;">Akan dicatat lunas saat serah terima</small>`;
                canHandover = true;
            }
        } else if (method === 'CREDIT') {
            paymentBadgeHtml = `<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Piutang 7 Hari</span>
                <small class="text-success" style="font-size: 0.68rem;"><i class="bi bi-check-circle"></i> Limit Disetujui</small>`;
            canHandover = true;
        } else {
            if (status === 'READY_FOR_PICKUP') {
                paymentBadgeHtml = `<span class="badge bg-success text-white"><i class="bi bi-check-circle-fill me-0.5"></i> Transfer Terverifikasi</span>`;
                canHandover = true;
            } else {
                paymentBadgeHtml = `<span class="badge bg-warning text-dark"><i class="bi bi-clock-history me-0.5"></i> Menunggu Verifikasi</span>
                    <button type="button" class="btn btn-outline-primary btn-sm py-0.5 px-2 mt-0.5 d-inline-flex align-items-center gap-1" style="font-size: 0.7rem;" onclick="openVerifyTransferModalByOrderId('${escapeHtml(orderId)}')">
                        <i class="bi bi-receipt-cutoff"></i>
                        <span>Catat & Verifikasi Transfer</span>
                    </button>`;
                canHandover = false;
            }
        }

        return `
            <tr class="live-order-row" id="pickupRow-${escapeHtml(pickupCode)}" data-code="${escapeHtml(String(pickupCode).toLowerCase())}" data-member="${escapeHtml((String(order.order_no || '') + ' ' + memberDisplay + ' ' + String(order.member_id || '')).toLowerCase())}">
                <td>
                    <span class="badge bg-primary px-2.5 py-1 fs-7 font-monospace fw-bold">${escapeHtml(pickupCode)}</span>
                    <small class="text-muted d-block" style="font-size: 0.68rem;">${escapeHtml(createdAt)}</small>
                </td>
                <td>
                    <span class="fw-bold text-dark font-monospace">${escapeHtml(order.order_no || '-')}</span>
                    <small class="text-muted d-block">${escapeHtml(memberDisplay)}</small>
                </td>
                <td><span class="text-secondary">${escapeHtml(itemsSummary)}</span></td>
                <td class="fw-bold text-dark font-monospace">Rp ${totalAmount.toLocaleString('id-ID')}</td>
                <td><div class="d-flex flex-column gap-1">${paymentBadgeHtml}</div></td>
                <td class="text-end">
                    <div class="d-flex align-items-center justify-content-end gap-2">
                        <button type="button" class="btn btn-sm ${canHandover ? 'btn-outline-success' : 'btn-outline-secondary opacity-50'} px-2.5 py-1 fw-semibold d-inline-flex align-items-center gap-1 text-nowrap handover-btn" ${canHandover ? '' : 'disabled'} onclick="openPickupHandoverModalByOrderId('${escapeHtml(orderId)}')">
                            <i class="bi bi-check2-circle"></i>
                            <span>Serah Terima</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger px-2.5 py-1 d-inline-flex align-items-center gap-1 text-nowrap cancel-btn" onclick="openCancelOrderModalByOrderId('${escapeHtml(orderId)}')">
                            <i class="bi bi-x-circle"></i>
                            <span>Batal</span>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    tbody.insertAdjacentHTML('beforeend', rowsHtml);
    const activeFilter = document.getElementById('pickupFilterInput')?.value || '';
    if (activeFilter) filterPickupQueue(activeFilter);
}

function openPickupHandoverModalByOrderId(orderId) {
    const order = liveCashierOrders.find(o => o.id === orderId);
    if (!order) return;

    selectedPickupOrder = {
        id: order.id,
        code: order.pickup_code || order.order_no,
        orderNo: order.order_no,
        memberId: order.member_id || 'Non-Anggota',
        items: formatOrderItemsSummary(order),
        amount: Number(order.total_amount || 0),
        method: order.payment_choice,
        status: order.status,
    };

    hideInlineAlert('pickupHandoverErrorAlert');
    document.getElementById('confirmPickupCode').textContent = selectedPickupOrder.code;
    document.getElementById('confirmPickupMemberName').textContent = selectedPickupOrder.orderNo || selectedPickupOrder.code;
    document.getElementById('confirmPickupMemberId').textContent = selectedPickupOrder.memberId;
    document.getElementById('confirmPickupItems').textContent = selectedPickupOrder.items;
    document.getElementById('confirmPickupAmount').textContent = `Rp ${selectedPickupOrder.amount.toLocaleString('id-ID')}`;

    const badge = document.getElementById('confirmPickupPaymentBadge');
    if (selectedPickupOrder.method === 'CASH') {
        badge.className = 'badge bg-success-subtle text-success border border-success-subtle';
        badge.textContent = selectedPickupOrder.status === 'READY_FOR_PICKUP' ? 'Lunas Tunai di Kasir' : 'Bayar Tunai saat Serah Terima';
    } else if (selectedPickupOrder.method === 'CREDIT') {
        badge.className = 'badge bg-warning-subtle text-warning-emphasis border border-warning-subtle';
        badge.textContent = 'Piutang Anggota (Tempo 7 Hari)';
    } else {
        badge.className = 'badge bg-primary-subtle text-primary border border-primary-subtle';
        badge.textContent = 'Lunas Transfer Bank';
    }

    document.getElementById('checkPhysicalHandover').checked = true;
    const modal = new bootstrap.Modal(document.getElementById('pickupConfirmModal'));
    modal.show();
}

async function executePickupHandover() {
    if (!selectedPickupOrder) return;
    if (!document.getElementById('checkPhysicalHandover')?.checked) {
        showInlineAlert('pickupHandoverErrorAlert', 'Harap centang konfirmasi bahwa barang fisik sudah diserahkan.');
        return;
    }

    hideInlineAlert('pickupHandoverErrorAlert');
    const btn = document.getElementById('btnExecuteHandover');
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Memproses...`;

    try {
        if (selectedPickupOrder.method === 'CASH' && selectedPickupOrder.status === 'PENDING_PAYMENT') {
            await apiRequest(`/api/v1/orders/${encodeURIComponent(selectedPickupOrder.id)}/payments`, 'POST', {
                method: 'CASH',
                amount: selectedPickupOrder.amount,
                idempotency_key: generateIdemKey(`ordcash-${selectedPickupOrder.id.slice(0, 8)}`),
            });
            selectedPickupOrder.status = 'READY_FOR_PICKUP';
        }

        await apiRequest(`/api/v1/orders/${encodeURIComponent(selectedPickupOrder.id)}/confirm-pickup`, 'POST', {
            pickup_code: selectedPickupOrder.code,
            handover_confirmed: true,
            idempotency_key: generateIdemKey(`pkp-${selectedPickupOrder.id.slice(0, 8)}`),
        });

        const modalEl = document.getElementById('pickupConfirmModal');
        bootstrap.Modal.getInstance(modalEl)?.hide();

        document.getElementById('pickupSuccessMessage').innerHTML = `
            Pesanan <strong>${escapeHtml(selectedPickupOrder.code)}</strong> berhasil diserahkan.<br>
            Stok fisik telah dikurangi dan transaksi tercatat sebagai COMPLETED.
        `;
        new bootstrap.Modal(document.getElementById('pickupSuccessModal')).show();
        await loadCashierOrders();
    } catch (err) {
        showInlineAlert('pickupHandoverErrorAlert', err.message || 'Gagal mengonfirmasi serah terima pickup.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = origText;
    }
}

function filterCashierStock(rawQuery) {
    const query = (rawQuery || '').toLowerCase().trim();
    let matchCount = 0;
    document.querySelectorAll('.stock-item-row').forEach(row => {
        const name = row.getAttribute('data-name') || '';
        const sku = row.getAttribute('data-sku') || '';
        const barcode = row.getAttribute('data-barcode') || '';
        if (!query || name.includes(query) || sku.includes(query) || barcode.includes(query)) {
            row.classList.remove('d-none');
            matchCount++;
        } else {
            row.classList.add('d-none');
        }
    });

    const emptyRow = document.getElementById('stockEmptyRow');
    if (emptyRow) emptyRow.classList.toggle('d-none', matchCount > 0);
}

function filterPickupQueue(query) {
    const q = (query || '').toLowerCase().trim();
    let matchCount = 0;
    document.querySelectorAll('#pickupQueueTableBody tr.live-order-row').forEach(row => {
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
    if (emptyRow) emptyRow.classList.toggle('d-none', matchCount > 0);
}

function openVerifyTransferModalByOrderId(orderId) {
    const order = liveCashierOrders.find(o => o.id === orderId);
    if (!order) return;

    currentVerifyTransfer = {
        id: order.id,
        code: order.pickup_code || order.order_no,
        memberId: order.member_id || '-',
        amount: Number(order.total_amount || 0),
        paymentId: null,
    };

    hideInlineAlert('vtfErrorAlert');
    document.getElementById('vtfPickupCode').textContent = currentVerifyTransfer.code;
    document.getElementById('vtfMemberName').textContent = order.order_no || currentVerifyTransfer.code;
    document.getElementById('vtfMemberId').textContent = currentVerifyTransfer.memberId;
    document.getElementById('vtfTotalAmount').textContent = `Rp ${currentVerifyTransfer.amount.toLocaleString('id-ID')}`;
    document.getElementById('vtfSenderName').textContent = currentVerifyTransfer.memberId;
    document.getElementById('vtfTransferProofAmount').textContent = `Rp ${currentVerifyTransfer.amount.toLocaleString('id-ID')}`;
    document.getElementById('vtfRefInput').value = '';
    document.getElementById('vtfConfirmFundsCheck').checked = false;
    document.getElementById('vtfRejectBox').classList.add('d-none');

    new bootstrap.Modal(document.getElementById('verifyTransferModal')).show();
}

function toggleRejectTransferBox() {
    document.getElementById('vtfRejectBox').classList.toggle('d-none');
}

async function ensureOrderTransferPaymentId(ref) {
    if (currentVerifyTransfer.paymentId) {
        return currentVerifyTransfer.paymentId;
    }

    const payRes = await apiRequest(`/api/v1/orders/${encodeURIComponent(currentVerifyTransfer.id)}/payments`, 'POST', {
        method: 'BANK_TRANSFER',
        amount: currentVerifyTransfer.amount,
        external_reference: ref || null,
        idempotency_key: generateIdemKey(`trf-${currentVerifyTransfer.id.slice(0, 8)}`),
    });
    currentVerifyTransfer.paymentId = payRes.data.payment_id;
    return currentVerifyTransfer.paymentId;
}

async function executeApproveTransfer() {
    if (!currentVerifyTransfer) return;
    const ref = document.getElementById('vtfRefInput').value.trim();
    const checked = document.getElementById('vtfConfirmFundsCheck').checked;

    if (!ref) {
        showInlineAlert('vtfErrorAlert', 'Nomor referensi mutasi bank wajib diisi.');
        return;
    }
    if (!checked) {
        showInlineAlert('vtfErrorAlert', 'Harap centang konfirmasi bahwa dana sudah masuk ke rekening koperasi.');
        return;
    }

    hideInlineAlert('vtfErrorAlert');
    const btn = document.getElementById('btnExecuteApproveTransfer');
    btn.disabled = true;

    try {
        const paymentId = await ensureOrderTransferPaymentId(ref);
        await apiRequest(`/api/v1/payments/${encodeURIComponent(paymentId)}/verify`, 'POST', {
            action: 'VERIFY',
            idempotency_key: generateIdemKey(`vrf-${paymentId.slice(0, 8)}`),
        });

        bootstrap.Modal.getInstance(document.getElementById('verifyTransferModal'))?.hide();

        document.getElementById('pickupSuccessMessage').innerHTML = `
            Pembayaran transfer untuk pesanan <strong>${escapeHtml(currentVerifyTransfer.code)}</strong> telah <strong>TERVERIFIKASI</strong>.<br>
            Tombol Serah Terima barang kini aktif.
        `;
        new bootstrap.Modal(document.getElementById('pickupSuccessModal')).show();
        await loadCashierOrders();
    } catch (err) {
        showInlineAlert('vtfErrorAlert', err.message || 'Gagal memverifikasi pembayaran transfer.');
    } finally {
        btn.disabled = false;
    }
}

async function executeRejectTransfer() {
    if (!currentVerifyTransfer) return;
    const ref = document.getElementById('vtfRefInput').value.trim() || null;
    const reason = document.getElementById('vtfRejectReason').value;

    hideInlineAlert('vtfErrorAlert');
    const btn = document.getElementById('btnExecuteRejectTransfer');
    btn.disabled = true;

    try {
        const paymentId = await ensureOrderTransferPaymentId(ref);
        await apiRequest(`/api/v1/payments/${encodeURIComponent(paymentId)}/verify`, 'POST', {
            action: 'REJECT',
            idempotency_key: generateIdemKey(`rej-${paymentId.slice(0, 8)}`),
        });

        bootstrap.Modal.getInstance(document.getElementById('verifyTransferModal'))?.hide();

        document.getElementById('pickupSuccessMessage').innerHTML = `
            Pembayaran transfer pesanan <strong>${escapeHtml(currentVerifyTransfer.code)}</strong> telah <strong>DITOLAK</strong> (${escapeHtml(reason)}).
        `;
        new bootstrap.Modal(document.getElementById('pickupSuccessModal')).show();
        await loadCashierOrders();
    } catch (err) {
        showInlineAlert('vtfErrorAlert', err.message || 'Gagal menolak bukti transfer.');
    } finally {
        btn.disabled = false;
    }
}

function openCancelOrderModalByOrderId(orderId) {
    const order = liveCashierOrders.find(o => o.id === orderId);
    if (!order) return;

    currentCancelOrder = {
        id: order.id,
        code: order.pickup_code || order.order_no,
        memberName: order.order_no || order.member_id || '-',
        items: formatOrderItemsSummary(order),
        amount: Number(order.total_amount || 0),
    };

    hideInlineAlert('cancelOrderErrorAlert');
    document.getElementById('cancelPickupCode').textContent = currentCancelOrder.code;
    document.getElementById('cancelMemberName').textContent = currentCancelOrder.memberName;
    document.getElementById('cancelItemsSummary').textContent = currentCancelOrder.items;
    document.getElementById('cancelTotalAmount').textContent = `Rp ${currentCancelOrder.amount.toLocaleString('id-ID')}`;

    new bootstrap.Modal(document.getElementById('cancelOrderModal')).show();
}

async function executeCancelOrder() {
    if (!currentCancelOrder) return;
    hideInlineAlert('cancelOrderErrorAlert');
    const btn = document.getElementById('btnExecuteCancelOrder');
    btn.disabled = true;

    try {
        await apiRequest(`/api/v1/orders/${encodeURIComponent(currentCancelOrder.id)}/cancel`, 'POST', {});

        bootstrap.Modal.getInstance(document.getElementById('cancelOrderModal'))?.hide();

        document.getElementById('pickupSuccessMessage').innerHTML = `
            Pesanan <strong>${escapeHtml(currentCancelOrder.code)}</strong> berhasil dibatalkan.<br>
            <span class="text-success small fw-semibold">Reservasi stok dan hold kredit telah dilepaskan.</span>
        `;
        new bootstrap.Modal(document.getElementById('pickupSuccessModal')).show();
        await loadCashierOrders();
    } catch (err) {
        showInlineAlert('cancelOrderErrorAlert', err.message || 'Gagal membatalkan pesanan.');
    } finally {
        btn.disabled = false;
    }
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
    const field = document.getElementById('posBarcodeField');
    const val = (field?.value || '').trim();
    if (!val) return;
    hidePosSearchDropdown();
    playGopayChime();
    handleIncomingBarcode(val, { source: 'input', showGopaySheet: true });
    if (field) {
        field.value = '';
        field.focus();
    }
}

async function handleIncomingBarcode(code, options = {}) {
    const trimmed = (code || '').trim();
    if (!trimmed) return;
    const clean = trimmed.toLowerCase();
    const shouldShowSheet = options.showGopaySheet !== false;

    if (clean.startsWith('pkp-') || clean.startsWith('ord-')) {
        const pickupUpper = trimmed.toUpperCase();
        if (liveCashierOrders.length === 0) {
            await loadCashierOrders();
        }
        const foundOrder = liveCashierOrders.find(o =>
            (o.pickup_code && o.pickup_code.toUpperCase() === pickupUpper) ||
            (o.order_no && o.order_no.toUpperCase() === pickupUpper)
        );

        if (foundOrder) {
            if (shouldShowSheet) {
                showGopayScanSheet({
                    type: 'Kode Pickup Pesanan Online',
                    badgeText: 'PICKUP TERVERIFIKASI',
                    badgeClass: 'bg-primary text-white',
                    title: `${foundOrder.order_no || foundOrder.pickup_code}`,
                    code: `${foundOrder.pickup_code || '-'} • ${foundOrder.order_no || ''}`,
                    price: `Rp ${Number(foundOrder.total_amount || 0).toLocaleString('id-ID')}`,
                    qtyBadge: `${(foundOrder.items || []).length} Item`,
                    subtitle: `Status: ${foundOrder.status} (${foundOrder.payment_choice}).`,
                    actionBtnText: 'Serah Terima Barang',
                    actionBtnIcon: 'bi-box-seam',
                    actionCallback: () => {
                        dismissGopayScanSheet();
                        openPickupHandoverModalByOrderId(foundOrder.id);
                    }
                });
            } else {
                showScanToast(`Kode Pickup ${pickupUpper} Ditemukan`, `Pesanan ${foundOrder.order_no || pickupUpper} siap diproses.`);
            }

            const row = document.getElementById(`pickupRow-${foundOrder.pickup_code || foundOrder.order_no}`);
            if (row) {
                row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                row.classList.add('table-warning');
                setTimeout(() => row.classList.remove('table-warning'), 3000);
            }
            return;
        }
    }

    // Normalisasi pencarian manual berdasarkan SKU atau barcode (case-insensitive terhadap katalog DB)
    const matchedCatalogue = stockCatalogue.find(item =>
        (item.barcode && String(item.barcode).toLowerCase() === clean) ||
        (item.sku && String(item.sku).toLowerCase() === clean)
    );
    const lookupQuery = matchedCatalogue
        ? (matchedCatalogue.barcode || matchedCatalogue.sku)
        : (clean.includes('-') ? trimmed.toUpperCase() : trimmed);

    try {
        const lookupRes = await apiRequest(`/api/v1/products/lookup?barcode=${encodeURIComponent(lookupQuery)}`);
        const product = lookupRes.data;
        const avail = Number(product.available_stock || 0);
        if (avail <= 0) {
            showInlineAlert('posErrorAlert', `Stok tersedia kosong untuk ${product.name} (${product.sku}).`);
            return;
        }

        posAddToCart(product.name, Number(product.price), product.sku, product.id, product.barcode || '');

        if (shouldShowSheet) {
            showGopayScanSheet({
                type: 'Komoditas Gerai KOPEG',
                badgeText: 'SCAN BERHASIL',
                badgeClass: 'bg-success text-white',
                title: product.name,
                code: `${product.sku} • ${product.barcode || '-'}`,
                price: `Rp ${Number(product.price).toLocaleString('id-ID')}`,
                qtyBadge: '+1 ke Keranjang Kasir',
                subtitle: `Stok tersedia: ${avail} ${product.unit || 'Pcs'}.`,
                actionBtnText: 'Scan Lagi',
                actionBtnIcon: 'bi-camera-fill',
                actionCallback: () => {
                    dismissGopayScanSheet();
                    openBarcodeScannerModal();
                }
            });
        } else {
            showScanToast(`${product.name}`, `Rp ${Number(product.price).toLocaleString('id-ID')} masuk keranjang`);
        }
    } catch (err) {
        showInlineAlert('posErrorAlert', err.message || `Barang dengan kode "${trimmed}" tidak ditemukan.`);
        if (shouldShowSheet) {
            showGopayScanSheet({
                type: 'Pencarian Barcode',
                badgeText: 'TIDAK DITEMUKAN',
                badgeClass: 'bg-danger text-white',
                title: `Kode "${trimmed}" tidak terdaftar`,
                code: trimmed,
                price: '-',
                qtyBadge: 'Gagal',
                subtitle: err.message || 'Periksa kembali SKU atau barcode produk di master data.',
                actionBtnText: 'Coba Scan Lagi',
                actionBtnIcon: 'bi-camera-fill',
                actionCallback: () => {
                    dismissGopayScanSheet();
                    openBarcodeScannerModal();
                }
            });
        }
    }
}

// Global Keydown Listener untuk Barcode Scanner Gun Fisik (USB / Bluetooth HID)
let barcodeGunBuffer = '';
let lastGunKeyTime = Date.now();

window.addEventListener('keydown', (e) => {
    const activeEl = document.activeElement;
    const isTypingInOtherInput = activeEl && 
        (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA' || activeEl.isContentEditable) &&
        activeEl.id !== 'posBarcodeField';

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
    loadCashierOrders();

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
