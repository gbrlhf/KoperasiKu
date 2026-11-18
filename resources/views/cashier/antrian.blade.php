@extends('layouts.app')

@section('title', 'Antrian Pickup Pesanan')

@section('header')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0 text-dark">Antrian Pickup & Verifikasi Kasir</h4>
    </div>
    <button type="button" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1.5" onclick="loadAntrianOrders()">
        <i class="bi bi-arrow-clockwise"></i>
        <span>Muat Ulang Antrian</span>
    </button>
</div>
@endsection

@section('content')
<style>
    .antrian-header-controls .form-select,
    .antrian-header-controls .input-group,
    .antrian-header-controls .input-group-text,
    .antrian-header-controls .form-control,
    .antrian-header-controls .btn {
        height: 38px;
        min-height: 38px !important;
        box-sizing: border-box;
    }
    .antrian-header-controls .input-group-text {
        border-radius: 8px 0 0 8px !important;
    }
    .antrian-header-controls .form-control {
        border-radius: 0 8px 8px 0 !important;
    }
</style>
<div class="card border-0 shadow-sm bg-white p-3 p-md-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3 pb-3 border-bottom">
        <div class="min-w-0">
            <h5 class="card-title fw-bold text-dark mb-0 fs-6">Daftar Antrian Pengambilan Barang (Pickup)</h5>
            <small class="text-muted" style="font-size: 0.75rem;">Sumber langsung dari <code>GET /api/v1/cashier/orders</code>. Cocokkan kode pickup sebelum menyerahkan barang.</small>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2 antrian-header-controls">
            <select id="antrianStatusFilter" class="form-select form-select-sm" style="width: auto; min-width: 175px;" onchange="loadAntrianOrders()">
                <option value="">Semua Antrian Aktif</option>
                <option value="PENDING_PAYMENT">Menunggu Pembayaran</option>
                <option value="PENDING_PICKUP">Siap Diambil (Kredit)</option>
                <option value="READY_FOR_PICKUP">Siap Diambil (Lunas)</option>
            </select>
            <div class="input-group input-group-sm" style="width: 210px; max-width: 100%;">
                <span class="input-group-text bg-light"><i class="bi bi-qr-code"></i></span>
                <input type="text" id="antrianFilterInput" class="form-control font-monospace text-uppercase" placeholder="Cari PKP- / ORD-..." oninput="filterAntrianRows(this.value)">
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1.5 text-nowrap flex-shrink-0 px-3" onclick="loadAntrianOrders()">
                <i class="bi bi-arrow-clockwise"></i>
                <span>Muat Ulang</span>
            </button>
        </div>
    </div>

    <div id="antrianErrorAlert" class="alert alert-danger mb-3 py-2 px-3 small d-none" role="alert"></div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
            <thead class="table-light">
                <tr>
                    <th>Kode Pickup</th>
                    <th>No. Order & Anggota</th>
                    <th>Rincian Item</th>
                    <th>Total Tagihan</th>
                    <th>Status Pembayaran</th>
                    <th class="text-end">Aksi Serah Terima</th>
                </tr>
            </thead>
            <tbody id="antrianTableBody">
                <tr id="antrianLoadingRow">
                    <td colspan="6" class="text-center py-4 text-muted small">
                        <span class="spinner-border spinner-border-sm me-1 text-primary" role="status"></span>
                        Memuat antrian pesanan...
                    </td>
                </tr>
                <tr id="antrianEmptyRow" class="d-none">
                    <td colspan="6" class="text-center py-4 text-muted small">
                        Tidak ada pesanan aktif pada antrian pickup.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Verifikasi Transfer -->
<div class="modal fade" id="antrianVerifyModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white py-3 px-3">
                <h6 class="modal-title fw-bold mb-0">Verifikasi Pembayaran Transfer</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-3">
                <div class="p-2.5 rounded bg-light border mb-3 small">
                    <div>Kode Pickup: <strong id="avPickupCode" class="font-monospace">-</strong></div>
                    <div>Nominal Tagihan: <strong id="avTotalAmount" class="font-monospace">-</strong></div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Nomor Referensi Mutasi Bank / QRIS:</label>
                    <input type="text" class="form-control form-control-sm font-monospace text-uppercase" id="avTransferRef" placeholder="Contoh: TRX-BCA-51922">
                </div>
                <div class="form-check p-2 rounded bg-light border mb-2">
                    <input class="form-check-input ms-0 me-2" type="checkbox" id="avConfirmCheck">
                    <label class="form-check-label small fw-semibold" for="avConfirmCheck">
                        Dana telah dipastikan masuk ke rekening koperasi.
                    </label>
                </div>
                <div id="avErrorAlert" class="alert alert-danger py-2 px-2.5 small d-none mb-0" role="alert"></div>
            </div>
            <div class="modal-footer bg-light py-2 px-3 justify-content-between">
                <button type="button" class="btn btn-outline-danger btn-sm" id="btnAvReject" onclick="submitAntrianVerify('REJECT')">
                    Tolak Bukti
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-success btn-sm fw-semibold" id="btnAvApprove" onclick="submitAntrianVerify('VERIFY')">
                        Setujui & Verifikasi
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Serah Terima Pickup -->
<div class="modal fade" id="antrianPickupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-3 px-3">
                <h6 class="modal-title fw-bold mb-0">Konfirmasi Serah Terima Pickup</h6>
            </div>
            <div class="modal-body p-3">
                <div class="p-2.5 rounded bg-light border mb-3 small">
                    <div>Kode Pickup: <strong id="apPickupCode" class="font-monospace">-</strong></div>
                    <div>No. Order: <strong id="apOrderNo" class="font-monospace">-</strong></div>
                    <div>Total Nilai: <strong id="apTotalAmount" class="font-monospace">-</strong></div>
                    <div>Metode: <strong id="apMethod">-</strong></div>
                </div>
                <div class="form-check p-2 rounded bg-warning bg-opacity-10 border border-warning border-opacity-25 mb-2">
                    <input class="form-check-input ms-0 me-2" type="checkbox" id="apHandoverCheck" checked>
                    <label class="form-check-label small fw-semibold" for="apHandoverCheck">
                        Barang fisik sudah diperiksa dan diserahkan kepada anggota.
                    </label>
                </div>
                <div id="apErrorAlert" class="alert alert-danger py-2 px-2.5 small d-none mb-0" role="alert"></div>
            </div>
            <div class="modal-footer bg-light py-2 px-3 justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success btn-sm fw-semibold" id="btnApSubmit" onclick="submitAntrianPickup()">
                    Konfirmasi Serah Terima
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Pembatalan Pesanan -->
<div class="modal fade" id="antrianCancelModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger text-white py-3 px-3">
                <h6 class="modal-title fw-bold mb-0">Batalkan Pesanan Pickup</h6>
            </div>
            <div class="modal-body p-3 small">
                <p class="mb-2">Pembatalan akan melepas reservasi stok dan penahanan limit kredit anggota untuk pesanan <strong id="acPickupCode" class="font-monospace">-</strong>.</p>
                <div id="acErrorAlert" class="alert alert-danger py-2 px-2.5 small d-none mb-0" role="alert"></div>
            </div>
            <div class="modal-footer bg-light py-2 px-3 justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Kembali</button>
                <button type="button" class="btn btn-danger btn-sm fw-semibold" id="btnAcSubmit" onclick="submitAntrianCancel()">
                    Ya, Batalkan Pesanan
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let antrianOrders = [];
let activeVerifyOrder = null;
let activePickupOrder = null;
let activeCancelOrder = null;

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

async function antrianApi(url, method = 'GET', payload = null) {
    const options = {
        method,
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
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

function setAntrianAlert(id, msg) {
    const el = document.getElementById(id);
    if (!el) return;
    if (msg) {
        el.textContent = msg;
        el.classList.remove('d-none');
    } else {
        el.textContent = '';
        el.classList.add('d-none');
    }
}

async function loadAntrianOrders() {
    const loadingRow = document.getElementById('antrianLoadingRow');
    const emptyRow = document.getElementById('antrianEmptyRow');
    const statusFilter = document.getElementById('antrianStatusFilter')?.value || '';

    setAntrianAlert('antrianErrorAlert', '');
    loadingRow?.classList.remove('d-none');

    try {
        const query = statusFilter ? `?status=${encodeURIComponent(statusFilter)}` : '';
        const res = await antrianApi(`/api/v1/cashier/orders${query}`);
        antrianOrders = Array.isArray(res.data) ? res.data : [];
        renderAntrianRows();
    } catch (err) {
        document.querySelectorAll('#antrianTableBody tr.antrian-row').forEach(tr => tr.remove());
        emptyRow?.classList.remove('d-none');
        setAntrianAlert('antrianErrorAlert', err.message || 'Gagal memuat daftar antrian pesanan.');
    } finally {
        loadingRow?.classList.add('d-none');
    }
}

function formatAntrianMemberLabel(order) {
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

function renderAntrianRows() {
    const tbody = document.getElementById('antrianTableBody');
    const emptyRow = document.getElementById('antrianEmptyRow');
    tbody.querySelectorAll('tr.antrian-row').forEach(tr => tr.remove());

    if (antrianOrders.length === 0) {
        emptyRow?.classList.remove('d-none');
        return;
    }
    emptyRow?.classList.add('d-none');

    const html = antrianOrders.map(order => {
        const code = order.pickup_code || order.order_no || order.id;
        const total = Number(order.total_amount || 0);
        const method = order.payment_choice || 'CASH';
        const status = order.status || 'PENDING_PAYMENT';
        const itemCount = Array.isArray(order.items) ? order.items.reduce((s, i) => s + Number(i.quantity || 0), 0) : 0;
        const needVerifyTransfer = (method === 'BANK_TRANSFER' && status === 'PENDING_PAYMENT');
        const memberDisplay = formatAntrianMemberLabel(order);

        return `
            <tr class="antrian-row" data-search="${escapeHtml((code + ' ' + (order.order_no || '') + ' ' + memberDisplay + ' ' + (order.member_id || '')).toLowerCase())}">
                <td><span class="badge bg-primary font-monospace">${escapeHtml(code)}</span></td>
                <td>
                    <div class="fw-semibold font-monospace">${escapeHtml(order.order_no || '-')}</div>
                    <small class="text-muted">${escapeHtml(memberDisplay)}</small>
                </td>
                <td>${itemCount} unit (${(order.items || []).length} baris SKU)</td>
                <td class="fw-bold font-monospace">Rp ${total.toLocaleString('id-ID')}</td>
                <td>
                    <span class="badge bg-light text-dark border">${escapeHtml(method)}</span>
                    <small class="d-block text-muted mt-0.5">${escapeHtml(status)}</small>
                    ${needVerifyTransfer ? `
                        <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 mt-1" style="font-size: 0.72rem;" onclick="openAntrianVerify('${escapeHtml(order.id)}')">
                            Verifikasi Transfer
                        </button>
                    ` : ''}
                </td>
                <td class="text-end">
                    <div class="d-flex align-items-center justify-content-end gap-2">
                        <button type="button" class="btn btn-sm ${needVerifyTransfer ? 'btn-outline-secondary opacity-50' : 'btn-outline-success'} text-nowrap" ${needVerifyTransfer ? 'disabled' : ''} onclick="openAntrianPickup('${escapeHtml(order.id)}')">
                            Serah Terima
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger text-nowrap" onclick="openAntrianCancel('${escapeHtml(order.id)}')">
                            Batal
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    tbody.insertAdjacentHTML('beforeend', html);
    const activeFilter = document.getElementById('antrianFilterInput')?.value || '';
    if (activeFilter) filterAntrianRows(activeFilter);
}

function filterAntrianRows(q) {
    const needle = (q || '').trim().toLowerCase();
    let count = 0;
    document.querySelectorAll('#antrianTableBody tr.antrian-row').forEach(tr => {
        const match = !needle || (tr.dataset.search || '').includes(needle);
        tr.classList.toggle('d-none', !match);
        if (match) count++;
    });
    document.getElementById('antrianEmptyRow')?.classList.toggle('d-none', count > 0);
}

function openAntrianVerify(orderId) {
    activeVerifyOrder = antrianOrders.find(o => o.id === orderId);
    if (!activeVerifyOrder) return;
    setAntrianAlert('avErrorAlert', '');
    document.getElementById('avPickupCode').textContent = activeVerifyOrder.pickup_code || activeVerifyOrder.order_no;
    document.getElementById('avTotalAmount').textContent = `Rp ${Number(activeVerifyOrder.total_amount || 0).toLocaleString('id-ID')}`;
    document.getElementById('avTransferRef').value = '';
    document.getElementById('avConfirmCheck').checked = false;
    new bootstrap.Modal(document.getElementById('antrianVerifyModal')).show();
}

async function submitAntrianVerify(action) {
    if (!activeVerifyOrder) return;
    const ref = document.getElementById('avTransferRef').value.trim();
    if (action === 'VERIFY' && (!ref || !document.getElementById('avConfirmCheck').checked)) {
        setAntrianAlert('avErrorAlert', 'Isi nomor referensi transfer dan centang konfirmasi dana masuk.');
        return;
    }

    setAntrianAlert('avErrorAlert', '');
    const btnApprove = document.getElementById('btnAvApprove');
    const btnReject = document.getElementById('btnAvReject');
    btnApprove.disabled = true;
    btnReject.disabled = true;

    try {
        const payRes = await antrianApi(`/api/v1/orders/${encodeURIComponent(activeVerifyOrder.id)}/payments`, 'POST', {
            method: 'BANK_TRANSFER',
            amount: Number(activeVerifyOrder.total_amount || 0),
            external_reference: ref || null,
            idempotency_key: generateIdemKey(`avpay-${activeVerifyOrder.id.slice(0, 8)}`),
        });
        await antrianApi(`/api/v1/payments/${encodeURIComponent(payRes.data.payment_id)}/verify`, 'POST', {
            action,
            idempotency_key: generateIdemKey(`avvrf-${payRes.data.payment_id.slice(0, 8)}`),
        });

        bootstrap.Modal.getInstance(document.getElementById('antrianVerifyModal'))?.hide();
        await loadAntrianOrders();
    } catch (err) {
        setAntrianAlert('avErrorAlert', err.message || 'Gagal memproses verifikasi transfer.');
    } finally {
        btnApprove.disabled = false;
        btnReject.disabled = false;
    }
}

function openAntrianPickup(orderId) {
    activePickupOrder = antrianOrders.find(o => o.id === orderId);
    if (!activePickupOrder) return;
    setAntrianAlert('apErrorAlert', '');
    document.getElementById('apPickupCode').textContent = activePickupOrder.pickup_code || '-';
    document.getElementById('apOrderNo').textContent = activePickupOrder.order_no || '-';
    document.getElementById('apTotalAmount').textContent = `Rp ${Number(activePickupOrder.total_amount || 0).toLocaleString('id-ID')}`;
    document.getElementById('apMethod').textContent = `${activePickupOrder.payment_choice} (${activePickupOrder.status})`;
    document.getElementById('apHandoverCheck').checked = true;
    new bootstrap.Modal(document.getElementById('antrianPickupModal')).show();
}

async function submitAntrianPickup() {
    if (!activePickupOrder) return;
    if (!document.getElementById('apHandoverCheck').checked) {
        setAntrianAlert('apErrorAlert', 'Centang konfirmasi serah terima barang fisik.');
        return;
    }

    setAntrianAlert('apErrorAlert', '');
    const btn = document.getElementById('btnApSubmit');
    btn.disabled = true;

    try {
        if (activePickupOrder.payment_choice === 'CASH' && activePickupOrder.status === 'PENDING_PAYMENT') {
            await antrianApi(`/api/v1/orders/${encodeURIComponent(activePickupOrder.id)}/payments`, 'POST', {
                method: 'CASH',
                amount: Number(activePickupOrder.total_amount || 0),
                idempotency_key: generateIdemKey(`apcash-${activePickupOrder.id.slice(0, 8)}`),
            });
        }

        await antrianApi(`/api/v1/orders/${encodeURIComponent(activePickupOrder.id)}/confirm-pickup`, 'POST', {
            pickup_code: activePickupOrder.pickup_code,
            handover_confirmed: true,
            idempotency_key: generateIdemKey(`apk-${activePickupOrder.id.slice(0, 8)}`),
        });

        bootstrap.Modal.getInstance(document.getElementById('antrianPickupModal'))?.hide();
        await loadAntrianOrders();
    } catch (err) {
        setAntrianAlert('apErrorAlert', err.message || 'Gagal mengonfirmasi pickup.');
    } finally {
        btn.disabled = false;
    }
}

function openAntrianCancel(orderId) {
    activeCancelOrder = antrianOrders.find(o => o.id === orderId);
    if (!activeCancelOrder) return;
    setAntrianAlert('acErrorAlert', '');
    document.getElementById('acPickupCode').textContent = activeCancelOrder.pickup_code || activeCancelOrder.order_no;
    new bootstrap.Modal(document.getElementById('antrianCancelModal')).show();
}

async function submitAntrianCancel() {
    if (!activeCancelOrder) return;
    setAntrianAlert('acErrorAlert', '');
    const btn = document.getElementById('btnAcSubmit');
    btn.disabled = true;

    try {
        await antrianApi(`/api/v1/orders/${encodeURIComponent(activeCancelOrder.id)}/cancel`, 'POST', {});
        bootstrap.Modal.getInstance(document.getElementById('antrianCancelModal'))?.hide();
        await loadAntrianOrders();
    } catch (err) {
        setAntrianAlert('acErrorAlert', err.message || 'Gagal membatalkan pesanan.');
    } finally {
        btn.disabled = false;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadAntrianOrders();
});
</script>
@endsection
