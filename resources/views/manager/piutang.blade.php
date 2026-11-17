@extends('layouts.app')

@section('title', 'Monitoring Portofolio & Aging Piutang — KoperasiKu')

@section('header')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
            <h4 class="fw-bold mb-0 text-dark">Monitoring Portofolio & Aging Piutang</h4>
            <span class="badge bg-danger text-white rounded-pill px-2.5 py-1 small">
                <i class="bi bi-clock-history me-1"></i> Standar 5 Kelompok PRD
            </span>
        </div>
        <p class="text-secondary small mb-0">Pengawasan saldo piutang berjalan, deteksi dini tunggakan kredit konsumsi tempo 7 hari kalender bunga 0%.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('manager.kredit') }}" class="btn btn-outline-primary btn-sm px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 shadow-xs">
            <i class="bi bi-sliders"></i>
            <span>Kebijakan Limit Global</span>
        </a>
    </div>
</div>
@endsection

@section('content')

<!-- 1. KPI SUMMARY PORTOFOLIO PIUTANG -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm bg-white p-3 h-100 border-start border-4 border-primary">
            <small class="text-secondary text-uppercase fw-bold" style="font-size: 0.7rem;">Total Saldo Piutang Beredar</small>
            <h4 class="fw-extrabold text-dark mb-1 fs-4">Rp {{ number_format($agingSummary['total_receivables'], 0, ',', '.') }}</h4>
            <small class="text-muted" style="font-size: 0.72rem;">Dipegang {{ $agingSummary['total_debtors'] }} anggota aktif</small>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm bg-white p-3 h-100 border-start border-4 border-success">
            <small class="text-secondary text-uppercase fw-bold" style="font-size: 0.7rem;">Piutang Lancar (Current 0-7 Hari)</small>
            <h4 class="fw-extrabold text-success mb-1 fs-4">Rp {{ number_format($agingSummary['current'], 0, ',', '.') }}</h4>
            <small class="text-muted" style="font-size: 0.72rem;">70.8% dari total portofolio</small>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm bg-white p-3 h-100 border-start border-4 border-warning">
            <small class="text-secondary text-uppercase fw-bold" style="font-size: 0.7rem;">Total Piutang Overdue (&gt;7 Hari)</small>
            <h4 class="fw-extrabold text-warning-emphasis mb-1 fs-4">
                Rp {{ number_format($agingSummary['overdue_1_30'] + $agingSummary['overdue_31_60'] + $agingSummary['overdue_61_90'] + $agingSummary['overdue_gt_90'], 0, ',', '.') }}
            </h4>
            <small class="text-muted" style="font-size: 0.72rem;">29.2% dalam pengawasan</small>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm bg-white p-3 h-100 border-start border-4 border-danger">
            <small class="text-secondary text-uppercase fw-bold" style="font-size: 0.7rem;">Debitur Menunggak (Overdue)</small>
            <h4 class="fw-extrabold text-danger mb-1 fs-4">{{ $agingSummary['overdue_debtors'] }} Anggota</h4>
            <small class="text-muted" style="font-size: 0.72rem;">Fasilitas otomatis terblokir sistem</small>
        </div>
    </div>
</div>

<!-- 2. TABEL MONITORING SELURUH FAKTUR PIUTANG DENGAN FILTER STATUS & AGING -->
<div class="card border-0 shadow-sm bg-white mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h5 class="card-title fw-bold text-dark mb-0 fs-6">
                <i class="bi bi-table text-primary me-1.5"></i> Detail Faktur Piutang Belanja Anggota
            </h5>
            <small class="text-muted" style="font-size: 0.72rem;">Filter berdasarkan status pembayaran dan 5 kelompok keterlambatan standar PRD</small>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="input-group input-group-sm" style="width: 220px;">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control border-start-0 ps-0" id="piutangSearchInput" placeholder="Cari No Faktur, Anggota..." oninput="applyPiutangFilters()">
            </div>

            <!-- Filter Status Pembayaran -->
            <select class="form-select form-select-sm" id="piutangStatusFilter" style="width: 140px;" onchange="applyPiutangFilters()">
                <option value="ALL">Semua Status</option>
                <option value="OPEN">OPEN (Belum Lunas)</option>
                <option value="PARTIALLY_PAID">PARTIALLY PAID</option>
                <option value="OVERDUE">OVERDUE (Tunggakan)</option>
                <option value="PAID">PAID (Lunas)</option>
            </select>

            <!-- Filter Kelompok Aging (5 Brackets) -->
            <select class="form-select form-select-sm" id="piutangAgingFilter" style="width: 170px;" onchange="applyPiutangFilters()">
                <option value="ALL">Semua Usia Piutang</option>
                <option value="current">Current (0–7 Hari)</option>
                <option value="1_30">1–30 Hari Lewat Tempo</option>
                <option value="31_60">31–60 Hari Lewat Tempo</option>
                <option value="61_90">61–90 Hari Lewat Tempo</option>
                <option value="gt_90">&gt;90 Hari (Macet)</option>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="piutangTable" style="font-size: 0.82rem;">
            <thead class="table-light">
                <tr>
                    <th>No. Faktur</th>
                    <th>Nama Anggota / ID</th>
                    <th>Tgl Pickup</th>
                    <th>Jatuh Tempo (+7H)</th>
                    <th class="text-center">Keterlambatan</th>
                    <th class="text-center">Kelompok Aging</th>
                    <th class="text-end">Total Awal</th>
                    <th class="text-end">Sisa Piutang</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($receivablesList as $inv)
                    <tr class="piutang-row" data-search="{{ strtolower($inv['invoice_no'] . ' ' . $inv['member_id'] . ' ' . $inv['member_name'] . ' ' . $inv['department']) }}" data-status="{{ $inv['status'] }}" data-aging="{{ $inv['aging_bucket'] }}">
                        <td class="fw-bold font-monospace text-primary">{{ $inv['invoice_no'] }}</td>
                        <td>
                            <div class="fw-semibold text-dark">{{ $inv['member_name'] }}</div>
                            <small class="text-muted">{{ $inv['member_id'] }} &bull; {{ $inv['department'] }}</small>
                        </td>
                        <td>{{ $inv['pickup_date'] }}</td>
                        <td class="fw-semibold">{{ $inv['due_date'] }}</td>
                        <td class="text-center">
                            @if($inv['days_past_due'] > 0)
                                <span class="badge bg-danger text-white">
                                    +{{ $inv['days_past_due'] }} Hari
                                </span>
                            @else
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    Tepat Waktu
                                </span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($inv['aging_bucket'] === 'current')
                                <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.7rem;">Current (0-7H)</span>
                            @elseif($inv['aging_bucket'] === '1_30')
                                <span class="badge bg-warning text-dark" style="font-size: 0.7rem;">1–30 Hari</span>
                            @elseif($inv['aging_bucket'] === '31_60')
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 0.7rem;">31–60 Hari</span>
                            @elseif($inv['aging_bucket'] === '61_90')
                                <span class="badge bg-danger text-white" style="font-size: 0.7rem;">61–90 Hari</span>
                            @elseif($inv['aging_bucket'] === 'gt_90')
                                <span class="badge bg-dark text-white" style="font-size: 0.7rem;">&gt;90 Hari (Macet)</span>
                            @else
                                <span class="badge bg-light text-secondary border" style="font-size: 0.7rem;">Lunas</span>
                            @endif
                        </td>
                        <td class="text-end text-muted">Rp {{ number_format($inv['total_amount'], 0, ',', '.') }}</td>
                        <td class="text-end fw-bold {{ $inv['balance_due'] > 0 ? 'text-danger' : 'text-success' }}">
                            Rp {{ number_format($inv['balance_due'], 0, ',', '.') }}
                        </td>
                        <td class="text-center">
                            @if($inv['status'] === 'OVERDUE')
                                <span class="badge bg-danger text-white">OVERDUE</span>
                            @elseif($inv['status'] === 'PARTIALLY_PAID')
                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">PARTIAL</span>
                            @elseif($inv['status'] === 'OPEN')
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">OPEN</span>
                            @else
                                <span class="badge bg-success text-white">PAID</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-outline-primary btn-sm px-2.5 py-1 d-inline-flex align-items-center gap-1 shadow-xs" onclick="openInvoiceDetailModal({{ json_encode($inv) }})">
                                <i class="bi bi-eye"></i>
                                <span>Rincian</span>
                            </button>
                        </td>
                    </tr>
                @endforeach
                <tr id="piutangEmptyRow" style="display: none;">
                    <td colspan="10" class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                        Tidak ada faktur piutang yang cocok dengan kriteria filter saat ini.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="card-footer bg-white border-top py-2.5 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <small class="text-muted" style="font-size: 0.74rem;">
            Total faktur tercatat: <strong>{{ count($receivablesList) }}</strong> faktur piutang.
        </small>
        <span class="badge bg-light text-secondary border small">
            <i class="bi bi-shield-check text-success me-1"></i> Data Real-Time Kasir & Pickup
        </span>
    </div>
</div>

<!-- MODAL RINCIAN FAKTUR PIUTANG (#detailFakturModal) -->
<div class="modal fade" id="detailFakturModal" tabindex="-1" aria-labelledby="detailFakturModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-receipt-cutoff fs-5"></i>
                    <h5 class="modal-title fw-bold fs-6" id="detailFakturModalLabel">Rincian Faktur Piutang Anggota</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center pb-3 border-bottom mb-3">
                    <div>
                        <small class="text-muted d-block" style="font-size: 0.7rem;">Nomor Faktur:</small>
                        <span class="fs-5 fw-bold font-monospace text-primary" id="modalInvNo">-</span>
                    </div>
                    <span class="badge bg-primary text-white" id="modalInvStatus">OPEN</span>
                </div>

                <div class="row g-2 mb-3 small">
                    <div class="col-6">
                        <span class="text-muted d-block">Nama Anggota:</span>
                        <strong class="text-dark" id="modalMemberName">-</strong>
                    </div>
                    <div class="col-6">
                        <span class="text-muted d-block">ID Karyawan / Unit:</span>
                        <strong class="text-dark" id="modalMemberId">-</strong>
                    </div>
                    <div class="col-6">
                        <span class="text-muted d-block">Tanggal Pengambilan:</span>
                        <strong class="text-dark" id="modalPickupDate">-</strong>
                    </div>
                    <div class="col-6">
                        <span class="text-muted d-block">Batas Jatuh Tempo (+7H):</span>
                        <strong class="text-dark" id="modalDueDate">-</strong>
                    </div>
                </div>

                <div class="p-3 bg-light rounded-3 border mb-3">
                    <div class="d-flex justify-content-between text-secondary small py-1 border-bottom border-light">
                        <span>Total Nominal Belanja:</span>
                        <strong class="text-dark" id="modalTotalAmount">Rp 0</strong>
                    </div>
                    <div class="d-flex justify-content-between text-secondary small py-1 border-bottom border-light">
                        <span>Kelompok Keterlambatan:</span>
                        <strong class="text-dark" id="modalAgingLabel">-</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-2">
                        <span class="fw-bold text-dark">Sisa Tagihan Belum Lunas:</span>
                        <span class="fs-5 fw-extrabold text-danger" id="modalBalanceDue">Rp 0</span>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary btn-sm px-3 fw-semibold" onclick="alert('Mencetak lembar penagihan faktur ' + document.getElementById('modalInvNo').textContent)">
                        <i class="bi bi-printer me-1"></i> Cetak Lembar Tagihan
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.applyPiutangFilters = function() {
    const q = (document.getElementById('piutangSearchInput')?.value || '').toLowerCase();
    const statusVal = document.getElementById('piutangStatusFilter')?.value || 'ALL';
    const agingVal = document.getElementById('piutangAgingFilter')?.value || 'ALL';

    const rows = document.querySelectorAll('.piutang-row');
    let visibleCount = 0;

    rows.forEach(r => {
        const text = r.getAttribute('data-search') || '';
        const status = r.getAttribute('data-status') || '';
        const aging = r.getAttribute('data-aging') || '';

        const matchSearch = text.includes(q);
        const matchStatus = (statusVal === 'ALL' || status === statusVal);
        const matchAging = (agingVal === 'ALL' || aging === agingVal);

        if (matchSearch && matchStatus && matchAging) {
            r.style.display = '';
            visibleCount++;
        } else {
            r.style.display = 'none';
        }
    });

    const emptyRow = document.getElementById('piutangEmptyRow');
    if (emptyRow) {
        emptyRow.style.display = visibleCount === 0 ? '' : 'none';
    }
};

window.openInvoiceDetailModal = function(inv) {
    document.getElementById('modalInvNo').textContent = inv.invoice_no;
    document.getElementById('modalInvStatus').textContent = inv.status;
    document.getElementById('modalInvStatus').className = 'badge ' + (inv.status === 'OVERDUE' ? 'bg-danger text-white' : (inv.status === 'PAID' ? 'bg-success text-white' : 'bg-primary text-white'));
    document.getElementById('modalMemberName').textContent = inv.member_name;
    document.getElementById('modalMemberId').textContent = inv.member_id + ' (' + inv.department + ')';
    document.getElementById('modalPickupDate').textContent = inv.pickup_date;
    document.getElementById('modalDueDate').textContent = inv.due_date;
    document.getElementById('modalAgingLabel').textContent = inv.aging_label;
    document.getElementById('modalTotalAmount').textContent = 'Rp ' + inv.total_amount.toLocaleString('id-ID');
    document.getElementById('modalBalanceDue').textContent = 'Rp ' + inv.balance_due.toLocaleString('id-ID');

    const modal = new bootstrap.Modal(document.getElementById('detailFakturModal'));
    modal.show();
};
</script>
@endsection
