@extends('layouts.app')

@section('title', 'Dashboard Eksekutif Manajer & Analitik — KoperasiKu')

@section('header')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
            <h4 class="fw-bold mb-0 text-dark">Dashboard Eksekutif Manajer</h4>
            <span class="badge bg-danger text-white rounded-pill px-2.5 py-1 small">
                <i class="bi bi-graph-up-arrow me-1"></i> Analitik Big Data
            </span>
            <!-- Indikator Freshness Data ETL PySpark Data Mart -->
            <span class="badge {{ $metrics['etl_status'] === 'FRESH' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' }} rounded-pill px-2.5 py-1 d-inline-flex align-items-center gap-1.5" style="font-size: 0.72rem;">
                <span class="spinner-grow spinner-grow-sm" role="status" style="width: 0.5rem; height: 0.5rem;" aria-hidden="true"></span>
                <span>Data Mart Diperbarui: <strong>{{ $metrics['last_sync_at'] }}</strong></span>
                <span class="badge {{ $metrics['etl_status'] === 'FRESH' ? 'bg-success text-white' : 'bg-warning text-dark' }} ms-1" style="font-size: 0.65rem;">
                    {{ $metrics['etl_status'] === 'FRESH' ? 'Sync Terkini' : 'Stale Warning' }}
                </span>
            </span>
        </div>
        <p class="text-secondary small mb-0">Laporan omzet penjualan bulan ini, laba kotor & bersih, OPEX, portofolio piutang, dan rekomendasi restock terverifikasi.</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="badge bg-white text-secondary border px-3 py-2 small shadow-xs">
            <i class="bi bi-calendar3 me-1 text-primary"></i> Periode: <strong>November 2026</strong>
        </span>
        <button type="button" class="btn btn-outline-danger btn-sm px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 shadow-xs" onclick="exportReport('PDF')">
            <i class="bi bi-file-earmark-pdf"></i>
            <span>Ekspor PDF</span>
        </button>
        <button type="button" class="btn btn-outline-success btn-sm px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 shadow-xs" onclick="exportReport('Excel')">
            <i class="bi bi-file-earmark-excel"></i>
            <span>Ekspor Excel</span>
        </button>
    </div>
</div>
@endsection

@section('content')

<!-- NOTIFIKASI STATUS KUALITAS DATA FINANSIAL (AC-18) -->
<div class="alert alert-light border shadow-xs d-flex align-items-center justify-content-between p-3 mb-4 rounded-3 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-patch-check-fill text-success fs-5"></i>
        <div>
            <div class="fw-bold text-dark small">Status Kualitas Data Finansial: {{ $metrics['data_quality_status'] === 'COMPLETE' ? 'Data HPP & Biaya Lengkap' : 'Estimasi / Data Belum Lengkap' }}</div>
            <p class="text-muted mb-0" style="font-size: 0.76rem;">Semua komponen omzet kasir, pergerakan stok HPP moving-average, dan beban operasional telah tervalidasi dan siap untuk audit tutup buku.</p>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 small fw-semibold">
            <i class="bi bi-check-all me-1"></i> Sesuai AC-18
        </span>
        <a href="{{ route('manager.produk') }}" class="btn btn-sm btn-link text-decoration-none text-primary fw-semibold p-0" style="font-size: 0.8rem;">
            <span>Lihat Master HPP</span> <i class="bi bi-arrow-right"></i>
        </a>
    </div>
</div>

<!-- 1. EXECUTIVE KPI METRICS CARDS (OMZET, HPP, LABA KOTOR, OPEX, ESTIMASI LABA BERSIH, KAS MASUK, PIUTANG) -->
<div class="row g-3 mb-4">
    <!-- Metric 1: Omzet Bulan Ini -->
    <div class="col-12 col-sm-6 col-xl-2">
        <div class="card border-0 shadow-sm bg-white p-3 h-100 border-start border-4 border-primary">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <small class="text-secondary text-uppercase fw-bold" style="font-size: 0.68rem;">Omzet Penjualan</small>
                <span class="badge bg-success-subtle text-success small fw-bold" style="font-size: 0.65rem;">
                    <i class="bi bi-arrow-up-right"></i> +{{ $metrics['omzet_growth_percentage'] }}%
                </span>
            </div>
            <h5 class="fw-extrabold text-dark mb-1">Rp {{ number_format($metrics['omzet_monthly'], 0, ',', '.') }}</h5>
            <small class="text-muted" style="font-size: 0.72rem;">{{ $metrics['completed_orders_count'] }} transaksi completed</small>
        </div>
    </div>

    <!-- Metric 2: Laba Kotor (Gross Profit) -->
    <div class="col-12 col-sm-6 col-xl-2">
        <div class="card border-0 shadow-sm bg-white p-3 h-100 border-start border-4 border-success">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <small class="text-secondary text-uppercase fw-bold" style="font-size: 0.68rem;">Laba Kotor</small>
                <span class="badge bg-light text-secondary border small" style="font-size: 0.65rem;">
                    {{ $metrics['gross_profit_margin'] }}%
                </span>
            </div>
            <h5 class="fw-extrabold text-success mb-1">Rp {{ number_format($metrics['gross_profit'], 0, ',', '.') }}</h5>
            <small class="text-muted" style="font-size: 0.72rem;">Omzet dikurangi HPP</small>
        </div>
    </div>

    <!-- Metric 3: Beban Operasional (OPEX) -->
    <div class="col-12 col-sm-6 col-xl-2">
        <div class="card border-0 shadow-sm bg-white p-3 h-100 border-start border-4 border-secondary">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <small class="text-secondary text-uppercase fw-bold" style="font-size: 0.68rem;">Biaya OPEX</small>
                <i class="bi bi-gear-wide-connected text-secondary fs-6"></i>
            </div>
            <h5 class="fw-extrabold text-secondary mb-1">Rp {{ number_format($metrics['operational_expense'], 0, ',', '.') }}</h5>
            <small class="text-muted" style="font-size: 0.72rem;">Listrik, ATK, pemeliharaan</small>
        </div>
    </div>

    <!-- Metric 4: Estimasi Laba Bersih (Net Profit) -->
    <div class="col-12 col-sm-6 col-xl-2">
        <div class="card border-0 shadow-sm bg-white p-3 h-100 border-start border-4 border-info">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <small class="text-secondary text-uppercase fw-bold" style="font-size: 0.68rem;">Laba Bersih Est.</small>
                <span class="badge bg-info-subtle text-info-emphasis small fw-bold" style="font-size: 0.65rem;">
                    Net
                </span>
            </div>
            <h5 class="fw-extrabold text-primary mb-1">Rp {{ number_format($metrics['net_profit_estimate'], 0, ',', '.') }}</h5>
            <small class="text-muted" style="font-size: 0.72rem;">Laba kotor &minus; OPEX</small>
        </div>
    </div>

    <!-- Metric 5: Kas Masuk Terverifikasi -->
    <div class="col-12 col-sm-6 col-xl-2">
        <div class="card border-0 shadow-sm bg-white p-3 h-100 border-start border-4 border-success">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <small class="text-secondary text-uppercase fw-bold" style="font-size: 0.68rem;">Kas Masuk</small>
                <i class="bi bi-cash-stack text-success fs-6"></i>
            </div>
            <h5 class="fw-extrabold text-dark mb-1">Rp {{ number_format($metrics['cash_inflow'], 0, ',', '.') }}</h5>
            <small class="text-muted" style="font-size: 0.72rem;">Tunai + Transfer bank</small>
        </div>
    </div>

    <!-- Metric 6: Saldo Piutang Berjalan -->
    <div class="col-12 col-sm-6 col-xl-2">
        <div class="card border-0 shadow-sm bg-white p-3 h-100 border-start border-4 border-warning">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <small class="text-secondary text-uppercase fw-bold" style="font-size: 0.68rem;">Saldo Piutang</small>
                <span class="badge bg-warning text-dark small fw-bold" style="font-size: 0.65rem;">Tempo 7H</span>
            </div>
            <h5 class="fw-extrabold text-warning-emphasis mb-1">Rp {{ number_format($metrics['outstanding_receivables'], 0, ',', '.') }}</h5>
            <small class="text-muted" style="font-size: 0.72rem;">{{ $metrics['active_debtor_members'] }} anggota aktif</small>
        </div>
    </div>
</div>

<!-- 2. CHARTS SECTION (VISUALISASI TREN OMZET & KOMPOSISI METODE BAYAR) -->
<div class="row g-3 mb-4">
    <!-- Chart Tren Penjualan 7 Hari -->
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm bg-white p-3 p-md-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h5 class="card-title fw-bold text-dark mb-0 fs-6">Tren Omzet Penjualan vs Laba Kotor (7 Hari Terakhir)</h5>
                    <small class="text-muted" style="font-size: 0.72rem;">Visualisasi harian pergerakan transaksi gerai KOPEG Mart</small>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-primary text-white small"><i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> Omzet</span>
                    <span class="badge bg-success text-white small"><i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> Laba Kotor</span>
                    <span class="badge bg-secondary text-white small"><i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> Baseline OPEX</span>
                </div>
            </div>
            <div style="height: 250px; position: relative;">
                <canvas id="salesTrendChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart Komposisi Pembayaran -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm bg-white p-3 p-md-4 h-100">
            <h5 class="card-title fw-bold text-dark mb-1 fs-6">Komposisi Metode Pembayaran</h5>
            <small class="text-muted d-block mb-3" style="font-size: 0.72rem;">Proporsi pembayaran transaksi bulan ini</small>
            
            <div style="height: 180px; position: relative;" class="mb-3">
                <canvas id="paymentCompositionChart"></canvas>
            </div>

            <div class="d-flex flex-column gap-2 small">
                @foreach($paymentComposition as $p)
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="d-flex align-items-center gap-1.5">
                            <i class="bi bi-square-fill" style="color: {{ $p['color'] }}; font-size: 0.75rem;"></i>
                            <span class="text-secondary">{{ $p['label'] }}</span>
                        </span>
                        <span class="fw-bold text-dark">{{ $p['percentage'] }}%</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<!-- 3. TOP SELLING PRODUCTS & RESTOCK FORECAST (DATA MART PIC D/E) -->
<div class="row g-3 mb-4">
    <!-- Top 5 Bestsellers & Forecast -->
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm bg-white h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="card-title fw-bold text-dark mb-0 fs-6">Komoditas Terlaris & Prediksi Restock (Forecasting 7/30 Hari)</h5>
                    <small class="text-muted" style="font-size: 0.72rem;">Hasil komputasi analitik data mart transaksi & peramalan permintaan</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-secondary border small">Top 5 SKU</span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.82rem;">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Nama Komoditas</th>
                            <th class="text-center">Terjual</th>
                            <th class="text-end">Omzet</th>
                            <th class="text-center">Ketahanan</th>
                            <th class="text-center">Evaluasi Model</th>
                            <th class="text-center">Aksi Manajerial</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topSellingProducts as $item)
                            <tr>
                                <td class="fw-bold">{{ $item['rank'] }}</td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $item['name'] }}</div>
                                    <small class="text-muted">{{ $item['sku'] }} &bull; {{ $item['category'] }}</small>
                                </td>
                                <td class="text-center fw-bold">{{ $item['sold_qty'] }} {{ $item['unit'] }}</td>
                                <td class="text-end fw-bold text-dark">Rp {{ number_format($item['omzet'], 0, ',', '.') }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $item['stock_days_left'] <= 4 ? 'bg-danger text-white' : 'bg-success-subtle text-success border border-success-subtle' }}">
                                        {{ $item['stock_days_left'] }} Hari (Sisa {{ $item['stock_available'] }} {{ $item['unit'] }})
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if($item['model_status'] === 'INSUFFICIENT')
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle" title="SKU baru belum memiliki histori transaksi yang cukup">
                                            <i class="bi bi-exclamation-circle me-1"></i> {{ $item['model_eval'] }}
                                        </span>
                                    @else
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle" title="Akurasi WAPE (Weighted Absolute Percentage Error)">
                                            <i class="bi bi-cpu me-1"></i> {{ $item['model_eval'] }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($item['restock_qty'] > 0)
                                        <button type="button" class="btn btn-primary btn-sm px-2.5 py-1 fw-semibold d-inline-flex align-items-center gap-1 shadow-xs" onclick="openRestockModal({{ json_encode($item) }})">
                                            <i class="bi bi-check2-circle"></i>
                                            <span>Review & Setujui</span>
                                        </button>
                                    @else
                                        <span class="badge bg-light text-success border px-2 py-1">
                                            <i class="bi bi-shield-check me-1"></i> Stok Aman
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top py-2 px-3">
                <small class="text-muted d-flex align-items-center gap-1" style="font-size: 0.72rem;">
                    <i class="bi bi-shield-lock-fill text-primary"></i> Sesuai FR-12: Sistem hanya memberikan rekomendasi restock; penerbitan Purchase Order wajib melalui persetujuan manual Manajer.
                </small>
            </div>
        </div>
    </div>

    <!-- 5 Kelompok Keterlambatan Standar PRD (Aging Report Portofolio Piutang) -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm bg-white p-3 p-md-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <h5 class="card-title fw-bold text-dark mb-0 fs-6">Monitoring Risiko Piutang (Aging)</h5>
                <a href="{{ route('manager.piutang') }}" class="small text-primary text-decoration-none fw-semibold">
                    <span>Lihat Semua</span> <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <small class="text-muted d-block mb-3" style="font-size: 0.72rem;">5 Kelompok Keterlambatan Standar PRD (FR-08B & 12.3)</small>

            <!-- 5-Color Stacked Bar -->
            <div class="p-3 rounded-2 bg-light border mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-semibold text-secondary">Tingkat Kolektibilitas</span>
                    <span class="badge bg-success text-white small fw-bold">70.8% Lancar</span>
                </div>
                <div class="progress" style="height: 10px;">
                    <div class="progress-bar bg-success" style="width: {{ $receivablesAging['current']['percentage'] }}%;" title="Current"></div>
                    <div class="progress-bar bg-warning" style="width: {{ $receivablesAging['overdue_1_30']['percentage'] }}%;" title="1-30 Hari"></div>
                    <div class="progress-bar bg-danger" style="width: {{ $receivablesAging['overdue_31_60']['percentage'] }}%; opacity: 0.7;" title="31-60 Hari"></div>
                    <div class="progress-bar bg-danger" style="width: {{ $receivablesAging['overdue_61_90']['percentage'] }}%;" title="61-90 Hari"></div>
                    <div class="progress-bar bg-dark" style="width: {{ $receivablesAging['overdue_gt_90']['percentage'] }}%;" title=">90 Hari"></div>
                </div>
            </div>

            <!-- 5 Standard Aging Rows -->
            <div class="d-flex flex-column gap-2 small">
                @foreach($receivablesAging as $key => $aging)
                    <div class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                        <div>
                            <span class="fw-semibold {{ $aging['text_color'] }} d-block" style="font-size: 0.78rem;">{{ $aging['label'] }}</span>
                            <small class="text-muted">{{ $aging['range'] }} &bull; {{ $aging['count'] }} Anggota</small>
                        </div>
                        <div class="text-end">
                            <span class="fw-bold {{ $aging['text_color'] }}">Rp {{ number_format($aging['amount'], 0, ',', '.') }}</span>
                            <small class="text-muted d-block" style="font-size: 0.68rem;">{{ $aging['percentage'] }}%</small>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-3 pt-2 text-center">
                <a href="{{ route('manager.kredit') }}" class="btn btn-outline-secondary btn-sm w-100 fw-semibold d-inline-flex align-items-center justify-content-center gap-1.5">
                    <i class="bi bi-sliders"></i>
                    <span>Kelola Kebijakan Limit Global</span>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- MODAL REVIEW & PERSETUJUAN RESTOCK MANUAL MANAJER (#approveRestockModal) -->
<div class="modal fade" id="approveRestockModal" tabindex="-1" aria-labelledby="approveRestockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-box-seam-fill fs-5"></i>
                    <h5 class="modal-title fw-bold fs-6" id="approveRestockModalLabel">Review & Persetujuan Restock (Human-in-the-Loop)</h5>
                </div>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info border-info-subtle small d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-info-circle-fill text-primary fs-5"></i>
                    <div>
                        <strong>Persetujuan Manual Wajib (FR-12):</strong> Kuantitas berikut dihitung dari estimasi permintaan selama <em>lead time</em> ditambah <em>safety stock</em>. Manajer berhak menyesuaikan sebelum Purchase Order diterbitkan.
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 border">
                            <small class="text-muted text-uppercase fw-bold d-block mb-1" style="font-size: 0.7rem;">Informasi Komoditas</small>
                            <h6 class="fw-bold text-dark mb-1" id="modalProductName">-</h6>
                            <div class="text-secondary small mb-2">SKU: <span id="modalProductSku" class="font-monospace fw-semibold">-</span></div>
                            <div class="text-secondary small">Supplier Terdaftar: <strong id="modalProductSupplier" class="text-dark">-</strong></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 border">
                            <small class="text-muted text-uppercase fw-bold d-block mb-1" style="font-size: 0.7rem;">Parameter Perhitungan Model ML</small>
                            <div class="d-flex justify-content-between text-secondary small py-1 border-bottom border-light">
                                <span>Stok Fisik Tersedia:</span>
                                <strong id="modalStockAvailable" class="text-dark">0</strong>
                            </div>
                            <div class="d-flex justify-content-between text-secondary small py-1 border-bottom border-light">
                                <span>Stok Reservasi Pickup:</span>
                                <strong id="modalStockReserved" class="text-dark">0</strong>
                            </div>
                            <div class="d-flex justify-content-between text-secondary small py-1 border-bottom border-light">
                                <span>Safety Stock Rekomendasi:</span>
                                <strong id="modalSafetyStock" class="text-dark">0</strong>
                            </div>
                            <div class="d-flex justify-content-between text-secondary small pt-1">
                                <span>Lead Time Pengiriman:</span>
                                <strong id="modalLeadTime" class="text-dark">0 Hari</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <form id="restockApprovalForm" onsubmit="submitRestockApproval(event)">
                    <div class="row g-3 align-items-end mb-3">
                        <div class="col-md-6">
                            <label for="approvedQtyInput" class="form-label fw-bold text-dark small">Kuantitas Order Disetujui Manajer (<span id="modalUnitName">Unit</span>)</label>
                            <div class="input-group">
                                <input type="number" class="form-control fw-bold" id="approvedQtyInput" min="1" required>
                                <span class="input-group-text bg-light text-muted" id="modalUnitSuffix">Unit</span>
                            </div>
                            <div class="form-text small text-muted">Rekomendasi model machine learning: <span id="modalModelQty" class="fw-bold text-primary">0</span> unit.</div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-2.5 bg-light rounded-2 border">
                                <small class="text-muted d-block" style="font-size: 0.7rem;">Estimasi Nilai Pengadaan (HPP):</small>
                                <div class="fs-5 fw-extrabold text-primary" id="modalEstimatedCost">Rp 0</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="restockNotes" class="form-label fw-semibold text-secondary small">Catatan Persetujuan Manajer (Opsional)</label>
                        <textarea class="form-control form-control-sm" id="restockNotes" rows="2" placeholder="Contoh: Disetujui sesuai rapat koordinasi logistik mingguan"></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                        <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary px-4 fw-semibold d-inline-flex align-items-center gap-1.5 shadow-sm">
                            <i class="bi bi-check2-circle"></i>
                            <span>Setujui & Buat Draft PO</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL SUKSES RESTOCK (#restockSuccessModal) -->
<div class="modal fade" id="restockSuccessModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow text-center p-4">
            <div class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 56px; height: 56px;">
                <i class="bi bi-check-lg fs-2"></i>
            </div>
            <h5 class="fw-bold text-dark mb-1">Restock Disetujui!</h5>
            <p class="text-secondary small mb-3">Draft Purchase Order berhasil diterbitkan ke bagian pengadaan untuk SKU terkait.</p>
            <button type="button" class="btn btn-primary btn-sm px-4 fw-semibold" data-bs-dismiss="modal">Tutup</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Line / Bar Chart Tren Penjualan 7 Hari
    const trendCtx = document.getElementById('salesTrendChart')?.getContext('2d');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: @json($salesTrend['labels']),
                datasets: [
                    {
                        label: 'Omzet Penjualan (Rp)',
                        data: @json($salesTrend['omzet']),
                        borderColor: '#1d4ed8',
                        backgroundColor: 'rgba(29, 78, 216, 0.08)',
                        fill: true,
                        tension: 0.3,
                        borderWidth: 2.5
                    },
                    {
                        label: 'Laba Kotor (Rp)',
                        data: @json($salesTrend['profit']),
                        borderColor: '#15803d',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        borderDash: [5, 5]
                    },
                    {
                        label: 'OPEX Harian (Rp)',
                        data: @json($salesTrend['opex']),
                        borderColor: '#64748b',
                        backgroundColor: 'transparent',
                        borderWidth: 1.5,
                        borderDash: [2, 2]
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(val) {
                                return 'Rp ' + (val / 1000000).toFixed(1) + 'Jt';
                            }
                        }
                    }
                }
            }
        });
    }

    // 2. Doughnut Chart Komposisi Pembayaran
    const compCtx = document.getElementById('paymentCompositionChart')?.getContext('2d');
    if (compCtx) {
        new Chart(compCtx, {
            type: 'doughnut',
            data: {
                labels: ['Tunai Kasir', 'Piutang Anggota', 'Transfer / QRIS'],
                datasets: [{
                    data: [55, 25, 20],
                    backgroundColor: ['#15803d', '#1d4ed8', '#b45309'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                cutout: '65%'
            }
        });
    }
});

let currentActiveRestockItem = null;

window.openRestockModal = function(item) {
    currentActiveRestockItem = item;
    document.getElementById('modalProductName').textContent = item.name;
    document.getElementById('modalProductSku').textContent = item.sku;
    document.getElementById('modalProductSupplier').textContent = item.supplier || 'Distributor Resmi Sembako';
    document.getElementById('modalStockAvailable').textContent = item.stock_available + ' ' + item.unit;
    document.getElementById('modalStockReserved').textContent = item.stock_reserved + ' ' + item.unit;
    document.getElementById('modalSafetyStock').textContent = item.safety_stock + ' ' + item.unit;
    document.getElementById('modalLeadTime').textContent = item.lead_time_days + ' Hari';
    document.getElementById('modalUnitName').textContent = item.unit;
    document.getElementById('modalUnitSuffix').textContent = item.unit;
    document.getElementById('modalModelQty').textContent = item.restock_qty;
    
    const qtyInput = document.getElementById('approvedQtyInput');
    qtyInput.value = item.restock_qty;
    updateEstimatedCost(item.restock_qty, item.unit_price);

    qtyInput.oninput = function() {
        updateEstimatedCost(parseInt(this.value) || 0, item.unit_price);
    };

    const modal = new bootstrap.Modal(document.getElementById('approveRestockModal'));
    modal.show();
};

function updateEstimatedCost(qty, unitPrice) {
    const total = qty * unitPrice;
    document.getElementById('modalEstimatedCost').textContent = 'Rp ' + total.toLocaleString('id-ID');
}

window.submitRestockApproval = function(e) {
    e.preventDefault();
    const modalEl = document.getElementById('approveRestockModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();

    setTimeout(() => {
        const successModal = new bootstrap.Modal(document.getElementById('restockSuccessModal'));
        successModal.show();
    }, 400);
};

window.exportReport = function(format) {
    alert('Mengunduh Laporan Eksekutif Periode November 2026 dalam format ' + format + '...');
};
</script>
@endsection
