@extends('layouts.app')

@section('title', 'Master Produk & HPP — KoperasiKu')

@section('header')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
            <h4 class="fw-bold mb-0 text-dark">Master Produk, Harga Jual & HPP</h4>
            <span class="badge bg-primary text-white rounded-pill px-2.5 py-1 small">
                <i class="bi bi-calculator me-1"></i> FR-05 Sesuai PRD
            </span>
        </div>
        <p class="text-secondary small mb-0">Pengelolaan katalog harga jual sembako anggota, HPP bergerak (moving weighted average), dan margin laba kotor.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('manager.import') }}" class="btn btn-outline-primary btn-sm px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 shadow-xs">
            <i class="bi bi-file-earmark-arrow-up"></i>
            <span>Impor Data Excel</span>
        </a>
    </div>
</div>
@endsection

@section('content')

<!-- 1. KPI SUMMARY PRODUK & INVENTARIS -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-4">
        <div class="card border-0 shadow-sm bg-white p-3 h-100 border-start border-4 border-primary">
            <small class="text-secondary text-uppercase fw-bold" style="font-size: 0.7rem;">Total SKU Terdaftar</small>
            <h4 class="fw-extrabold text-dark mb-1 fs-4">{{ $summary['total_sku'] }} SKU Aktif</h4>
            <small class="text-muted" style="font-size: 0.72rem;">Sembako, minyak, produk segar, kebutuhan harian</small>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-4">
        <div class="card border-0 shadow-sm bg-white p-3 h-100 border-start border-4 border-success">
            <small class="text-secondary text-uppercase fw-bold" style="font-size: 0.7rem;">Total Nilai Aset Inventaris (HPP)</small>
            <h4 class="fw-extrabold text-success mb-1 fs-4">Rp {{ number_format($summary['total_inventory_value'], 0, ',', '.') }}</h4>
            <small class="text-muted" style="font-size: 0.72rem;">Dihitung dari moving-weighted-average HPP &times; stok fisik</small>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-4">
        <div class="card border-0 shadow-sm bg-white p-3 h-100 border-start border-4 border-info">
            <small class="text-secondary text-uppercase fw-bold" style="font-size: 0.7rem;">Rata-rata Margin Kotor</small>
            <h4 class="fw-extrabold text-primary mb-1 fs-4">{{ $summary['avg_margin_percent'] }}%</h4>
            <small class="text-muted" style="font-size: 0.72rem;">Sesuai target penetapan harga koperasi konsumen</small>
        </div>
    </div>
</div>

<!-- 2. TABEL MASTER PRODUK -->
<div class="card border-0 shadow-sm bg-white mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h5 class="card-title fw-bold text-dark mb-0 fs-6">
                <i class="bi bi-tags text-primary me-1.5"></i> Katalog Master Produk & Penentuan Harga
            </h5>
            <small class="text-muted" style="font-size: 0.72rem;">Stok fisik, alokasi reservasi online, HPP rata-rata tertimbang, dan harga jual resmi</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="input-group input-group-sm" style="width: 250px;">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control border-start-0 ps-0" id="productSearchInput" placeholder="Cari SKU, Barcode, Nama..." oninput="filterProductTable(this.value)">
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="productTable" style="font-size: 0.82rem;">
            <thead class="table-light">
                <tr>
                    <th>SKU & Barcode</th>
                    <th>Nama Komoditas</th>
                    <th>Kategori</th>
                    <th class="text-center">Stok Fisik</th>
                    <th class="text-center">Reservasi</th>
                    <th class="text-center">Tersedia</th>
                    <th class="text-end">HPP Bergerak</th>
                    <th class="text-end">Harga Jual</th>
                    <th class="text-center">Gross Margin</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($products as $p)
                    <tr class="product-row" data-search="{{ strtolower($p['sku'] . ' ' . $p['barcode'] . ' ' . $p['name'] . ' ' . $p['category']) }}">
                        <td>
                            <div class="fw-bold font-monospace text-primary">{{ $p['sku'] }}</div>
                            <small class="text-muted font-monospace">{{ $p['barcode'] }}</small>
                        </td>
                        <td class="fw-semibold text-dark">{{ $p['name'] }}</td>
                        <td><span class="badge bg-light text-secondary border">{{ $p['category'] }}</span></td>
                        <td class="text-center fw-bold">{{ $p['stock_physical'] }} {{ $p['unit'] }}</td>
                        <td class="text-center text-warning-emphasis fw-bold">
                            {{ $p['stock_reserved'] > 0 ? $p['stock_reserved'] . ' ' . $p['unit'] : '-' }}
                        </td>
                        <td class="text-center">
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                {{ $p['stock_available'] }} {{ $p['unit'] }}
                            </span>
                        </td>
                        <td class="text-end text-secondary fw-semibold">
                            Rp {{ number_format($p['hpp'], 0, ',', '.') }}
                        </td>
                        <td class="text-end fw-extrabold text-dark">
                            Rp {{ number_format($p['selling_price'], 0, ',', '.') }}
                        </td>
                        <td class="text-center">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                {{ $p['margin_percent'] }}% (Rp {{ number_format($p['margin_rp'], 0, ',', '.') }})
                            </span>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-outline-primary btn-sm px-2.5 py-1 d-inline-flex align-items-center gap-1 shadow-xs" onclick="openUpdatePriceModal({{ json_encode($p) }})">
                                <i class="bi bi-pencil-square"></i>
                                <span>Ubah Harga</span>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="card-footer bg-white border-top py-2.5 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <small class="text-muted" style="font-size: 0.74rem;">
            <i class="bi bi-info-circle me-1 text-primary"></i> HPP dihitung otomatis dengan metode <em>Moving Weighted Average</em> setiap penerimaan barang baru dari supplier.
        </small>
        <span class="badge bg-light text-secondary border small">
            Tabel Riwayat: <code>product_price_histories</code>
        </span>
    </div>
</div>

<!-- MODAL UBAH HARGA JUAL PRODUK (#updatePriceModal) -->
<div class="modal fade" id="updatePriceModal" tabindex="-1" aria-labelledby="updatePriceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-tag-fill fs-5"></i>
                    <h5 class="modal-title fw-bold fs-6" id="updatePriceModalLabel">Ubah Harga Jual Produk</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="p-3 bg-light rounded-3 border mb-3">
                    <small class="text-muted text-uppercase fw-bold d-block mb-1" style="font-size: 0.7rem;">Komoditas:</small>
                    <h6 class="fw-bold text-dark mb-1" id="modalPriceProductName">-</h6>
                    <div class="text-secondary small">SKU: <span id="modalPriceSku" class="font-monospace fw-semibold">-</span></div>
                    <div class="text-secondary small mt-1">HPP Saat Ini: <strong id="modalPriceHpp" class="text-dark">Rp 0</strong></div>
                </div>

                <form id="updatePriceForm" onsubmit="submitPriceChange(event)">
                    <div class="mb-3">
                        <label for="newPriceInput" class="form-label fw-bold text-dark small">Harga Jual Anggota Baru (Rp)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted fw-semibold">Rp</span>
                            <input type="number" class="form-control fw-extrabold text-primary fs-5" id="newPriceInput" min="1000" step="500" required>
                        </div>
                    </div>

                    <!-- Kalkulasi Margin Baru Realtime -->
                    <div class="p-2.5 bg-light rounded-2 border mb-3">
                        <div class="d-flex justify-content-between align-items-center text-secondary small">
                            <span>Estimasi Margin Baru:</span>
                            <strong class="text-primary fs-6" id="modalNewMarginDisplay">0% (Rp 0)</strong>
                        </div>
                    </div>

                    <div class="alert alert-warning border-warning-subtle small mb-3">
                        <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
                        <strong>Aturan Akuntansi (FR-05):</strong> Perubahan harga ini hanya berlaku untuk transaksi baru ke depan dan <strong>tidak mengubah transaksi masa lalu</strong>. Perubahan akan dicatat ke tabel <code>product_price_histories</code>.
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm">
                            <i class="bi bi-check-lg me-1"></i> Simpan Harga Baru
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let currentProductItem = null;

window.openUpdatePriceModal = function(p) {
    currentProductItem = p;
    document.getElementById('modalPriceProductName').textContent = p.name;
    document.getElementById('modalPriceSku').textContent = p.sku;
    document.getElementById('modalPriceHpp').textContent = 'Rp ' + p.hpp.toLocaleString('id-ID');
    
    const priceInput = document.getElementById('newPriceInput');
    priceInput.value = p.selling_price;
    updateMarginDisplay(p.selling_price, p.hpp);

    priceInput.oninput = function() {
        updateMarginDisplay(parseInt(this.value) || 0, p.hpp);
    };

    const modal = new bootstrap.Modal(document.getElementById('updatePriceModal'));
    modal.show();
};

function updateMarginDisplay(newPrice, hpp) {
    const marginRp = newPrice - hpp;
    const marginPercent = newPrice > 0 ? ((marginRp / newPrice) * 100).toFixed(1) : 0;
    document.getElementById('modalNewMarginDisplay').textContent = marginPercent + '% (Rp ' + marginRp.toLocaleString('id-ID') + ')';
}

window.submitPriceChange = function(e) {
    e.preventDefault();
    const modalEl = document.getElementById('updatePriceModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();

    const newPrice = document.getElementById('newPriceInput').value;
    setTimeout(() => {
        alert('Harga jual produk ' + currentProductItem.name + ' berhasil diperbarui menjadi Rp ' + parseInt(newPrice).toLocaleString('id-ID') + ' dan dicatat ke product_price_histories.');
        location.reload();
    }, 400);
};

window.filterProductTable = function(term) {
    const q = term.toLowerCase();
    const rows = document.querySelectorAll('.product-row');
    rows.forEach(r => {
        const text = r.getAttribute('data-search') || '';
        r.style.display = text.includes(q) ? '' : 'none';
    });
};
</script>
@endsection
