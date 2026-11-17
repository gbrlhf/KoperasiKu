@extends('layouts.app')

@section('title', 'Kebijakan Limit Piutang Global — KoperasiKu')

@section('header')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
            <h4 class="fw-bold mb-0 text-dark">Kebijakan Limit Piutang Global & Fasilitas Anggota</h4>
            <span class="badge bg-primary text-white rounded-pill px-2.5 py-1 small">
                <i class="bi bi-shield-check me-1"></i> FR-08A Sesuai PRD
            </span>
        </div>
        <p class="text-secondary small mb-0">Pengaturan limit seragam koperasi, penguncian tempo 7 hari kalender, dan kontrol aktivasi fasilitas kredit anggota.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-white text-secondary border px-3 py-2 small shadow-xs">
            <i class="bi bi-people me-1 text-primary"></i> <strong>{{ $activeMembers }}</strong> dari <strong>{{ $totalMembers }}</strong> Anggota Aktif
        </span>
    </div>
</div>
@endsection

@section('content')

<!-- ATURAN BISNIS BANNER: LIMIT SERAGAM & 7 HARI KALENDER -->
<div class="alert alert-primary-subtle border border-primary-subtle rounded-3 p-3 mb-4 shadow-xs">
    <div class="d-flex align-items-start gap-3">
        <div class="p-2 bg-primary text-white rounded-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
            <i class="bi bi-info-circle-fill fs-5"></i>
        </div>
        <div class="flex-grow-1">
            <h6 class="fw-bold text-primary mb-1">Prinsip Limit Seragam Koperasi Konsumen Pegawai (FR-08A)</h6>
            <p class="text-secondary small mb-0 lh-base">
                Sesuai AD/ART KoperasiKu dan aturan FR-08A, limit belanja tempo 7 hari berlaku <strong>sama rata (seragam)</strong> bagi seluruh anggota yang berhak. Tidak ada limitasi individu per orang. Perubahan limit global hanya berlaku untuk pesanan baru dan <strong>tidak mengubah saldo atau tanggal jatuh tempo faktur yang sedang berjalan</strong>.
            </p>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- FORM KEBIJAKAN LIMIT GLOBAL -->
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm bg-white h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="card-title fw-bold text-dark mb-0 fs-6">
                    <i class="bi bi-sliders text-primary me-1.5"></i> Form Parameter Kebijakan Global
                </h5>
                <small class="text-muted" style="font-size: 0.72rem;">Berlaku otomatis ke seluruh anggota dengan status fasilitas ACTIVE</small>
            </div>
            <div class="card-body p-3 p-md-4">
                <form id="globalPolicyForm" onsubmit="openConfirmPolicyModal(event)">
                    
                    <!-- Input 1: Pagu Limit Seragam -->
                    <div class="mb-3">
                        <label for="inputGlobalLimit" class="form-label fw-bold text-dark small">Pagu Limit Piutang Global Seragam</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted fw-semibold">Rp</span>
                            <input type="number" class="form-control fw-extrabold text-primary fs-5" id="inputGlobalLimit" value="{{ $globalLimit }}" step="50000" min="100000" required>
                        </div>
                        <div class="form-text small text-muted">Contoh nilai: Rp 1.000.000 (Diterapkan sama rata).</div>
                    </div>

                    <!-- Input 2: Parameter Jatuh Tempo (Locked MVP) -->
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small d-flex justify-content-between align-items-center">
                            <span>Masa Jatuh Tempo Standar</span>
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle font-monospace" style="font-size: 0.65rem;">
                                <i class="bi bi-lock-fill me-1"></i> TERKUNCI (MVP)
                            </span>
                        </label>
                        <div class="input-group">
                            <input type="text" class="form-control bg-light text-muted fw-semibold" value="{{ $dueDateDays }} Hari Kalender" readonly>
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-calendar-check"></i></span>
                        </div>
                        <div class="form-text small text-muted">Bunga 0%, jatuh tempo ketat 7 hari terhitung sejak fisik barang diserahkan di kasir gerai.</div>
                    </div>

                    <!-- Input 3: Alasan Perubahan Kebijakan -->
                    <div class="mb-4">
                        <label for="policyChangeReason" class="form-label fw-semibold text-secondary small">Alasan Perubahan Kebijakan (Wajib Audit Log)</label>
                        <textarea class="form-control form-control-sm" id="policyChangeReason" rows="3" placeholder="Tuliskan keputusan rapat pengurus / dasar penyesuaian limit..." required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2.5 fw-semibold shadow-sm d-inline-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-check2-circle fs-5"></i>
                        <span>Simpan & Terapkan Kebijakan</span>
                    </button>
                </form>
            </div>
            <div class="card-footer bg-light border-top py-2.5 px-3">
                <small class="text-muted d-block" style="font-size: 0.72rem;">
                    <i class="bi bi-clock-history me-1"></i> Terakhir diperbarui: <strong>10 Nov 2026</strong> oleh <strong>Ir. Sudirman (Manajer)</strong>
                </small>
            </div>
        </div>
    </div>

    <!-- AUDIT LOG PERUBAHAN KEBIJAKAN -->
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm bg-white h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title fw-bold text-dark mb-0 fs-6">
                        <i class="bi bi-journal-text text-primary me-1.5"></i> Audit Log Riwayat Perubahan Limit
                    </h5>
                    <small class="text-muted" style="font-size: 0.72rem;">Catatan historis seluruh perubahan limit kredit global yang dilakukan eksekutif</small>
                </div>
                <span class="badge bg-light text-secondary border small">{{ count($policyAuditLogs) }} Rekaman</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.8rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Waktu & Tanggal</th>
                                <th>Aktor / Manajer</th>
                                <th>Penyesuaian Limit</th>
                                <th>Catatan / Alasan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($policyAuditLogs as $log)
                                <tr>
                                    <td class="font-monospace text-muted small text-nowrap">{{ $log['timestamp'] }}</td>
                                    <td class="fw-semibold text-dark text-nowrap">{{ $log['actor'] }}</td>
                                    <td class="text-nowrap">
                                        <del class="text-muted small">Rp {{ number_format($log['previous_limit'], 0, ',', '.') }}</del>
                                        <i class="bi bi-arrow-right mx-1 text-primary"></i>
                                        <span class="fw-bold text-success">Rp {{ number_format($log['new_limit'], 0, ',', '.') }}</span>
                                    </td>
                                    <td class="text-secondary small">{{ $log['note'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white border-top py-2.5 px-3">
                <small class="text-muted" style="font-size: 0.72rem;">
                    <i class="bi bi-database-lock me-1"></i> Seluruh penyesuaian tersimpan permanen di database tabel log untuk keperluan rapat anggota tahunan (RAT).
                </small>
            </div>
        </div>
    </div>
</div>

<!-- 2. TABEL STATUS FASILITAS ANGGOTA (ACTIVE / BLOCKED) -->
<div class="card border-0 shadow-sm bg-white mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h5 class="card-title fw-bold text-dark mb-0 fs-6">
                <i class="bi bi-person-check text-primary me-1.5"></i> Kontrol Status Fasilitas Kredit Anggota
            </h5>
            <small class="text-muted" style="font-size: 0.72rem;">Manajer dapat memblokir atau membuka kembali fasilitas tempo 7 hari anggota tanpa mengubah limit global</small>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="input-group input-group-sm" style="width: 250px;">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control border-start-0 ps-0" id="memberFilterInput" placeholder="Cari ID, Nama, Unit..." oninput="filterMemberTable(this.value)">
            </div>
            <select class="form-select form-select-sm" id="statusFilterSelect" style="width: 140px;" onchange="filterMemberStatus(this.value)">
                <option value="ALL">Semua Status</option>
                <option value="ACTIVE">Fasilitas Aktif</option>
                <option value="BLOCKED">Fasilitas Diblokir</option>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="memberTable" style="font-size: 0.82rem;">
            <thead class="table-light">
                <tr>
                    <th>ID Anggota</th>
                    <th>Nama Lengkap</th>
                    <th>Unit / Departemen</th>
                    <th class="text-center">Status Fasilitas</th>
                    <th class="text-end">Outstanding Piutang</th>
                    <th class="text-center">Umur Faktur Tertua</th>
                    <th class="text-center">Aksi Hak Akses</th>
                </tr>
            </thead>
            <tbody>
                @foreach($memberCreditList as $m)
                    <tr class="member-row" data-search="{{ strtolower($m['id'] . ' ' . $m['name'] . ' ' . $m['department']) }}" data-status="{{ $m['status'] }}">
                        <td class="fw-bold font-monospace text-primary">{{ $m['id'] }}</td>
                        <td class="fw-semibold text-dark">{{ $m['name'] }}</td>
                        <td class="text-secondary small">{{ $m['department'] }}</td>
                        <td class="text-center">
                            @if($m['status'] === 'ACTIVE')
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                    <i class="bi bi-check-circle-fill me-1"></i> ACTIVE
                                </span>
                            @else
                                <span class="badge bg-danger text-white px-2 py-1">
                                    <i class="bi bi-lock-fill me-1"></i> BLOCKED
                                </span>
                            @endif
                        </td>
                        <td class="text-end fw-bold {{ $m['current_debt'] > 0 ? 'text-danger' : 'text-muted' }}">
                            Rp {{ number_format($m['current_debt'], 0, ',', '.') }}
                        </td>
                        <td class="text-center">
                            @if($m['is_overdue'])
                                <span class="badge bg-danger text-white" style="font-size: 0.7rem;">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ $m['oldest_invoice_age'] }}
                                </span>
                            @else
                                <span class="text-muted small">{{ $m['oldest_invoice_age'] }}</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($m['status'] === 'ACTIVE')
                                <button type="button" class="btn btn-outline-danger btn-sm px-2.5 py-1 d-inline-flex align-items-center gap-1 shadow-xs" onclick="openToggleMemberModal('{{ $m['id'] }}', '{{ $m['name'] }}', 'BLOCKED')">
                                    <i class="bi bi-slash-circle"></i>
                                    <span>Blokir Fasilitas</span>
                                </button>
                            @else
                                <button type="button" class="btn btn-outline-success btn-sm px-2.5 py-1 d-inline-flex align-items-center gap-1 shadow-xs" onclick="openToggleMemberModal('{{ $m['id'] }}', '{{ $m['name'] }}', 'ACTIVE')">
                                    <i class="bi bi-unlock-fill"></i>
                                    <span>Aktifkan Kembali</span>
                                </button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="card-footer bg-white border-top py-2.5 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <small class="text-muted" style="font-size: 0.74rem;">
            Menampilkan <strong>{{ count($memberCreditList) }}</strong> data anggota contoh (Filter pencarian aktif real-time).
        </small>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-success text-white small">Aktif: {{ $activeMembers }}</span>
            <span class="badge bg-danger text-white small">Diblokir: {{ $blockedMembers }}</span>
        </div>
    </div>
</div>

<!-- MODAL KONFIRMASI SIMPAN KEBIJAKAN LIMIT GLOBAL (#confirmPolicyModal) -->
<div class="modal fade" id="confirmPolicyModal" tabindex="-1" aria-labelledby="confirmPolicyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-shield-exclamation fs-5"></i>
                    <h5 class="modal-title fw-bold fs-6" id="confirmPolicyModalLabel">Konfirmasi Kebijakan Limit Seragam</h5>
                </div>
            </div>
            <div class="modal-body p-4">
                <p class="text-dark small mb-3">
                    Anda akan memperbarui pagu limit kredit konsumsi seragam untuk seluruh anggota eligible koperasi:
                </p>
                <div class="p-3 bg-light rounded-3 border mb-3 text-center">
                    <small class="text-muted text-uppercase fw-bold d-block mb-1" style="font-size: 0.7rem;">Limit Baru Seragam:</small>
                    <div class="fs-3 fw-extrabold text-primary" id="confirmNewLimitDisplay">Rp 1.000.000</div>
                    <div class="badge bg-primary-subtle text-primary mt-1">Berlaku untuk {{ $activeMembers }} anggota aktif</div>
                </div>

                <div class="alert alert-warning border-warning-subtle small mb-3">
                    <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
                    <strong>Penting:</strong> Perubahan ini akan segera berlaku untuk pesanan baru. Tagihan dan batas tempo faktur lama yang sedang berjalan <strong>tidak akan terpengaruh</strong>. Perubahan ini akan dicatat di Audit Log permanen.
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary px-4 fw-semibold shadow-sm" onclick="executeSavePolicy()">
                        <i class="bi bi-check-lg me-1"></i> Konfirmasi & Terapkan
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL KONFIRMASI TOGGLE STATUS FASILITAS ANGGOTA (#toggleMemberModal) -->
<div class="modal fade" id="toggleMemberModal" tabindex="-1" aria-labelledby="toggleMemberModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header" id="toggleModalHeader">
                <div class="d-flex align-items-center gap-2 text-white">
                    <i class="bi bi-person-fill-gear fs-5"></i>
                    <h5 class="modal-title fw-bold fs-6" id="toggleMemberModalLabel">Konfirmasi Perubahan Status Fasilitas</h5>
                </div>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="mb-3" id="toggleModalIcon">
                    <!-- Dynamic icon -->
                </div>
                <h5 class="fw-bold text-dark mb-1" id="toggleMemberName">-</h5>
                <p class="text-muted small mb-3">Nomor Anggota: <span class="font-monospace fw-bold" id="toggleMemberId">-</span></p>

                <p class="text-secondary small mb-4" id="toggleModalMessage">
                    Apakah Anda yakin ingin memperbarui status fasilitas kredit sembako tempo 7 hari anggota ini?
                </p>

                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary px-4 fw-semibold shadow-sm" id="toggleConfirmBtn" onclick="executeToggleMember()">
                        Simpan Perubahan
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let pendingNewLimit = 0;
let pendingToggle = { id: '', name: '', newStatus: '' };

window.openConfirmPolicyModal = function(e) {
    e.preventDefault();
    pendingNewLimit = parseInt(document.getElementById('inputGlobalLimit').value) || 0;
    document.getElementById('confirmNewLimitDisplay').textContent = 'Rp ' + pendingNewLimit.toLocaleString('id-ID');
    const modal = new bootstrap.Modal(document.getElementById('confirmPolicyModal'));
    modal.show();
};

window.executeSavePolicy = function() {
    const modalEl = document.getElementById('confirmPolicyModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();

    setTimeout(() => {
        alert('Kebijakan limit seragam Rp ' + pendingNewLimit.toLocaleString('id-ID') + ' berhasil diterapkan ke seluruh anggota aktif dan dicatat di Audit Log.');
    }, 400);
};

window.openToggleMemberModal = function(id, name, newStatus) {
    pendingToggle = { id, name, newStatus };
    document.getElementById('toggleMemberId').textContent = id;
    document.getElementById('toggleMemberName').textContent = name;

    const header = document.getElementById('toggleModalHeader');
    const btn = document.getElementById('toggleConfirmBtn');
    const iconContainer = document.getElementById('toggleModalIcon');
    const msg = document.getElementById('toggleModalMessage');

    if (newStatus === 'BLOCKED') {
        header.className = 'modal-header bg-danger text-white';
        btn.className = 'btn btn-danger px-4 fw-semibold shadow-sm';
        btn.textContent = 'Blokir Fasilitas';
        iconContainer.innerHTML = '<div class="rounded-circle bg-danger-subtle text-danger d-inline-flex align-items-center justify-content-center" style="width: 56px; height: 56px;"><i class="bi bi-slash-circle fs-2"></i></div>';
        msg.textContent = 'Anggota tidak akan dapat memilih metode bayar Piutang 7 Hari saat checkout online maupun di kasir POS hingga fasilitas dibuka kembali.';
    } else {
        header.className = 'modal-header bg-success text-white';
        btn.className = 'btn btn-success px-4 fw-semibold shadow-sm';
        btn.textContent = 'Aktifkan Kembali';
        iconContainer.innerHTML = '<div class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center" style="width: 56px; height: 56px;"><i class="bi bi-unlock-fill fs-2"></i></div>';
        msg.textContent = 'Hak akses fasilitas kredit sembako tempo 7 hari anggota ini akan dipulihkan dengan pagu limit global seragam.';
    }

    const modal = new bootstrap.Modal(document.getElementById('toggleMemberModal'));
    modal.show();
};

window.executeToggleMember = function() {
    const modalEl = document.getElementById('toggleMemberModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();

    setTimeout(() => {
        alert('Status fasilitas anggota ' + pendingToggle.id + ' (' + pendingToggle.name + ') berhasil diubah menjadi: ' + pendingToggle.newStatus);
        location.reload();
    }, 400);
};

window.filterMemberTable = function(term) {
    const q = term.toLowerCase();
    const rows = document.querySelectorAll('.member-row');
    rows.forEach(r => {
        const text = r.getAttribute('data-search') || '';
        r.style.display = text.includes(q) ? '' : 'none';
    });
};

window.filterMemberStatus = function(val) {
    const rows = document.querySelectorAll('.member-row');
    rows.forEach(r => {
        const st = r.getAttribute('data-status') || '';
        if (val === 'ALL' || st === val) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });
};
</script>
@endsection
