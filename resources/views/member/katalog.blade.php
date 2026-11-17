@extends('layouts.app')

@section('title', 'Portal Belanja Anggota & Cek Hutang')

@push('styles')
    @vite(['resources/css/landing.css'])
@endpush

@section('header')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="fw-bold mb-0 text-dark">Portal Belanja Anggota</h4>
            <span class="badge bg-primary text-white rounded-pill px-2.5 py-1 small">
                <i class="bi bi-person-check me-1"></i> Anggota Terdaftar
            </span>
        </div>
        <p class="text-secondary small mb-0">Pesan sembako keluarga, cek status piutang, dan pantau kode pickup gerai.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-outline-primary btn-sm px-3 py-2 fw-semibold d-inline-flex align-items-center gap-2 shadow-xs position-relative" data-bs-toggle="offcanvas" data-bs-target="#cartDrawer">
            <i class="bi bi-cart3"></i>
            <span>Keranjang Belanja</span>
            <span class="badge bg-danger rounded-pill d-none" id="desktopCartBadge">0</span>
        </button>
        <a href="#katalog-grid" class="btn btn-primary btn-sm px-3 py-2 fw-semibold d-inline-flex align-items-center gap-2 shadow-xs">
            <i class="bi bi-bag-check"></i>
            <span>Katalog Sembako</span>
        </a>
    </div>
</div>
@endsection

@section('content')

<!-- 1. WIDGET CEK STATUS HUTANG & FASILITAS PIUTANG SAYA -->
<div class="card border-0 shadow-sm mb-4 bg-white overflow-hidden">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <div class="p-2 rounded-2 bg-warning-subtle text-warning-emphasis d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                <i class="bi bi-wallet2 fs-5"></i>
            </div>
            <div>
                <h5 class="card-title fw-bold text-dark mb-0 fs-6">Status Fasilitas Piutang & Tagihan Saya</h5>
                <small class="text-muted" style="font-size: 0.72rem;">Fasilitas tempo 7 hari kalender bunga 0% khusus anggota</small>
            </div>
        </div>
        <div>
            @if($receivableStatus['has_debt'])
                <span class="badge bg-warning text-dark px-3 py-1.5 fw-bold rounded-pill">
                    <i class="bi bi-clock-history me-1"></i> Ada Tagihan Berjalan
                </span>
            @else
                <span class="badge bg-success text-white px-3 py-1.5 fw-bold rounded-pill">
                    <i class="bi bi-check-circle me-1"></i> Tidak Ada Tagihan (Lunas)
                </span>
            @endif
        </div>
    </div>

    <div class="card-body p-3 p-md-4">
        <div class="row g-3">
            <!-- Metric 1: Total Hutang Aktif -->
            <div class="col-12 col-md-4">
                <div class="p-3 rounded-2 bg-light border h-100">
                    <small class="text-secondary text-uppercase fw-bold d-block mb-1" style="font-size: 0.72rem;">Total Tagihan / Hutang Aktif</small>
                    <div class="d-flex align-items-baseline gap-1 mb-1">
                        <span class="fs-5 fw-extrabold text-danger">Rp {{ number_format($receivableStatus['total_debt'], 0, ',', '.') }}</span>
                    </div>
                    <small class="text-danger fw-semibold d-block" style="font-size: 0.75rem;">
                        <i class="bi bi-exclamation-circle me-1"></i> Jatuh tempo: {{ $receivableStatus['due_date'] }} ({{ $receivableStatus['days_remaining'] }} hari lagi)
                    </small>
                </div>
            </div>

            <!-- Metric 2: Sisa Limit Belanja Kredit -->
            <div class="col-12 col-md-4">
                <div class="p-3 rounded-2 bg-light border h-100">
                    <small class="text-secondary text-uppercase fw-bold d-block mb-1" style="font-size: 0.72rem;">Sisa Limit Belanja Piutang</small>
                    <div class="d-flex align-items-baseline gap-1 mb-1">
                        <span class="fs-5 fw-extrabold text-success">Rp {{ number_format($receivableStatus['available_limit'], 0, ',', '.') }}</span>
                        <span class="text-muted small">/ {{ number_format($receivableStatus['credit_limit'], 0, ',', '.') }}</span>
                    </div>
                    <small class="text-success fw-medium d-block" style="font-size: 0.75rem;">
                        <i class="bi bi-shield-check me-1"></i> Limit aktif & dapat digunakan belanja
                    </small>
                </div>
            </div>

            <!-- Metric 3: Lokasi Pelunasan Kasir -->
            <div class="col-12 col-md-4">
                <div class="p-3 rounded-2 bg-light border h-100 d-flex flex-column justify-content-between">
                    <div>
                        <small class="text-secondary text-uppercase fw-bold d-block mb-1" style="font-size: 0.72rem;">Pelunasan Piutang</small>
                        <p class="small text-secondary mb-2 lh-sm" style="font-size: 0.78rem;">
                            Dapat dibayar tunai atau transfer via kasir gerai di Jl. Kanayakan No. A11 Dago.
                        </p>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm w-100 fw-semibold" data-bs-toggle="modal" data-bs-target="#invoicesDetailModal">
                        <i class="bi bi-receipt me-1"></i> Lihat Rincian Faktur Hutang
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 2. KOTAK PESANAN AKTIF (MENUNGGU PENGAMBILAN DI GERAI) -->
@if(!empty($activeOrders))
<div class="card border-0 shadow-sm mb-4 bg-primary text-white">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <span class="badge bg-warning text-dark fw-bold px-2.5 py-1 rounded-pill mb-2" style="font-size: 0.7rem;">
                    <i class="bi bi-box-seam me-1"></i> PESANAN SIAP DIAMBIL
                </span>
                <h5 class="fw-bold mb-1 fs-5">Tunjukkan Kode Pickup ke Petugas Kasir</h5>
                <p class="small text-white text-opacity-90 mb-0">
                    {{ $activeOrders[0]['store_location'] }} &bull; Total: <strong>Rp {{ number_format($activeOrders[0]['total_amount'], 0, ',', '.') }}</strong> ({{ $activeOrders[0]['items_count'] }} Barang)
                </p>
            </div>
            <div class="text-md-end">
                <div class="p-2.5 px-4 rounded-3 bg-white text-primary text-center shadow-xs">
                    <small class="text-muted fw-bold d-block" style="font-size: 0.68rem; letter-spacing: 0.05em;">KODE PICKUP RESMI:</small>
                    <div class="fs-3 fw-extrabold font-monospace text-primary tracking-wide">
                        {{ $activeOrders[0]['pickup_code'] }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<!-- 3. KATALOG PRODUK KHUSUS BELANJA ANGGOTA -->
<div id="katalog-grid" class="card border-0 shadow-sm bg-white">
    <div class="card-header bg-white py-3 border-bottom">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h5 class="card-title fw-bold text-dark mb-0 fs-6">Katalog Sembako & Kebutuhan Harian</h5>
                <small class="text-muted" style="font-size: 0.75rem;">Stok tersedia (available) dijamin ter-reservasi saat Anda melakukan checkout</small>
            </div>

            <!-- Search Input Inside Member Portal -->
            <div style="max-width: 340px;" class="w-100">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="memberSearchInput" class="form-control" placeholder="Cari beras, minyak, gula..." oninput="filterMemberCatalog(this.value)">
                </div>
            </div>
        </div>
    </div>

    <div class="card-body p-3 p-md-4">
        <!-- Category Filter Pills -->
        <div class="d-flex align-items-center gap-1.5 flex-wrap mb-4 pb-2 border-bottom">
            @foreach($categories as $index => $cat)
                <button type="button" class="btn btn-sm {{ $index === 0 ? 'btn-primary' : 'btn-outline-secondary' }} px-3 py-1 rounded-pill category-filter-btn" onclick="filterMemberCategory('{{ $cat }}', this)">
                    {{ $cat }}
                </button>
            @endforeach
        </div>

        <!-- Products Grid (4-5 Columns) -->
        <div class="row row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3" id="memberProductsList">
            @foreach($products as $item)
                <div class="col member-product-item" data-name="{{ strtolower($item['name']) }}" data-category="{{ strtolower($item['category']) }}">
                    <div class="card h-100 border p-2.5 shadow-none hover-shadow transition-base">
                        <!-- Thumbnail Box with Real Image & Fallback -->
                        <div class="bg-light rounded-2 d-flex align-items-center justify-content-center mb-2.5 position-relative overflow-hidden" style="height: 140px; cursor: pointer;" onclick="openProductDetailModal('{{ $item['id'] }}')" role="button" tabindex="0" title="Klik untuk lihat rincian {{ $item['name'] }}">
                            @if(!empty($item['image']))
                                <img src="{{ $item['image'] }}" alt="{{ $item['name'] }}" class="w-100 h-100 object-fit-cover" loading="lazy" onerror="this.classList.add('d-none'); this.nextElementSibling.classList.remove('d-none');">
                                <div class="w-100 h-100 d-none d-flex align-items-center justify-content-center text-secondary">
                                    <i class="bi {{ $item['icon'] }}" style="font-size: 2.5rem;"></i>
                                </div>
                            @else
                                <i class="bi {{ $item['icon'] }} text-secondary" style="font-size: 2.5rem;"></i>
                            @endif

                            @if(!empty($item['badge']))
                                <span class="position-absolute top-0 start-0 m-2 badge bg-primary text-white" style="font-size: 0.65rem;">
                                    {{ $item['badge'] }}
                                </span>
                            @endif
                        </div>

                        <div class="d-flex flex-column flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="text-muted text-uppercase fw-bold text-truncate" style="font-size: 0.68rem;">
                                    {{ $item['category'] }}
                                </small>
                                <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.65rem;">
                                    Stok: {{ $item['available_stock'] }}
                                </span>
                            </div>

                            <div class="d-flex align-items-center gap-1 mb-1 small text-warning" style="font-size: 0.72rem;">
                                <i class="bi bi-star-fill"></i>
                                <span class="fw-bold text-dark">{{ $item['rating'] ?? 4.9 }}</span>
                                <span class="text-muted">({{ $item['reviews_count'] ?? 20 }})</span>
                            </div>

                            <h6 class="fw-bold text-dark text-truncate mb-2" title="{{ $item['name'] }}" onclick="openProductDetailModal('{{ $item['id'] }}')" role="button" style="cursor: pointer;">
                                {{ $item['name'] }}
                            </h6>

                            <div class="mt-auto pt-2 border-top d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="fw-extrabold text-primary fs-6">
                                        Rp {{ number_format($item['price'], 0, ',', '.') }}
                                    </div>
                                    @if(!empty($item['original_price']) && $item['original_price'] > $item['price'])
                                        <del class="text-muted" style="font-size: 0.68rem;">Rp {{ number_format($item['original_price'], 0, ',', '.') }}</del>
                                    @else
                                        <small class="text-muted" style="font-size: 0.68rem;">per {{ $item['unit'] }}</small>
                                    @endif
                                </div>
                                <button type="button" class="btn btn-sm btn-primary px-2.5 py-1 d-inline-flex align-items-center gap-1 shadow-xs fw-semibold" onclick="openProductDetailModal('{{ $item['id'] }}')">
                                    <i class="bi bi-eye"></i>
                                    <span>Detail</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<!-- Modal Rincian Faktur Hutang -->
<div class="modal fade" id="invoicesDetailModal" tabindex="-1" aria-labelledby="invoicesDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark fs-6" id="invoicesDetailModalLabel">
                    <i class="bi bi-receipt text-primary me-2"></i> Rincian Tagihan Piutang Anggota
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-warning d-flex align-items-center gap-2 mb-3 py-2 small">
                    <i class="bi bi-info-circle-fill fs-5 text-warning"></i>
                    <div>Jatuh tempo <strong>7 hari kalender</strong> sejak serah terima barang di kasir gerai.</div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0" style="font-size: 0.82rem;">
                        <thead class="table-light">
                            <tr>
                                <th>No. Faktur</th>
                                <th>Tanggal</th>
                                <th>Barang</th>
                                <th class="text-end">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($receivableStatus['active_invoices'] as $inv)
                                <tr>
                                    <td class="fw-bold text-primary">{{ $inv['invoice_no'] }}</td>
                                    <td>{{ $inv['date'] }}</td>
                                    <td>{{ $inv['items_summary'] }}</td>
                                    <td class="text-end fw-bold text-danger">Rp {{ number_format($inv['amount'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="3" class="fw-bold">Total Tagihan Berjalan</td>
                                <td class="text-end fw-extrabold text-danger">Rp {{ number_format($receivableStatus['total_debt'], 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

    {{-- DRAWER & MODAL PEMESANAN LANGSUNG KONSUMEN / ANGGOTA --}}
    @include('partials.landing.cart-drawer')
    @include('partials.landing.checkout-modal')
    @include('partials.landing.product-detail-modal')

@endsection

@push('scripts')
@vite(['resources/js/landing.js'])
<script>
    window.allProducts = @json($allProductsMap ?? []);

    function filterMemberCatalog(rawQuery) {
        const query = (rawQuery || '').toLowerCase().trim();
        const items = document.querySelectorAll('.member-product-item');
        items.forEach(el => {
            const name = el.getAttribute('data-name') || '';
            const cat = el.getAttribute('data-category') || '';
            if (!query || name.includes(query) || cat.includes(query)) {
                el.classList.remove('d-none');
            } else {
                el.classList.add('d-none');
            }
        });
    }

    function filterMemberCategory(categoryName, btn) {
        document.querySelectorAll('.category-filter-btn').forEach(b => {
            b.classList.remove('btn-primary');
            b.classList.add('btn-outline-secondary');
        });
        btn.classList.remove('btn-outline-secondary');
        btn.classList.add('btn-primary');

        if (categoryName === 'Semua') {
            filterMemberCatalog('');
        } else {
            filterMemberCatalog(categoryName);
        }
    }
</script>
@endpush
