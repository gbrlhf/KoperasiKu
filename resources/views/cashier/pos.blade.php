@extends('layouts.app')

@section('title', 'Portal Kasir & Monitoring Stok')

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

<!-- RINGKASAN STATUS STOK GERAI -->
<div class="row g-2 g-md-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="p-2.5 p-md-3 rounded-2 bg-white border shadow-xs h-100">
            <small class="text-secondary text-uppercase fw-bold d-block mb-1" style="font-size: 0.68rem;">Total SKU Barang</small>
            <h4 class="fw-extrabold text-dark mb-0 fs-5 fs-md-4">{{ $stockSummary['total_sku'] }} Item</h4>
            <small class="text-muted d-block text-truncate" style="font-size: 0.7rem;">Komoditas aktif gerai</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="p-2.5 p-md-3 rounded-2 bg-white border shadow-xs h-100">
            <small class="text-secondary text-uppercase fw-bold d-block mb-1" style="font-size: 0.68rem;">Stok Aman</small>
            <h4 class="fw-extrabold text-success mb-0 fs-5 fs-md-4">{{ $stockSummary['safe_stock_count'] }} SKU</h4>
            <small class="text-success d-block text-truncate" style="font-size: 0.7rem;"><i class="bi bi-check-circle me-1"></i>Pasokan cukup</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="p-2.5 p-md-3 rounded-2 bg-white border shadow-xs h-100">
            <small class="text-secondary text-uppercase fw-bold d-block mb-1" style="font-size: 0.68rem;">Stok Kritis / Menipis</small>
            <h4 class="fw-extrabold text-danger mb-0 fs-5 fs-md-4">{{ $stockSummary['low_stock_count'] }} SKU</h4>
            <small class="text-danger d-block text-truncate" style="font-size: 0.7rem;"><i class="bi bi-exclamation-triangle me-1"></i>Perlu restock</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="p-2.5 p-md-3 rounded-2 bg-white border shadow-xs h-100">
            <small class="text-secondary text-uppercase fw-bold d-block mb-1" style="font-size: 0.68rem;">Reservasi Pickup Online</small>
            <h4 class="fw-extrabold text-primary mb-0 fs-5 fs-md-4">{{ $stockSummary['total_reserved_items'] }} Barang</h4>
            <small class="text-primary d-block text-truncate" style="font-size: 0.7rem;"><i class="bi bi-bag-check me-1"></i>Menunggu diambil</small>
        </div>
    </div>
</div>

<!-- TABS OPERASIONAL KASIR -->
<ul class="nav nav-pills mb-3 gap-2 border-bottom pb-3 overflow-x-auto flex-nowrap text-nowrap" id="cashierTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-semibold d-inline-flex align-items-center gap-2" id="stock-tab" data-bs-toggle="pill" data-bs-target="#stock-pane" type="button" role="tab">
            <i class="bi bi-boxes"></i>
            <span>Monitoring Stok Barang</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold d-inline-flex align-items-center gap-2" id="pos-tab" data-bs-toggle="pill" data-bs-target="#pos-pane" type="button" role="tab">
            <i class="bi bi-upc-scan"></i>
            <span>Point of Sale (POS) Kasir</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold d-inline-flex align-items-center gap-2" id="pickup-tab" data-bs-toggle="pill" data-bs-target="#pickup-pane" type="button" role="tab">
            <i class="bi bi-qr-code-scan"></i>
            <span>Verifikasi Pickup Pesanan ({{ count($pickupQueue) }})</span>
        </button>
    </li>
</ul>

<div class="tab-content" id="cashierTabContent">
    
    <!-- TAB 1: MONITORING STOK BARANG REAL-TIME -->
    <div class="tab-pane fade show active" id="stock-pane" role="tabpanel">
        <div class="card border-0 shadow-sm bg-white">
            <div class="card-header bg-white py-3 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h5 class="card-title fw-bold text-dark mb-0 fs-6">Master Persediaan & Stok Barang Gerai</h5>
                    <small class="text-muted" style="font-size: 0.75rem;">Stok tersedia = Stok fisik rak dikurangi barang ter-reservasi pesanan online</small>
                </div>
                <div style="max-width: 320px;" class="w-100">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" id="stockSearchInput" class="form-control" placeholder="Cari SKU atau nama barang..." oninput="filterCashierStock(this.value)">
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
                                    <span class="fw-bold font-monospace text-dark">{{ $item['sku'] }}</span>
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">{{ $item['barcode'] }}</small>
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
    <div class="tab-pane fade" id="pos-pane" role="tabpanel">
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

                <!-- Quick Pick Buttons Sembako Populer -->
                <div class="card border-0 shadow-sm bg-white p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2.5">
                        <h6 class="fw-bold text-dark mb-0 small">Pilihan Cepat Sembako Populer (1-Click Add):</h6>
                        <small class="text-muted" style="font-size: 0.7rem;">Stok Gerai Kanayakan</small>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1" onclick="posAddToCart('Beras Premium Ramos 5 Kg', 74500, 'SEM-001')">
                            <i class="bi bi-plus-circle text-primary"></i>
                            <span>Beras Ramos (Rp 74.500)</span>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1" onclick="posAddToCart('Minyak Goreng Pouch 2 Liter', 36000, 'MNY-001')">
                            <i class="bi bi-plus-circle text-primary"></i>
                            <span>Minyak 2L (Rp 36.000)</span>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1" onclick="posAddToCart('Gula Pasir Kristal Putih 1 Kg', 17500, 'GLA-001')">
                            <i class="bi bi-plus-circle text-primary"></i>
                            <span>Gula 1kg (Rp 17.500)</span>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1" onclick="posAddToCart('Telur Ayam Negeri Fresh 1 Kg', 28500, 'TLR-001')">
                            <i class="bi bi-plus-circle text-primary"></i>
                            <span>Telur 1kg (Rp 28.500)</span>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1" onclick="posAddToCart('Tepung Terigu Segitiga Biru 1 Kg', 14000, 'TPG-001')">
                            <i class="bi bi-plus-circle text-primary"></i>
                            <span>Terigu 1kg (Rp 14.000)</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right: POS Cart & Checkout Receipt -->
            <div class="col-12 col-lg-5">
                <div class="card border-0 shadow-sm bg-white p-3 p-md-4">
                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-2 d-flex justify-content-between align-items-center">
                        <span class="d-flex align-items-center gap-1.5">
                            <i class="bi bi-receipt text-primary"></i>
                            <span>Keranjang Meja Kasir</span>
                        </span>
                        <div class="d-flex align-items-center gap-1">
                            <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.7rem;">#POS-9421</span>
                            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1.5" style="font-size: 0.68rem;" onclick="clearPosCart()" title="Kosongkan Keranjang Kasir">
                                Reset
                            </button>
                        </div>
                    </h6>

                    <!-- Dynamic Cart Item List -->
                    <div id="posCartList" class="mb-3" style="min-height: 120px; max-height: 240px; overflow-y: auto;">
                        <!-- Injected via JavaScript renderPosCart() -->
                    </div>

                    <div class="border-top pt-2.5 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-secondary small fw-semibold">Total Tagihan Kasir:</span>
                            <span class="fs-4 fw-extrabold text-primary font-monospace" id="posTotalDisplay">Rp 0</span>
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
                                <input type="number" class="form-control fw-bold font-monospace" id="posCashReceived" value="120000" oninput="calculatePosChange()">
                            </div>
                            <div class="d-flex gap-1 mb-2">
                                <button type="button" class="btn btn-light border btn-sm py-0.5 px-2 text-secondary" style="font-size: 0.7rem;" onclick="setExactCash()">Uang Pas</button>
                                <button type="button" class="btn btn-light border btn-sm py-0.5 px-2 text-secondary" style="font-size: 0.7rem;" onclick="setCashAmount(50000)">Rp 50k</button>
                                <button type="button" class="btn btn-light border btn-sm py-0.5 px-2 text-secondary" style="font-size: 0.7rem;" onclick="setCashAmount(100000)">Rp 100k</button>
                                <button type="button" class="btn btn-light border btn-sm py-0.5 px-2 text-secondary" style="font-size: 0.7rem;" onclick="setCashAmount(150000)">Rp 150k</button>
                                <button type="button" class="btn btn-light border btn-sm py-0.5 px-2 text-secondary" style="font-size: 0.7rem;" onclick="setCashAmount(200000)">Rp 200k</button>
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
                                    <option value="EMP-001">EMP-001 | Ahmad Fauzi (Teknik Mesin POLMAN)</option>
                                    <option value="EMP-014">EMP-014 | Siti Rahma (Logistik & Pengadaan)</option>
                                    <option value="EMP-028">EMP-028 | Dewi Lestari (Admin Keuangan - Limit Tipis)</option>
                                    <option value="EMP-035">EMP-035 | Dr. Hendra Gunawan (Dosen - Ada Overdue)</option>
                                    <option value="EMP-042">EMP-042 | Bambang Sugianto (Staff Lab - Dibekukan)</option>
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
    <div class="tab-pane fade" id="pickup-pane" role="tabpanel">
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
                                $canHandover = !($q['payment_method'] === 'BANK_TRANSFER' && $payStatus !== 'VERIFIED');
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
                                                <small class="text-muted font-monospace" style="font-size: 0.68rem;">{{ $q['transfer_ref'] ?? 'TRX-VERIFIED' }}</small>
                                            @elseif($payStatus === 'PAYMENT_REJECTED')
                                                <span class="badge bg-danger text-white" id="badgeTransfer-{{ $q['pickup_code'] }}">
                                                    <i class="bi bi-x-circle-fill me-0.5"></i> Transfer Ditolak
                                                </span>
                                                <small class="text-danger" style="font-size: 0.68rem;">Mutasi tidak ditemukan</small>
                                            @else
                                                <span class="badge bg-warning text-dark" id="badgeTransfer-{{ $q['pickup_code'] }}">
                                                    <i class="bi bi-clock-history me-0.5"></i> Menunggu Verifikasi
                                                </span>
                                                <button type="button" class="btn btn-outline-primary btn-sm py-0.5 px-2 mt-0.5 d-inline-flex align-items-center gap-1" id="btnVerify-{{ $q['pickup_code'] }}" style="font-size: 0.7rem;" onclick="openVerifyTransferModal('{{ $q['pickup_code'] }}', '{{ $q['member_name'] }}', '{{ $q['member_id'] }}', {{ $q['total_amount'] }}, '{{ $q['transfer_ref'] ?? 'TRX-BCA-51922' }}')">
                                                    <i class="bi bi-receipt-cutoff"></i>
                                                    <span>Verifikasi Transfer</span>
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex align-items-center justify-content-center gap-1.5 flex-wrap">
                                        <button type="button" class="btn btn-sm {{ $canHandover ? 'btn-outline-success' : 'btn-outline-secondary opacity-50' }} px-2.5 py-1 fw-semibold d-inline-flex align-items-center gap-1 handover-btn" id="btnHandover-{{ $q['pickup_code'] }}" {{ $canHandover ? '' : 'disabled' }} title="{{ $canHandover ? 'Serahkan barang ke pembeli' : 'Wajib verifikasi pembayaran transfer terlebih dahulu' }}" onclick="openPickupHandoverModal('{{ $q['pickup_code'] }}', '{{ $q['member_name'] }}', '{{ $q['member_id'] }}', '{{ $q['items_summary'] }}', {{ $q['total_amount'] }}, '{{ $q['payment_method'] }}')">
                                            <i class="bi bi-check2-circle"></i>
                                            <span>Serah Terima</span>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger px-2 py-1 cancel-btn" id="btnCancel-{{ $q['pickup_code'] }}" title="Batalkan Pesanan (Lepas Reservasi Stok & Credit Hold)" onclick="openCancelOrderModal('{{ $q['pickup_code'] }}', '{{ $q['member_name'] }}', '{{ $q['items_summary'] }}', {{ $q['total_amount'] }})">
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
     MODAL 1: SCANNER BARCODE KAMERA GERAI (html5-qrcode CDN)
     ============================================================================== -->
<div class="modal fade" id="barcodeScannerModal" tabindex="-1" aria-labelledby="barcodeScannerModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white py-3 px-3 px-md-4">
                <h6 class="modal-title fw-bold d-flex align-items-center gap-2" id="barcodeScannerModalLabel">
                    <i class="bi bi-upc-scan text-primary fs-5"></i>
                    <span>Scanner Barcode Kamera Gerai</span>
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup" onclick="stopBarcodeScanner()"></button>
            </div>
            <div class="modal-body p-3 p-md-4">
                <!-- Scanner Viewport Container -->
                <div class="position-relative bg-black rounded-3 overflow-hidden mb-3 border" style="min-height: 250px;">
                    <div id="qr-reader" style="width: 100%;"></div>
                </div>

                <!-- Status Banner -->
                <div id="scannerStatusAlert" class="alert alert-info py-2 px-3 small d-flex align-items-center gap-2 mb-2">
                    <div class="spinner-border spinner-border-sm text-primary flex-shrink-0" role="status"></div>
                    <span id="scannerStatusText">Mengaktifkan modul kamera... Arahkan barcode ke kotak bidik.</span>
                </div>

                <!-- Error Banner: Ditampilkan ramah jika NotAllowedError / no camera -->
                <div id="scannerErrorAlert" class="alert alert-danger py-2.5 px-3 small d-none">
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi bi-exclamation-octagon-fill fs-5 text-danger flex-shrink-0 mt-0.5"></i>
                        <div>
                            <strong class="d-block mb-1" id="scannerErrorTitle">Akses Kamera Tidak Tersedia</strong>
                            <span id="scannerErrorMessage" class="d-block text-secondary mb-2">
                                Izin kamera tidak diberikan atau perangkat komputer kasir tidak memiliki kamera.
                            </span>
                            <div class="p-2 rounded bg-white border small text-dark">
                                <strong>Solusi Cepat:</strong> Silakan gunakan input teks SKU/Barcode manual di bawah ini.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Input Manual Fallback di dalam Modal -->
                <div class="p-2.5 rounded-2 bg-light border">
                    <label class="form-label text-secondary fw-semibold d-block mb-1" style="font-size: 0.72rem;">Input Manual di Meja Kasir:</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-upc"></i></span>
                        <input type="text" id="modalManualBarcodeInput" class="form-control font-monospace" placeholder="Ketik SKU / Barcode (cth: SEM-001 / 89999991001)..." onkeydown="if(event.key === 'Enter'){ submitModalManualBarcode(); }">
                        <button type="button" class="btn btn-primary fw-semibold" onclick="submitModalManualBarcode()">
                            + Masukkan
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 px-3 justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1" id="btnSwitchCamera" onclick="switchScannerCamera()">
                    <i class="bi bi-arrow-repeat"></i>
                    <span>Ganti Kamera</span>
                </button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" onclick="stopBarcodeScanner()">
                    Tutup Scanner
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
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup" onclick="resetPosCartAfterSale()"></button>
            </div>
            <div class="modal-body p-3 p-md-4">
                <!-- Struk Digital Card -->
                <div class="border rounded-3 p-3 bg-light mb-3 font-monospace" style="font-size: 0.78rem;">
                    <div class="text-center pb-2 mb-2 border-bottom border-dashed">
                        <h6 class="fw-bold mb-0 font-sans text-dark">KOPEG MART BANDUNG</h6>
                        <small class="text-muted d-block">Koperasi Konsumen Pegawai POLMAN Bandung</small>
                        <small class="text-muted d-block">Jl. Kanayakan No. A11 Dago, Bandung</small>
                        <div class="d-flex justify-content-between text-muted mt-1 small">
                            <span>No: <strong class="text-dark">#POS-9421</strong></span>
                            <span id="receiptDate">16 Nov 2026</span>
                        </div>
                    </div>

                    <!-- Kasir & Pelanggan Info -->
                    <div class="mb-2 pb-2 border-bottom border-dashed">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Kasir:</span>
                            <span class="fw-bold text-dark">Budi Santoso (01)</span>
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
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
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
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
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
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
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

<!-- Library html5-qrcode CDN untuk Barcode Scanning Kamera Nyata -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
// ==============================================================================
// 1. DATA MASTER & STATE KASIR
// ==============================================================================
const stockCatalogue = @json($stockItems);

const membersDirectory = [
    {
        id: 'EMP-001',
        name: 'Ahmad Fauzi',
        unit: 'Teknik Mesin & Manufaktur POLMAN',
        status: 'TERVERIFIKASI',
        available_limit: 852000,
        total_limit: 1000000,
        is_overdue: false,
        overdue_days: 0,
        unpaid_invoices: 2
    },
    {
        id: 'EMP-014',
        name: 'Siti Rahma',
        unit: 'Logistik & Pengadaan POLMAN',
        status: 'TERVERIFIKASI',
        available_limit: 950000,
        total_limit: 1000000,
        is_overdue: false,
        overdue_days: 0,
        unpaid_invoices: 1
    },
    {
        id: 'EMP-028',
        name: 'Dewi Lestari',
        unit: 'Administrasi Keuangan POLMAN',
        status: 'TERVERIFIKASI',
        available_limit: 50000,
        total_limit: 1000000,
        is_overdue: false,
        overdue_days: 0,
        unpaid_invoices: 3
    },
    {
        id: 'EMP-035',
        name: 'Dr. Hendra Gunawan',
        unit: 'Dosen Rekayasa Perancangan',
        status: 'TERVERIFIKASI',
        available_limit: 1000000,
        total_limit: 1000000,
        is_overdue: true,
        overdue_days: 9,
        unpaid_invoices: 1
    },
    {
        id: 'EMP-042',
        name: 'Bambang Sugianto',
        unit: 'Staff Lab Bengkel Mesin',
        status: 'DIBEKUKAN',
        available_limit: 0,
        total_limit: 1000000,
        is_overdue: true,
        overdue_days: 14,
        unpaid_invoices: 4
    }
];

let posCart = [
    { sku: 'SEM-001', name: 'Beras Premium Ramos 5 Kg', price: 74500, qty: 1 },
    { sku: 'MNY-001', name: 'Minyak Goreng Pouch 2 Liter', price: 36000, qty: 1 }
];

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
            <div class="d-flex justify-content-between align-items-center py-2 border-bottom small">
                <div class="lh-sm pe-2">
                    <span class="fw-semibold text-dark d-block">${item.name}</span>
                    <small class="text-muted font-monospace">${item.sku} &bull; Rp ${item.price.toLocaleString('id-ID')}</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="input-group input-group-sm" style="width: 82px;">
                        <button type="button" class="btn btn-outline-secondary px-1.5 py-0" onclick="updatePosQty(${index}, -1)">-</button>
                        <span class="input-group-text bg-white px-2 py-0 fw-bold">${item.qty}</span>
                        <button type="button" class="btn btn-outline-secondary px-1.5 py-0" onclick="updatePosQty(${index}, 1)">+</button>
                    </div>
                    <span class="fw-bold text-dark font-monospace" style="min-width: 70px; text-align: right;">
                        Rp ${subtotal.toLocaleString('id-ID')}
                    </span>
                    <button type="button" class="btn btn-link text-danger p-0 ms-1" onclick="removePosItem(${index})" title="Hapus">
                        <i class="bi bi-trash"></i>
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

function posAddToCart(name, price, sku = 'SKU-CUSTOM') {
    const existing = posCart.find(i => i.name === name || i.sku === sku);
    if (existing) {
        existing.qty += 1;
    } else {
        posCart.push({ sku, name, price, qty: 1 });
    }
    renderPosCart();
}

function updatePosQty(index, delta) {
    if (posCart[index]) {
        posCart[index].qty += delta;
        if (posCart[index].qty <= 0) {
            posCart.splice(index, 1);
        }
        renderPosCart();
    }
}

function removePosItem(index) {
    if (posCart[index]) {
        posCart.splice(index, 1);
        renderPosCart();
    }
}

function clearPosCart() {
    posCart = [];
    renderPosCart();
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
function openPosReceiptModal() {
    const total = getPosTotal();
    if (total <= 0) return;

    const method = document.getElementById('posPaymentMethod').value;
    const now = new Date();
    const dateStr = now.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' WIB';

    document.getElementById('receiptDate').textContent = dateStr;
    document.getElementById('receiptTotalAmount').textContent = `Rp ${total.toLocaleString('id-ID')}`;

    // Itemized table
    const container = document.getElementById('receiptItemsContainer');
    let itemsHtml = '';
    posCart.forEach(item => {
        itemsHtml += `
            <div class="d-flex justify-content-between mb-1">
                <span>${item.name} (${item.qty}x)</span>
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
        const received = parseInt(document.getElementById('posCashReceived').value, 10) || total;
        document.getElementById('receiptCashReceived').textContent = `Rp ${received.toLocaleString('id-ID')}`;
        document.getElementById('receiptChangeAmount').textContent = `Rp ${(received - total).toLocaleString('id-ID')}`;
    } else if (method === 'BANK_TRANSFER') {
        badge.className = 'badge bg-primary';
        badge.textContent = 'TRANSFER / QRIS';
        document.getElementById('receiptCustomerName').textContent = 'Pembeli Non-Tunai';
        transDet.classList.remove('d-none');
        const ref = document.getElementById('posTransferRef').value.trim() || 'QRIS-VERIF-' + Math.floor(10000 + Math.random() * 90000);
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
        const dueDate = new Date();
        dueDate.setDate(dueDate.getDate() + 7);
        document.getElementById('receiptCreditDueDate').textContent = dueDate.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) + ' (Tempo 7 Hari)';

        // Deduct limit in mock session
        if (member) member.available_limit -= total;
    }

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

    document.getElementById('checkPhysicalHandover').checked = true;

    const modal = new bootstrap.Modal(document.getElementById('pickupConfirmModal'));
    modal.show();
}

function executePickupHandover() {
    if (!selectedPickupOrder) return;

    const modalEl = document.getElementById('pickupConfirmModal');
    const modalInstance = bootstrap.Modal.getInstance(modalEl);
    if (modalInstance) modalInstance.hide();

    // Mark row as complete in UI
    const row = document.getElementById(`pickupRow-${selectedPickupOrder.code}`);
    if (row) {
        const actionCell = row.querySelector('.handover-btn')?.parentElement;
        if (actionCell) {
            actionCell.innerHTML = `
                <span class="badge bg-success text-white py-1.5 px-2.5">
                    <i class="bi bi-check-all me-1"></i> Telah Diserahkan
                </span>
            `;
        }
        row.classList.add('table-light', 'text-muted');
    }

    // Show success modal
    document.getElementById('pickupSuccessMessage').innerHTML = `
        Pesanan <strong>${selectedPickupOrder.code}</strong> atas nama <strong>${selectedPickupOrder.name}</strong> berhasil diserahkan.<br>
        Stok fisik barang telah resmi dibukukan keluar.
    `;

    const successModal = new bootstrap.Modal(document.getElementById('pickupSuccessModal'));
    successModal.show();
}

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
    document.getElementById('vtfRefInput').value = ref || `TRX-BCA-${code.replace('PKP-', '')}`;
    document.getElementById('vtfConfirmFundsCheck').checked = false;
    document.getElementById('vtfRejectBox').classList.add('d-none');

    const modal = new bootstrap.Modal(document.getElementById('verifyTransferModal'));
    modal.show();
}

function toggleRejectTransferBox() {
    const box = document.getElementById('vtfRejectBox');
    box.classList.toggle('d-none');
}

function executeApproveTransfer() {
    if (!currentVerifyTransfer) return;
    const ref = document.getElementById('vtfRefInput').value.trim() || 'TRX-VERIFIED';
    const checked = document.getElementById('vtfConfirmFundsCheck').checked;

    if (!checked) {
        alert("Harap centang kotak konfirmasi bahwa dana telah dicek masuk rekening koperasi.");
        return;
    }

    const code = currentVerifyTransfer.code;
    const modalEl = document.getElementById('verifyTransferModal');
    const modalInstance = bootstrap.Modal.getInstance(modalEl);
    if (modalInstance) modalInstance.hide();

    // Update status badge di tabel antrian
    const badge = document.getElementById(`badgeTransfer-${code}`);
    if (badge) {
        badge.className = 'badge bg-success text-white';
        badge.innerHTML = `<i class="bi bi-check-circle-fill me-0.5"></i> Transfer Terverifikasi`;
        const container = badge.parentElement;
        const btnVerify = document.getElementById(`btnVerify-${code}`);
        if (btnVerify) btnVerify.remove();
        const refSmall = document.createElement('small');
        refSmall.className = 'text-muted font-monospace d-block';
        refSmall.style.fontSize = '0.68rem';
        refSmall.textContent = ref;
        container.appendChild(refSmall);
    }

    // Aktifkan tombol serah terima
    const btnHandover = document.getElementById(`btnHandover-${code}`);
    if (btnHandover) {
        btnHandover.disabled = false;
        btnHandover.className = 'btn btn-sm btn-outline-success px-2.5 py-1 fw-semibold d-inline-flex align-items-center gap-1 handover-btn';
        btnHandover.title = 'Serahkan barang ke pembeli';
    }

    document.getElementById('pickupSuccessMessage').innerHTML = `
        Pembayaran transfer untuk pesanan <strong>${code}</strong> (${currentVerifyTransfer.memberName}) <strong>BERHASIL DIVERIFIKASI</strong>.<br>
        Tombol Serah Terima barang kini telah aktif.
    `;
    const sModal = new bootstrap.Modal(document.getElementById('pickupSuccessModal'));
    sModal.show();
}

function executeRejectTransfer() {
    if (!currentVerifyTransfer) return;
    const reason = document.getElementById('vtfRejectReason').value;
    const code = currentVerifyTransfer.code;

    const modalEl = document.getElementById('verifyTransferModal');
    const modalInstance = bootstrap.Modal.getInstance(modalEl);
    if (modalInstance) modalInstance.hide();

    // Update status badge di tabel antrian
    const badge = document.getElementById(`badgeTransfer-${code}`);
    if (badge) {
        badge.className = 'badge bg-danger text-white';
        badge.innerHTML = `<i class="bi bi-x-circle-fill me-0.5"></i> Transfer Ditolak`;
        const container = badge.parentElement;
        const btnVerify = document.getElementById(`btnVerify-${code}`);
        if (btnVerify) btnVerify.remove();
        const reasonSmall = document.createElement('small');
        reasonSmall.className = 'text-danger d-block';
        reasonSmall.style.fontSize = '0.68rem';
        reasonSmall.textContent = reason;
        container.appendChild(reasonSmall);
    }

    // Tombol serah terima tetap nonaktif
    const btnHandover = document.getElementById(`btnHandover-${code}`);
    if (btnHandover) {
        btnHandover.disabled = true;
        btnHandover.className = 'btn btn-sm btn-outline-secondary opacity-50 px-2.5 py-1 fw-semibold d-inline-flex align-items-center gap-1 handover-btn';
        btnHandover.title = 'Pembayaran transfer ditolak';
    }

    document.getElementById('pickupSuccessMessage').innerHTML = `
        Pembayaran transfer untuk pesanan <strong>${code}</strong> telah <strong>DITOLAK</strong> (${reason}).<br>
        Pembeli wajib menyelesaikan pembayaran tunai di kasir sebelum barang diserahkan.
    `;
    const sModal = new bootstrap.Modal(document.getElementById('pickupSuccessModal'));
    sModal.show();
}

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

function executeCancelOrder() {
    if (!currentCancelOrder) return;
    const code = currentCancelOrder.code;

    const modalEl = document.getElementById('cancelOrderModal');
    const modalInstance = bootstrap.Modal.getInstance(modalEl);
    if (modalInstance) modalInstance.hide();

    const row = document.getElementById(`pickupRow-${code}`);
    if (row) {
        row.classList.add('table-light', 'text-muted');
        const actionCell = row.querySelector('.handover-btn')?.parentElement;
        if (actionCell) {
            actionCell.innerHTML = `
                <span class="badge bg-secondary text-white py-1.5 px-2">
                    <i class="bi bi-x-circle me-0.5"></i> Pesanan Dibatalkan
                </span>
            `;
        }
    }

    document.getElementById('pickupSuccessMessage').innerHTML = `
        Pesanan <strong>${code}</strong> berhasil dibatalkan.<br>
        <span class="text-success small fw-semibold">Reservasi stok dan penahanan limit kredit (credit hold) anggota telah dilepaskan otomatis tanpa membentuk piutang.</span>
    `;
    const sModal = new bootstrap.Modal(document.getElementById('pickupSuccessModal'));
    sModal.show();
}

// ==============================================================================
// 6. BARCODE SCANNER KAMERA NYATA (html5-qrcode CDN Integration)
// ==============================================================================
let html5QrCode = null;
let currentCameraId = null;
let availableCameras = [];
let isScannerRunning = false;

function playScanBeep() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(900, audioCtx.currentTime);
        gain.gain.setValueAtTime(0.18, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.12);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.12);
    } catch (e) {
        // AudioContext might be muted or blocked by browser policy
    }
}

function openBarcodeScannerModal() {
    const errorAlert = document.getElementById('scannerErrorAlert');
    const statusAlert = document.getElementById('scannerStatusAlert');
    const statusText = document.getElementById('scannerStatusText');

    errorAlert.classList.add('d-none');
    statusAlert.classList.remove('d-none');
    statusText.textContent = "Menginisialisasi modul kamera gerai...";

    const modal = new bootstrap.Modal(document.getElementById('barcodeScannerModal'));
    modal.show();

    setTimeout(() => {
        startBarcodeScanner();
    }, 400);
}

async function startBarcodeScanner() {
    if (isScannerRunning) return;

    const errorAlert = document.getElementById('scannerErrorAlert');
    const statusAlert = document.getElementById('scannerStatusAlert');
    const statusText = document.getElementById('scannerStatusText');
    const errorMessage = document.getElementById('scannerErrorMessage');
    const errorTitle = document.getElementById('scannerErrorTitle');

    try {
        if (!window.Html5Qrcode) {
            throw new Error("LibraryScannerBelumTerload");
        }

        if (!html5QrCode) {
            html5QrCode = new Html5Qrcode("qr-reader");
        }

        availableCameras = await Html5Qrcode.getCameras();
        if (!availableCameras || availableCameras.length === 0) {
            throw new Error("NoCameraFound");
        }

        // Cari kamera belakang jika di smartphone
        let backCam = availableCameras.find(cam => 
            cam.label.toLowerCase().includes('back') || 
            cam.label.toLowerCase().includes('rear') || 
            cam.label.toLowerCase().includes('belakang')
        );
        let selectedCamId = currentCameraId || (backCam ? backCam.id : availableCameras[0].id);
        currentCameraId = selectedCamId;

        const config = {
            fps: 15,
            qrbox: { width: 260, height: 160 },
            aspectRatio: 1.333
        };

        await html5QrCode.start(
            selectedCamId,
            config,
            onBarcodeScanSuccess,
            (errorMsg) => {
                // Ignore scanning noise
            }
        );

        isScannerRunning = true;
        statusText.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-record-circle-fill text-danger me-1"></i> Kamera Aktif:</span> Arahkan barcode sembako ke garis bidik.`;
    } catch (err) {
        console.warn("Scanner Initialization Notice:", err);
        isScannerRunning = false;
        statusAlert.classList.add('d-none');
        errorAlert.classList.remove('d-none');

        const errStr = (err.name || '') + ' ' + (err.message || '');
        if (errStr.includes('NotAllowedError') || errStr.toLowerCase().includes('permission')) {
            errorTitle.textContent = "Izin Kamera Ditolak Browser";
            errorMessage.textContent = "Akses kamera diblokir oleh izin browser. Silakan izinkan akses kamera pada icon gembok URL atau gunakan input SKU manual di bawah.";
        } else if (errStr.includes('NoCameraFound') || errStr.toLowerCase().includes('notfound')) {
            errorTitle.textContent = "Modul Kamera Tidak Ditemukan";
            errorMessage.textContent = "Komputer kasir tidak memiliki perangkat webcam/kamera terhubung. Gunakan scanner barcode USB atau input SKU manual.";
        } else {
            errorTitle.textContent = "Kamera Belum Dapat Diakses";
            errorMessage.textContent = "Tidak dapat membuka stream video (" + (err.message || 'Izin peramban dibutuhkan') + "). Silakan gunakan input manual di bawah.";
        }
    }
}

function onBarcodeScanSuccess(decodedText) {
    playScanBeep();
    stopBarcodeScanner();

    const modalEl = document.getElementById('barcodeScannerModal');
    const modalInstance = bootstrap.Modal.getInstance(modalEl);
    if (modalInstance) modalInstance.hide();

    handleIncomingBarcode(decodedText);
}

function stopBarcodeScanner() {
    if (html5QrCode && isScannerRunning) {
        html5QrCode.stop().then(() => {
            isScannerRunning = false;
        }).catch((err) => {
            console.warn("Stop scanner catch:", err);
            isScannerRunning = false;
        });
    }
}

async function switchScannerCamera() {
    if (availableCameras.length <= 1) {
        alert("Hanya ada 1 kamera yang terdeteksi pada perangkat kasir.");
        return;
    }
    stopBarcodeScanner();
    let currentIndex = availableCameras.findIndex(c => c.id === currentCameraId);
    let nextIndex = (currentIndex + 1) % availableCameras.length;
    currentCameraId = availableCameras[nextIndex].id;
    setTimeout(() => {
        startBarcodeScanner();
    }, 350);
}

function submitModalManualBarcode() {
    const val = document.getElementById('modalManualBarcodeInput').value.trim();
    if (!val) return;

    stopBarcodeScanner();
    const modalEl = document.getElementById('barcodeScannerModal');
    const modalInstance = bootstrap.Modal.getInstance(modalEl);
    if (modalInstance) modalInstance.hide();

    handleIncomingBarcode(val);
    document.getElementById('modalManualBarcodeInput').value = '';
}

function submitManualBarcodeField() {
    const val = document.getElementById('posBarcodeField').value.trim();
    if (!val) return;
    handleIncomingBarcode(val);
    document.getElementById('posBarcodeField').value = '';
}

function handleIncomingBarcode(code) {
    const clean = code.trim().toLowerCase();
    
    // Cari di katalog stok kasir
    const found = stockCatalogue.find(item => 
        item.sku.toLowerCase() === clean || 
        item.barcode.toLowerCase() === clean ||
        item.name.toLowerCase().includes(clean)
    );

    if (found) {
        posAddToCart(found.name, found.sell_price, found.sku);
    } else {
        // Fallback untuk barang non-katalog atau custom scan
        posAddToCart(`Komoditas Sembako #${code.toUpperCase()}`, 25000, code.toUpperCase());
    }
}

// Inisialisasi Keranjang Kasir Awal
document.addEventListener('DOMContentLoaded', () => {
    renderPosCart();
});
</script>
@endsection
