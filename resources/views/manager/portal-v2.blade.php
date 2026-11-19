@extends('layouts.app')

@section('title', 'Portal Manajer')

@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    $managerUser = Auth::user();
    $managerMember = $managerUser?->member;
    $managerName = $managerMember?->full_name
        ?? $managerUser?->name
        ?? session('user.name')
        ?? $managerUser?->email
        ?? 'Manajer Operasional';
    $managerEmail = $managerUser?->email ?? session('user.email', '');
    $nameParts = preg_split('/\s+/', trim((string) $managerName)) ?: ['Manajer'];
    $managerFirstName = $nameParts[0] ?? 'Manajer';

    $oltpCategories = [];
    if (Schema::hasTable('categories')) {
        $oltpCategories = DB::table('categories')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn ($c) => (array) $c)
            ->values()
            ->all();
    }

    // Read-only OLTP snapshots untuk initial render (fallback ketika endpoint analitik PIC C/D/E belum aktif)
    $oltpProducts = [];
    if (Schema::hasTable('products')) {
        $oltpProducts = \App\Models\Product::query()
            ->with('inventory:product_id,on_hand,reserved')
            ->orderBy('name')
            ->get()
            ->map(function ($p) {
                $onHand = (int) ($p->inventory?->on_hand ?? 0);
                $reserved = (int) ($p->inventory?->reserved ?? 0);
                $available = max(0, $onHand - $reserved);
                $reorderPoint = (int) ($p->reorder_point ?? 10);
                $rawImg = trim((string) ($p->image_path ?? $p->image ?? ''));
                $imageUrl = '';
                if ($rawImg !== '') {
                    if (str_starts_with($rawImg, 'http://') || str_starts_with($rawImg, 'https://') || str_starts_with($rawImg, '/')) {
                        $imageUrl = $rawImg;
                    } elseif (str_starts_with($rawImg, 'storage/')) {
                        $imageUrl = asset($rawImg);
                    } else {
                        $imageUrl = \Illuminate\Support\Facades\Storage::url($rawImg);
                    }
                }

                return [
                    'id' => (string) $p->id,
                    'sku' => (string) $p->sku,
                    'barcode' => (string) ($p->barcode ?? ''),
                    'name' => (string) $p->name,
                    'category' => (string) ($p->category ?? 'Umum'),
                    'category_id' => (string) ($p->category_id ?? ''),
                    'supplier_id' => (string) ($p->supplier_id ?? ''),
                    'unit' => (string) ($p->unit ?? 'Pcs'),
                    'current_cost' => (float) ($p->current_cost ?? 0),
                    'selling_price' => (float) ($p->selling_price ?? 0),
                    'on_hand' => $onHand,
                    'reserved' => $reserved,
                    'available' => $available,
                    'reorder_point' => $reorderPoint,
                    'min_stock' => $reorderPoint,
                    'image_url' => $imageUrl,
                    'sales_count' => (int) ($p->sales_count ?? 0),
                    'is_active' => (bool) ($p->is_active ?? true),
                ];
            })->values()->all();
    }

    $oltpMembers = [];
    if (Schema::hasTable('members')) {
        $rolesByUser = Schema::hasTable('user_roles')
            ? DB::table('user_roles')->get()->groupBy('user_id')->map(fn ($g) => $g->pluck('role_code')->values()->all())->all()
            : [];

        $oltpMembers = \App\Models\Member::query()
            ->with(['creditAccount:member_id,status,activated_at', 'user:id,email,status'])
            ->orderBy('full_name')
            ->get()
            ->map(function ($m) use ($rolesByUser) {
                $uid = (string) ($m->user_id ?? '');
                return [
                    'id' => (string) $m->id,
                    'employee_number' => (string) ($m->employee_number ?? ''),
                    'member_number' => (string) ($m->member_number ?? ''),
                    'full_name' => (string) $m->full_name,
                    'department' => (string) ($m->department ?? 'Umum'),
                    'status' => (string) ($m->status ?? 'ACTIVE'),
                    'credit_status' => (string) ($m->creditAccount?->status ?? 'BLOCKED'),
                    'email' => (string) ($m->user?->email ?? ''),
                    'roles' => $rolesByUser[$uid] ?? ['MEMBER'],
                ];
            })->values()->all();
    }

    $oltpSuppliers = [];
    if (Schema::hasTable('suppliers')) {
        $oltpSuppliers = DB::table('suppliers')
            ->orderBy('name')
            ->get()
            ->map(fn ($s) => [
                'id' => (string) $s->id,
                'code' => (string) ($s->code ?? ''),
                'name' => (string) $s->name,
                'phone' => (string) ($s->phone ?? ''),
                'address' => (string) ($s->address ?? ''),
                'lead_time_days' => (int) ($s->lead_time_days ?? 1),
                'is_active' => (bool) ($s->is_active ?? true),
            ])
            ->values()
            ->all();
    }

    $oltpExpenses = [];
    if (Schema::hasTable('operating_expenses')) {
        $oltpExpenses = DB::table('operating_expenses')
            ->orderByDesc('expense_date')
            ->limit(50)
            ->get()
            ->map(fn ($e) => (array) $e)
            ->values()
            ->all();
    }

    $oltpImportBatches = [];
    if (Schema::hasTable('import_batches')) {
        $oltpImportBatches = DB::table('import_batches')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get()
            ->map(fn ($b) => (array) $b)
            ->values()
            ->all();
    }

    $oltpPolicyLogs = [];
    if (Schema::hasTable('credit_policy_logs')) {
        $oltpPolicyLogs = \App\Models\CreditPolicyLog::query()
            ->orderByDesc('changed_at')
            ->limit(15)
            ->get()
            ->map(fn ($l) => [
                'old_limit_amount' => $l->old_limit_amount !== null ? (float) $l->old_limit_amount : null,
                'new_limit_amount' => (float) $l->new_limit_amount,
                'reason' => (string) ($l->reason ?? '-'),
                'changed_at' => $l->changed_at ? \Carbon\Carbon::parse($l->changed_at)->timezone('Asia/Jakarta')->format('d M Y H:i') : '-',
            ])->values()->all();
    }

    $oltpAuditLogs = [];
    if (Schema::hasTable('audit_logs')) {
        $oltpAuditLogs = \App\Models\AuditLog::query()
            ->with('actor:id,email')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get()
            ->map(fn ($a) => [
                'created_at' => $a->created_at ? $a->created_at->timezone('Asia/Jakarta')->format('d M Y H:i') : '-',
                'actor' => (string) ($a->actor?->email ?? 'Sistem'),
                'action' => (string) $a->action,
                'entity_type' => (string) ($a->entity_type ?? '-'),
                'entity_id' => (string) ($a->entity_id ?? '-'),
            ])->values()->all();
    }

    $oltpOrdersSummary = [
        'completed_count' => 0,
        'sales_revenue' => 0.0,
        'cash_sales' => 0.0,
        'credit_sales' => 0.0,
        'cogs' => 0.0,
        'verified_payments' => 0.0,
        'cogs_incomplete' => false,
    ];
    $oltpCompletedOrders = [];
    if (Schema::hasTable('orders')) {
        $completedOrders = DB::table('orders')->where('status', 'COMPLETED');
        $oltpOrdersSummary['completed_count'] = (int) (clone $completedOrders)->count();
        $oltpOrdersSummary['sales_revenue'] = (float) (clone $completedOrders)->sum('total_amount');
        $oltpOrdersSummary['cash_sales'] = (float) (clone $completedOrders)->whereIn('payment_choice', ['CASH', 'BANK_TRANSFER'])->sum('total_amount');
        $oltpOrdersSummary['credit_sales'] = (float) (clone $completedOrders)->where('payment_choice', 'CREDIT')->sum('total_amount');

        if (Schema::hasTable('order_items')) {
            $itemAgg = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.status', 'COMPLETED')
                ->selectRaw('COALESCE(SUM(COALESCE(order_items.hpp_snapshot, 0) * order_items.quantity), 0) AS total_cogs, SUM(CASE WHEN order_items.hpp_snapshot IS NULL THEN 1 ELSE 0 END) AS null_hpp_rows')
                ->first();
            $oltpOrdersSummary['cogs'] = (float) ($itemAgg->total_cogs ?? 0);
            $oltpOrdersSummary['cogs_incomplete'] = ((int) ($itemAgg->null_hpp_rows ?? 0)) > 0;

            $oltpCompletedOrders = DB::table('orders as o')
                ->leftJoin('order_items as oi', 'oi.order_id', '=', 'o.id')
                ->leftJoin('products as p', 'p.id', '=', 'oi.product_id')
                ->where('o.status', 'COMPLETED')
                ->groupBy('o.id', 'o.total_amount', 'o.payment_choice', 'o.completed_at', 'o.created_at')
                ->selectRaw('o.id, o.total_amount, o.payment_choice, COALESCE(o.completed_at, o.created_at) AS tx_at, COALESCE(SUM(oi.quantity * COALESCE(oi.hpp_snapshot, p.current_cost, 0)), 0) AS order_cogs')
                ->get()
                ->map(fn ($o) => [
                    'date_jakarta' => \Carbon\Carbon::parse($o->tx_at)->timezone('Asia/Jakarta')->toDateString(),
                    'total_amount' => (float) $o->total_amount,
                    'payment_choice' => (string) $o->payment_choice,
                    'cogs' => (float) $o->order_cogs,
                ])
                ->values()
                ->all();
        }
    }
    $oltpVerifiedPaymentsList = [];
    if (Schema::hasTable('payments')) {
        $oltpOrdersSummary['verified_payments'] = (float) DB::table('payments')->where('status', 'VERIFIED')->sum('amount');
        $oltpVerifiedPaymentsList = DB::table('payments')
            ->where('status', 'VERIFIED')
            ->get(['amount', DB::raw('COALESCE(verified_at, created_at) AS pay_at')])
            ->map(fn ($p) => [
                'date_jakarta' => \Carbon\Carbon::parse($p->pay_at)->timezone('Asia/Jakarta')->toDateString(),
                'amount' => (float) $p->amount,
            ])
            ->values()
            ->all();
    }

    $managerBootstrap = [
        'user' => [
            'name' => $managerName,
            'first_name' => $managerFirstName,
            'email' => $managerEmail,
        ],
        'initial_page' => $initialPage ?? 'home',
        'initial_tab' => (int) ($initialTab ?? 0),
        'today_jakarta' => now('Asia/Jakarta')->toDateString(),
        'routes' => [
            'landing' => route('landing'),
        ],
        'oltp' => [
            'products' => $oltpProducts,
            'categories' => $oltpCategories,
            'members' => $oltpMembers,
            'suppliers' => $oltpSuppliers,
            'expenses' => $oltpExpenses,
            'import_batches' => $oltpImportBatches,
            'policy_logs' => $oltpPolicyLogs,
            'audit_logs' => $oltpAuditLogs,
            'orders_summary' => $oltpOrdersSummary,
            'completed_orders' => $oltpCompletedOrders,
            'verified_payments' => $oltpVerifiedPaymentsList,
            'forecasts' => class_exists(\App\Services\Analytics\AnalyticsQueryService::class)
                ? app(\App\Services\Analytics\AnalyticsQueryService::class)->forecasts(7)
                : null,
            'rendered_at' => now('Asia/Jakarta')->format('d M Y, H:i') . ' WIB',
        ],
    ];
@endphp

@push('styles')
<style>
    .mgr-tabs {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 6px;
        background: #ffffff;
        border: 1px solid #E5E7E9;
        padding: 5px;
        border-radius: 10px;
    }
    .mgr-tabs .btn-tab {
        border: 0;
        background: transparent;
        color: #6D7588;
        font-size: 0.84rem;
        font-weight: 600;
        padding: 7px 14px;
        border-radius: 8px;
        transition: all 0.15s ease;
    }
    .mgr-tabs .btn-tab:hover {
        color: #212121;
        background: #F5F7F9;
    }
    .mgr-tabs .btn-tab.active {
        background: #ecfdf5;
        color: #059669;
        font-weight: 700;
    }
    .mgr-quick-btn {
        cursor: pointer;
        text-align: left;
        width: 100%;
    }
    .mgr-switch {
        appearance: none;
        width: 40px;
        height: 22px;
        border-radius: 99px;
        background: #d1d5db;
        position: relative;
        cursor: pointer;
        padding: 0;
        border: 0;
        flex-shrink: 0;
        transition: background-color 0.15s ease;
    }
    .mgr-switch:checked {
        background: #10b981;
    }
    .mgr-switch::after {
        content: "";
        position: absolute;
        top: 3px;
        left: 3px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: #ffffff;
        transition: left 0.15s ease;
    }
    .mgr-switch:checked::after {
        left: 21px;
    }
    .mgr-progress-track {
        height: 8px;
        border-radius: 6px;
        background: #ecfdf5;
        overflow: hidden;
    }
    .mgr-progress-fill {
        display: block;
        height: 100%;
        border-radius: 6px;
    }
    .mgr-stepper {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .mgr-step-pill {
        padding: 6px 14px;
        border-radius: 99px;
        background: #f3f4f6;
        color: #6b7280;
        font-size: 0.78rem;
        font-weight: 600;
        border: 1px solid #e5e7eb;
    }
    .mgr-step-pill.active {
        background: #059669;
        color: #ffffff;
        border-color: #059669;
    }
    .mgr-step-pill.done {
        background: #ecfdf5;
        color: #059669;
        border-color: #a7f3d0;
    }
    .mgr-chart-wrap {
        position: relative;
        height: 240px;
        width: 100%;
    }
    .mgr-toast {
        position: fixed;
        bottom: 24px;
        left: 50%;
        transform: translateX(-50%);
        background: #1f2937;
        color: #ffffff;
        padding: 10px 18px;
        border-radius: 10px;
        font-size: 0.84rem;
        font-weight: 500;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        z-index: 1090;
        display: none;
        max-width: calc(100vw - 32px);
    }
    #managerPortalRoot .table-responsive .dropdown-menu {
        z-index: 1060;
    }
    html {
        scroll-behavior: smooth;
    }
    @keyframes portalSectionEnter {
        from {
            opacity: 0;
            transform: translateY(6px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    .portal-section-enter {
        animation: portalSectionEnter 220ms cubic-bezier(0.22, 1, 0.36, 1);
        will-change: opacity, transform;
    }
    @media (prefers-reduced-motion: reduce) {
        html {
            scroll-behavior: auto;
        }
        .portal-section-enter {
            animation: none;
        }
    }
</style>
@endpush

@section('content')
<div id="managerPortalRoot" data-ui="manager-v2">
    <div id="pg"></div>
</div>

<!-- Modal Aksi Dinamis Portal Manajer -->
<div class="modal fade" id="mgrActionModal" tabindex="-1" aria-labelledby="mgrModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-white border-bottom px-4 py-3">
                <h6 class="modal-title fw-bold text-dark mb-0" id="mgrModalTitle">Form Manajer</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-4" id="mgrModalBody"></div>
        </div>
    </div>
</div>

<div class="mgr-toast" id="ts" role="status" aria-live="polite"></div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const BOOTSTRAP = @json($managerBootstrap);
const $ = s => document.querySelector(s);
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const escapeHtml = str => String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

const rp = n => {
    const val = Number(n || 0);
    return (val < 0 ? '-' : '') + 'Rp ' + Math.round(Math.abs(val)).toLocaleString('id-ID');
};

const pl = (t, tone = 'success') => {
    const map = {
        g: 'bg-success-subtle text-success border border-success-subtle',
        success: 'bg-success-subtle text-success border border-success-subtle',
        o: 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
        warning: 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
        r: 'bg-danger-subtle text-danger border border-danger-subtle',
        danger: 'bg-danger-subtle text-danger border border-danger-subtle',
        i: 'bg-light text-secondary border',
        pl: 'bg-primary-subtle text-primary border border-primary-subtle',
        v: 'bg-secondary-subtle text-dark border',
    };
    return `<span class="badge ${map[tone] || map.i} fw-semibold">${escapeHtml(t)}</span>`;
};

const toast = t => {
    const e = $('#ts');
    if (!e) return;
    e.textContent = t;
    e.style.display = 'block';
    clearTimeout(window.__tsTimer);
    window.__tsTimer = setTimeout(() => { e.style.display = 'none'; }, 3400);
};

const cd = (title, body, actions = '', cardClass = 'h-100') => `
    <div class="card border-0 shadow-sm bg-white ${cardClass}">
        <div class="card-header bg-white px-4 py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="card-title fw-bold text-dark mb-0 small">${title}</h6>
            ${actions ? `<div class="d-flex align-items-center gap-2 flex-wrap">${actions}</div>` : ''}
        </div>
        <div class="card-body px-4 py-3">${body}</div>
    </div>
`;

const cdTable = (title, body, actions = '') => `
    <div class="card border-0 shadow-sm bg-white">
        <div class="card-header bg-white px-4 py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="card-title fw-bold text-dark mb-0 small">${title}</h6>
            ${actions ? `<div class="d-flex align-items-center gap-2 flex-wrap">${actions}</div>` : ''}
        </div>
        ${body}
    </div>
`;

const renderActionDropdown = (itemsHtml, label = 'Aksi') => `
    <div class="dropdown d-inline-block">
        <button type="button" class="btn btn-light btn-sm border px-2 py-1" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="${escapeHtml(label)}" title="${escapeHtml(label)}">
            <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm border" style="min-width:190px;z-index:1060;">
            ${itemsHtml}
        </ul>
    </div>
`;

const tb = (headers, rows, emptyMsg = 'Tidak ada data untuk ditampilkan.') => {
    const body = rows.length
        ? rows.map(r => `<tr>${r.map(c => `<td class="py-2">${c}</td>`).join('')}</tr>`).join('')
        : `<tr><td colspan="${headers.length}" class="text-center text-muted py-4 small">${escapeHtml(emptyMsg)}</td></tr>`;
    return `<div class="table-responsive"><table class="table table-hover align-middle mb-0" style="font-size:0.84rem;"><thead><tr>${headers.map(h => `<th>${escapeHtml(h)}</th>`).join('')}</tr></thead><tbody>${body}</tbody></table></div>`;
};

const fl = (label, inputHtml) => `<div class="mb-3"><label class="form-label small fw-semibold text-secondary mb-1">${label}</label>${inputHtml}</div>`;

const ph = (title, subtitle, rightHtml = '') => `
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div class="min-w-0">
            <h4 class="fw-bold mb-1 text-dark">${escapeHtml(title)}</h4>
            ${subtitle ? `<p class="text-secondary small mb-0">${escapeHtml(subtitle)}</p>` : ''}
        </div>
        ${rightHtml ? `<div class="d-flex align-items-center gap-2 flex-wrap">${rightHtml}</div>` : ''}
    </div>
`;

function openMgrModal(title, html) {
    $('#mgrModalTitle').textContent = title;
    $('#mgrModalBody').innerHTML = html;
    bootstrap.Modal.getOrCreateInstance($('#mgrActionModal')).show();
}

function closeMgrModal() {
    const el = $('#mgrActionModal');
    const inst = el ? bootstrap.Modal.getInstance(el) : null;
    if (inst) inst.hide();
}

/* ---------- Helper API Terpusat (fetch + CSRF Token) ---------- */
async function managerApi(url, method = 'GET', body = null) {
    const headers = {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
    };
    const options = { method, headers, credentials: 'same-origin' };

    if (body instanceof FormData) {
        options.body = body;
    } else if (body !== null) {
        headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
    }

    const res = await fetch(url, options);
    let data = {};
    try {
        data = await res.json();
    } catch (_) {
        data = {};
    }

    if (!res.ok || data.success === false) {
        const firstValidationErr = data.errors ? Object.values(data.errors).flat()[0] : null;
        const err = new Error(firstValidationErr || data.message || `Permintaan gagal (HTTP ${res.status}).`);
        err.status = res.status;
        err.payload = data;
        throw err;
    }
    return data;
}

const MGR_PERIOD_OPTIONS = [
    ['day', 'Hari Ini'],
    ['week', 'Pekan Ini'],
    ['month', 'Bulan Ini'],
    ['year', 'Tahun Ini'],
];

function normalizePeriodKey(period = 'month') {
    const p = String(period || 'month').toLowerCase().trim();
    if (['day', 'today', 'daily', 'harian', 'hari_ini'].includes(p)) return 'day';
    if (['week', 'this_week', 'weekly', 'pekanan', 'pekan_ini'].includes(p)) return 'week';
    if (['year', 'this_year', 'yearly', 'tahunan', 'tahun_ini'].includes(p)) return 'year';
    return 'month';
}

function getPeriodRange(period = 'month') {
    const key = normalizePeriodKey(period);
    const todayStr = BOOTSTRAP.today_jakarta || new Date().toISOString().slice(0, 10);
    const [y, m, d] = todayStr.split('-').map(Number);
    const baseDate = new Date(Date.UTC(y, m - 1, d));
    const fmt = dt => `${dt.getUTCFullYear()}-${String(dt.getUTCMonth() + 1).padStart(2, '0')}-${String(dt.getUTCDate()).padStart(2, '0')}`;

    if (key === 'day') return { start: todayStr, end: todayStr };
    if (key === 'week') {
        const dow = baseDate.getUTCDay() || 7; // Senin=1 .. Minggu=7
        const monday = new Date(baseDate.getTime());
        monday.setUTCDate(monday.getUTCDate() - (dow - 1));
        return { start: fmt(monday), end: todayStr };
    }
    if (key === 'year') return { start: `${y}-01-01`, end: todayStr };
    return { start: `${y}-${String(m).padStart(2, '0')}-01`, end: todayStr };
}

function renderPeriodButtonsHtml(activePeriod = state.period, scope = 'rep') {
    const activeKey = normalizePeriodKey(activePeriod);
    return `
        <div class="mgr-tabs" role="group" aria-label="Filter Periode Laporan">
            ${MGR_PERIOD_OPTIONS.map(([val, lbl]) => `
                <button type="button"
                        class="btn-tab mgr-period-btn ${activeKey === val ? 'active' : ''}"
                        data-period="${val}"
                        onclick="onManagerPeriodChange('${val}', '${scope}')">
                    ${escapeHtml(lbl)}
                </button>
            `).join('')}
        </div>
    `;
}

function syncPeriodControlsAndExports() {
    const activeKey = normalizePeriodKey(state.period);
    const { start, end } = getPeriodRange(activeKey);

    document.querySelectorAll('.mgr-period-btn[data-period]').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.period === activeKey);
    });
    const perSel = $('#per');
    if (perSel && perSel.value !== activeKey) {
        perSel.value = activeKey;
    }

    const pdfBtn = $('#btnExportPdf');
    const xlsxBtn = $('#btnExportXlsx');
    const qs = `period=${encodeURIComponent(activeKey)}&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`;
    if (pdfBtn) pdfBtn.href = `/api/v1/reports/financial/export?${qs}&format=pdf`;
    if (xlsxBtn) xlsxBtn.href = `/api/v1/reports/financial/export?${qs}&format=xlsx`;
}

function computeLocalOltpSummaryForPeriod(period = state.period) {
    const { start, end } = getPeriodRange(period);
    const orders = (BOOTSTRAP.oltp.completed_orders || []).filter(o => o.date_jakarta >= start && o.date_jakarta <= end);
    const opexRows = (BOOTSTRAP.oltp.expenses || []).filter(e => {
        const d = String(e.expense_date || '').slice(0, 10);
        return d >= start && d <= end;
    });
    const payRows = (BOOTSTRAP.oltp.verified_payments || []).filter(p => p.date_jakarta >= start && p.date_jakarta <= end);

    const sales_revenue = orders.reduce((s, o) => s + Number(o.total_amount || 0), 0);
    const cash_sales = orders.filter(o => o.payment_choice !== 'CREDIT').reduce((s, o) => s + Number(o.total_amount || 0), 0);
    const credit_sales = orders.filter(o => o.payment_choice === 'CREDIT').reduce((s, o) => s + Number(o.total_amount || 0), 0);
    const cogs = orders.reduce((s, o) => s + Number(o.cogs || 0), 0);
    const opex = opexRows.reduce((s, e) => s + Number(e.amount || 0), 0);
    const verified_payments = payRows.reduce((s, p) => s + Number(p.amount || 0), 0);

    return {
        start,
        end,
        completed_count: orders.length,
        sales_revenue,
        cash_sales,
        credit_sales,
        cogs,
        opex,
        verified_payments,
    };
}

function onManagerPeriodChange(period, scope = 'rep') {
    state.period = normalizePeriodKey(period);
    syncPeriodControlsAndExports();
    if (scope === 'home' || activePageId === 'home') {
        loadExecutiveDashboard(state.period);
    } else {
        loadReportsAndAnalytics(state.period);
    }
}

/* ---------- State Portal Manajer ---------- */
const state = {
    period: 'month',
    orderStatusFilter: '',
    orders: [],
    receivables: [],
    creditPolicy: null,
    agingSummary: null,
    financialReport: null,
    analyticsSummary: null,
    topProducts: null,
    forecasts: BOOTSTRAP.oltp.forecasts || null,
    forecastHorizon: 7,
    importBatch: null,
    importPreview: null,
    importStep: 0,
    charts: {},
};

const TB = { ops: 0, data: 0, rep: 0 };
if (Object.prototype.hasOwnProperty.call(TB, BOOTSTRAP.initial_page)) {
    TB[BOOTSTRAP.initial_page] = Number(BOOTSTRAP.initial_tab || 0);
}

const MGR_BRAND_NAME = @json(config('koperasi.brand_name', 'KoperasiKu'));
const MGR_PAGE_TITLES = {
    home: 'Portal Manajer',
    ops: 'Transaksi & Operasional',
    ar: 'Piutang & Kebijakan Kredit',
    data: 'Master Data & Stok',
    rep: 'Laporan & Analitik',
    sys: 'Sistem & Audit Log',
};

function syncSidebarActive(pageId) {
    document.querySelectorAll('.sidebar .nav-link[data-mgr-page]').forEach(link => {
        link.classList.toggle('active', link.dataset.mgrPage === pageId);
    });
    document.querySelectorAll('.mobile-role-nav a[data-mgr-page]').forEach(link => {
        const isActive = link.dataset.mgrPage === pageId;
        link.classList.toggle('btn-success', isActive);
        link.classList.toggle('btn-light', !isActive);
        link.classList.toggle('border', !isActive);
    });
    const pageTitle = MGR_PAGE_TITLES[pageId] || 'Portal Manajer';
    document.title = `${pageTitle} - ${MGR_BRAND_NAME}`;
}

const go = id => { location.hash = id; };
const tabs = (pg, labels) => `
    <div class="mt-3 mb-3">
        <div class="mgr-tabs" role="tablist">
            ${labels.map((lbl, i) => `<button type="button" role="tab" class="btn-tab ${TB[pg] == i ? 'active' : ''}" onclick="TB.${pg}=${i};route()">${escapeHtml(lbl)}</button>`).join('')}
        </div>
    </div>
`;

const S = {}, H = {};
let activePageId = null;
let activeTabKey = null;
let animateSubTabOnly = false;

const wrapSectionEnter = html => `<div class="portal-section-enter">${html}</div>`;
const renderSubTabShell = (pg, labels, panels) => {
    const body = panels[TB[pg]]();
    return `${tabs(pg, labels)}${animateSubTabOnly ? wrapSectionEnter(body) : body}`;
};

function destroyCharts() {
    Object.values(state.charts).forEach(ch => { try { ch?.destroy(); } catch (_) {} });
    state.charts = {};
}

function route() {
    destroyCharts();
    let id = location.hash.slice(1);
    if (!S[id]) id = BOOTSTRAP.initial_page || 'home';
    if (!S[id]) id = 'home';

    const pageChanged = activePageId !== id;
    const tabKey = `${id}:${TB[id] ?? 0}`;
    const tabChanged = !pageChanged && activeTabKey !== tabKey;
    animateSubTabOnly = tabChanged;

    syncSidebarActive(id);
    const rendered = S[id]();
    $('#pg').innerHTML = pageChanged ? wrapSectionEnter(rendered) : rendered;

    const shouldScrollTop = pageChanged && activePageId !== null;
    activePageId = id;
    activeTabKey = tabKey;
    animateSubTabOnly = false;

    (H[id] || (() => {}))();
    if (shouldScrollTop) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

window.onhashchange = () => {
    route();
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

/* ---------- Helper Aging Piutang dari Data Server ---------- */
function formatReceivablesAgingFromServer(rows) {
    if (state.agingSummary && Array.isArray(state.agingSummary.buckets)) {
        return state.agingSummary.buckets;
    }
    const openRows = (rows || []).filter(r => r.effective_status !== 'PAID' && Number(r.outstanding_amount || 0) > 0);
    const today = new Date();
    const todayUtc = Date.UTC(today.getFullYear(), today.getMonth(), today.getDate());
    const buckets = [
        { key: 'current', label: 'Belum jatuh tempo (Current)', amount: 0, count: 0, color: '#10b981' },
        { key: '1_30', label: '1–30 hari lewat tempo', amount: 0, count: 0, color: '#f59e0b' },
        { key: '31_60', label: '31–60 hari lewat tempo', amount: 0, count: 0, color: '#f97316' },
        { key: '61_90', label: '61–90 hari lewat tempo', amount: 0, count: 0, color: '#ef4444' },
        { key: 'gt_90', label: '>90 hari lewat tempo', amount: 0, count: 0, color: '#991b1b' },
    ];
    openRows.forEach(r => {
        const amt = Number(r.outstanding_amount || 0);
        if (r.effective_status !== 'OVERDUE' || !r.due_date) {
            buckets[0].amount += amt;
            buckets[0].count += 1;
            return;
        }
        const due = new Date(String(r.due_date).slice(0, 10) + 'T00:00:00Z');
        const diffDays = Math.max(1, Math.floor((todayUtc - due.getTime()) / 86400000));
        const idx = diffDays <= 30 ? 1 : (diffDays <= 60 ? 2 : (diffDays <= 90 ? 3 : 4));
        buckets[idx].amount += amt;
        buckets[idx].count += 1;
    });
    return buckets;
}

function renderAgingBarsHtml(rows) {
    const buckets = formatReceivablesAgingFromServer(rows);
    const maxAmt = Math.max(...buckets.map(b => b.amount), 1);
    return buckets.map(b => {
        const pct = Math.round((b.amount / maxAmt) * 100);
        return `
            <div class="mb-2.5">
                <div class="d-flex justify-content-between align-items-center small mb-1">
                    <span class="text-secondary">${escapeHtml(b.label)} (${b.count})</span>
                    <strong class="text-dark tokped-price">${rp(b.amount)}</strong>
                </div>
                <div class="mgr-progress-track"><span class="mgr-progress-fill" style="width:${pct}%;background:${b.color}"></span></div>
            </div>
        `;
    }).join('') + `<small class="text-muted d-block mt-2" style="font-size:0.72rem;">Saldo snapshot terkini — tidak dijumlahkan lintas hari.</small>`;
}

function statCardHtml(title, value, sub, iconClass, iconBg, iconColor, valClass = 'text-dark') {
    return `
        <div class="tokped-stat-card h-100 d-flex align-items-start justify-content-between gap-2">
            <div class="min-w-0">
                <small class="text-muted fw-semibold d-block mb-1" style="font-size:0.74rem;">${escapeHtml(title)}</small>
                <h4 class="fw-bold ${valClass} mb-0 fs-5 tokped-price">${escapeHtml(value)}</h4>
                <small class="text-muted d-block text-truncate mt-1" style="font-size:0.7rem;" title="${escapeHtml(sub)}">${escapeHtml(sub)}</small>
            </div>
            <span class="tokped-stat-icon" style="background:${iconBg};color:${iconColor};">
                <i class="bi ${iconClass}"></i>
            </span>
        </div>
    `;
}

/* ---------- 1. BERANDA EKSEKUTIF ---------- */
S.home = () => {
    return `${ph(
        'Beranda & Ringkasan',
        `Selamat datang, ${BOOTSTRAP.user.first_name}. Ringkasan operasional, keuangan, piutang, dan analitik koperasi.`,
        `${renderPeriodButtonsHtml(state.period, 'home')}
        <button type="button" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1.5" onclick="loadExecutiveDashboard(state.period)">
            <i class="bi bi-arrow-clockwise"></i>
            <span>Muat Ulang</span>
        </button>`
    )}
    <div id="homeStatusBanner"></div>
    <div class="row g-3 g-xl-4 mb-4" id="kp"></div>
    <div class="row g-3 g-xl-4 mb-4" id="homeQuickActions"></div>
    <div class="row g-3 g-xl-4 mb-4">
        <div class="col-12 col-lg-8">
            ${cd('Omzet vs Laba Kotor', '<div class="mgr-chart-wrap"><canvas id="homeRevenueChart"></canvas></div><small class="text-muted d-block mt-2" id="homeTrendNote">Memuat data periode...</small>')}
        </div>
        <div class="col-12 col-lg-4">
            ${cd('Komposisi Penjualan &amp; Kas Masuk', '<div class="mgr-chart-wrap" style="height:190px"><canvas id="homeCompositionChart"></canvas></div><div id="homeCompositionLegend" class="mt-3"></div>')}
        </div>
    </div>
    <div class="row g-3 g-xl-4">
        <div class="col-12 col-lg-4">
            ${cd('Aging Piutang (5 Kelompok)', '<div id="homeAgingBox">Memuat...</div>', '<button type="button" class="btn btn-outline-primary btn-sm pos-btn-compact" onclick="go(\'ar\')">Detail</button>')}
        </div>
        <div class="col-12 col-lg-4">
            ${cd('Prediksi Permintaan (7 / 30 Hari)', '<div id="homeForecastBox">Memuat...</div>', '<button type="button" class="btn btn-outline-primary btn-sm pos-btn-compact" onclick="TB.rep=3;go(\'rep\');route()">Detail</button>')}
        </div>
        <div class="col-12 col-lg-4">
            ${cd('Produk Terlaris &amp; Stok Kritis', '<div id="homeTopProductsBox">Memuat...</div>', '<button type="button" class="btn btn-outline-primary btn-sm pos-btn-compact" onclick="TB.data=0;go(\'data\');route()">Master</button>')}
        </div>
    </div>`;
};

function onHomePeriodChange(period) {
    onManagerPeriodChange(period, 'home');
}

H.home = () => loadExecutiveDashboard(state.period);

async function loadExecutiveDashboard(period = state.period || 'month') {
    state.period = normalizePeriodKey(period);
    syncPeriodControlsAndExports();
    const { start, end } = getPeriodRange(state.period);
    const query = `?period=${encodeURIComponent(state.period)}&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`;

    const [ordersRes, recvRes, policyRes, finRes, summaryRes, topRes, forecastRes, agingRes] = await Promise.allSettled([
        managerApi('/api/v1/cashier/orders'),
        managerApi('/api/v1/manager/receivables'),
        managerApi('/api/v1/credit-policy'),
        managerApi(`/api/v1/reports/financial${query}`),
        managerApi(`/api/v1/analytics/summary${query}`),
        managerApi(`/api/v1/analytics/top-products${query}`),
        managerApi('/api/v1/analytics/forecasts?horizon=7,30'),
        managerApi('/api/v1/manager/receivables/aging'),
    ]);

    if (ordersRes.status === 'fulfilled') state.orders = ordersRes.value.data || [];
    if (recvRes.status === 'fulfilled') state.receivables = recvRes.value.data || [];
    if (policyRes.status === 'fulfilled') state.creditPolicy = policyRes.value.data || null;
    state.financialReport = finRes.status === 'fulfilled' ? (finRes.value.data || finRes.value) : null;
    state.analyticsSummary = summaryRes.status === 'fulfilled' ? (summaryRes.value.data || summaryRes.value) : null;
    state.topProducts = topRes.status === 'fulfilled' ? (topRes.value.data || topRes.value) : null;
    state.forecasts = forecastRes.status === 'fulfilled' ? (forecastRes.value.data || forecastRes.value) : null;
    state.agingSummary = agingRes.status === 'fulfilled' ? (agingRes.value.data || agingRes.value) : null;

    const freshBadge = $('#etlFreshBadge');
    if (freshBadge) updateFreshnessBadge();
    if ($('#kp')) renderHomeDashboardContent();
}

function updateFreshnessBadge() {
    const el = $('#etlFreshBadge');
    if (!el) return;
}

function renderHomeDashboardContent() {
    const fin = state.financialReport || state.analyticsSummary || {};
    const m = fin.metrics || {};
    const hasAnalytics = Boolean(state.financialReport || state.analyticsSummary);
    const oltpOrd = computeLocalOltpSummaryForPeriod(state.period);

    const omzet = hasAnalytics && m.sales_revenue !== undefined ? Number(m.sales_revenue) : oltpOrd.sales_revenue;
    const cashSales = hasAnalytics && m.cash_sales_revenue !== undefined ? Number(m.cash_sales_revenue) : oltpOrd.cash_sales;
    const creditSales = hasAnalytics && m.new_credit_sales !== undefined ? Number(m.new_credit_sales) : oltpOrd.credit_sales;
    const cogs = hasAnalytics && m.cogs !== undefined ? Number(m.cogs) : oltpOrd.cogs;
    const grossProfit = hasAnalytics && m.gross_profit !== undefined ? Number(m.gross_profit) : (omzet - cogs);
    const opex = hasAnalytics && m.operating_expenses !== undefined ? Number(m.operating_expenses) : oltpOrd.opex;
    const netProfit = hasAnalytics && m.estimated_net_profit !== undefined ? Number(m.estimated_net_profit) : (grossProfit - opex);
    const marginPct = omzet > 0 ? ((grossProfit / omzet) * 100).toFixed(1) : '0.0';
    const cashIn = hasAnalytics && m.cash_receipts !== undefined ? Number(m.cash_receipts) : oltpOrd.verified_payments;
    const openRecv = hasAnalytics && m.receivables_outstanding_as_of_end !== undefined
        ? Number(m.receivables_outstanding_as_of_end)
        : state.receivables.reduce((s, r) => s + Number(r.outstanding_amount || 0), 0);
    const overdueRecv = hasAnalytics && m.overdue_outstanding_as_of_end !== undefined
        ? Number(m.overdue_outstanding_as_of_end)
        : state.receivables.filter(r => r.effective_status === 'OVERDUE').reduce((s, r) => s + Number(r.outstanding_amount || 0), 0);
    const collectedRecv = hasAnalytics && m.receivable_payments_collected !== undefined ? Number(m.receivable_payments_collected) : null;
    const totalStockAvail = BOOTSTRAP.oltp.products.reduce((s, p) => s + Number(p.available || 0), 0);
    const activeMembersCount = BOOTSTRAP.oltp.members.filter(x => x.status === 'ACTIVE').length;
    const txCount = hasAnalytics && m.completed_orders_count !== undefined ? Number(m.completed_orders_count) : oltpOrd.completed_count;

    const cogsIncomplete = fin.data_quality ? !fin.data_quality.cogs_complete : oltpOrd.cogs_incomplete;
    const expIncomplete = fin.data_quality ? !fin.data_quality.expenses_complete : BOOTSTRAP.oltp.expenses.length === 0;
    const isStale = Boolean(fin.is_stale);

    const banner = $('#homeStatusBanner');
    if (banner) {
        const notes = [];
        if (!hasAnalytics) {
            notes.push('Menampilkan ringkasan transaksi operasional terkini.');
        }
        if (isStale) {
            notes.push(`<strong>Sinkronisasi Tertunda:</strong> data analitik belum terbarui dari transaksi terkini (pembaruan terakhir: ${escapeHtml(fin.last_updated_at || '-')}).`);
        }
        if (cogsIncomplete || expIncomplete) {
            notes.push('<strong>Estimasi / data belum lengkap:</strong> sebagian HPP historis atau biaya operasional belum tercatat penuh.');
        }
        banner.innerHTML = notes.length
            ? `<div class="alert ${isStale ? 'alert-warning' : 'alert-light border'} py-2.5 px-3 small mb-4">${notes.join(' ')}</div>`
            : '';
    }

    const kpiCards = [
        ['Omzet Penjualan Selesai', rp(omzet), `${txCount} Transaksi Selesai`, 'bi-cash-coin', '#ecfdf5', '#059669', 'text-dark'],
        ['Tunai / Transfer vs Kredit', rp(cashSales), `Penjualan piutang: ${rp(creditSales)}`, 'bi-credit-card-2-front', '#f3f4f6', '#4b5563', 'text-dark'],
        ['HPP & Laba Kotor', rp(grossProfit), `HPP: ${rp(cogs)} · Margin ${marginPct}%`, 'bi-graph-up-arrow', '#ecfdf5', '#059669', 'text-success'],
        ['Biaya & Estimasi Laba Bersih', rp(netProfit), `OPEX: ${rp(opex)} ${(cogsIncomplete || expIncomplete) ? '· estimasi' : ''}`, 'bi-calculator', '#f3f4f6', '#4b5563', 'text-dark'],
        ['Kas Masuk Terverifikasi', rp(cashIn), collectedRecv !== null ? `Cicilan piutang: ${rp(collectedRecv)}` : 'Pembayaran status VERIFIED', 'bi-wallet2', '#ecfdf5', '#059669', 'text-success'],
        ['Piutang Terbuka (Snapshot)', rp(openRecv), `Jatuh tempo (OVERDUE): ${rp(overdueRecv)}`, 'bi-exclamation-circle', '#fff7ed', '#d97706', overdueRecv > 0 ? 'text-danger' : 'text-dark'],
        ['Stok Tersedia Gerai', `${totalStockAvail.toLocaleString('id-ID')} unit`, `${BOOTSTRAP.oltp.products.filter(p => p.available <= 0).length} SKU stok habis`, 'bi-box-seam', '#f3f4f6', '#4b5563', 'text-dark'],
        ['Anggota Aktif & Limit Global', `${activeMembersCount} anggota`, state.creditPolicy ? `Limit global: ${rp(state.creditPolicy.limit_amount)} (7 hari)` : 'Limit global belum dimuat', 'bi-people', '#ecfdf5', '#059669', 'text-dark'],
    ];

    $('#kp').innerHTML = kpiCards.map(c => `
        <div class="col-6 col-md-3">
            ${statCardHtml(c[0], c[1], c[2], c[3], c[4], c[5], c[6])}
        </div>
    `).join('');

    const activeOrdersCount = state.orders.filter(o => ['PENDING_PAYMENT', 'PENDING_PICKUP', 'READY_FOR_PICKUP'].includes(o.status)).length;
    const overdueCount = state.receivables.filter(r => r.effective_status === 'OVERDUE').length;
    const criticalStockCount = BOOTSTRAP.oltp.products.filter(p => p.available <= Math.max(p.reorder_point, 10)).length;

    $('#homeQuickActions').innerHTML = `
        <div class="col-12 col-md-4">
            <button type="button" class="tokped-stat-card mgr-quick-btn h-100 d-flex align-items-center justify-content-between gap-2" onclick="go('ops')">
                <div class="min-w-0">
                    <small class="text-muted fw-semibold d-block mb-1" style="font-size:0.74rem;">Antrian Pesanan Aktif</small>
                    <h5 class="fw-bold text-dark mb-0 tokped-price">${activeOrdersCount} Pesanan</h5>
                    <small class="text-success d-block mt-1" style="font-size:0.7rem;">Lihat operasional pesanan →</small>
                </div>
                <span class="tokped-stat-icon" style="background:#FFF5E6;color:#FA591D;"><i class="bi bi-bag-check"></i></span>
            </button>
        </div>
        <div class="col-12 col-md-4">
            <button type="button" class="tokped-stat-card mgr-quick-btn h-100 d-flex align-items-center justify-content-between gap-2" onclick="go('ar')">
                <div class="min-w-0">
                    <small class="text-muted fw-semibold d-block mb-1" style="font-size:0.74rem;">Faktur Piutang Overdue</small>
                    <h5 class="fw-bold text-danger mb-0 tokped-price">${overdueCount} Faktur</h5>
                    <small class="text-muted d-block mt-1" style="font-size:0.7rem;">Total jatuh tempo: ${rp(overdueRecv)}</small>
                </div>
                <span class="tokped-stat-icon" style="background:#FFEAEA;color:#EF144A;"><i class="bi bi-clock-history"></i></span>
            </button>
        </div>
        <div class="col-12 col-md-4">
            <button type="button" class="tokped-stat-card mgr-quick-btn h-100 d-flex align-items-center justify-content-between gap-2" onclick="TB.ops=1;go('ops');route()">
                <div class="min-w-0">
                    <small class="text-muted fw-semibold d-block mb-1" style="font-size:0.74rem;">Stok Habis / Kritis</small>
                    <h5 class="fw-bold text-dark mb-0 tokped-price">${criticalStockCount} SKU</h5>
                    <small class="text-success d-block mt-1" style="font-size:0.7rem;">Periksa persediaan gerai →</small>
                </div>
                <span class="tokped-stat-icon" style="background:#ecfdf5;color:#059669;"><i class="bi bi-boxes"></i></span>
            </button>
        </div>
    `;

    renderHomeCharts(omzet, grossProfit, cashSales, creditSales, cogs);

    const agingBox = $('#homeAgingBox');
    if (agingBox) agingBox.innerHTML = renderAgingBarsHtml(state.receivables);

    const forecastBox = $('#homeForecastBox');
    if (forecastBox) {
        if (state.forecasts && Array.isArray(state.forecasts.items || state.forecasts)) {
            const list = (state.forecasts.items || state.forecasts).slice(0, 5);
            forecastBox.innerHTML = tb(
                ['Produk', '7H', '30H', 'Saran Restok'],
                list.map(f => {
                    const recQty = Number(f.recommended_restock_qty_7d ?? f.recommended_restock_qty ?? f.recommended_qty ?? 0);
                    const statusBadge = f.insufficient_data
                        ? pl('Data Histori Kurang', 'o')
                        : (recQty > 0
                            ? pl(`Restok +${recQty} ${f.unit || ''}`.trim(), f.urgency === 'TINGGI' ? 'r' : 'o')
                            : pl('Stok Cukup', 'g'));
                    return [
                        `<span class="fw-semibold text-dark">${escapeHtml(f.name || f.sku || '-')}</span><br><small class="text-muted font-monospace">${escapeHtml(f.sku || '')}</small>`,
                        `${f.forecast_7d ?? '-'}`,
                        `${f.forecast_30d ?? '-'}`,
                        statusBadge,
                    ];
                })
            );
        } else {
            forecastBox.innerHTML = '<div class="alert alert-light border small mb-0">Memuat prediksi permintaan dari histori transaksi...</div>';
        }
    }

    const topBox = $('#homeTopProductsBox');
    if (topBox) {
        const topList = state.topProducts && Array.isArray(state.topProducts.items || state.topProducts)
            ? (state.topProducts.items || state.topProducts)
            : null;
        if (topList && topList.length > 0) {
            const items = topList.slice(0, 5);
            topBox.innerHTML = tb(
                ['Produk', 'Unit', 'Omzet'],
                items.map(it => [escapeHtml(it.name || it.sku), it.units ?? it.sold_qty ?? 0, rp(it.sales_revenue ?? it.omzet ?? 0)])
            );
        } else if (topList && topList.length === 0) {
            topBox.innerHTML = '<div class="alert alert-light border small mb-0">Belum ada penjualan selesai pada periode ini.</div>';
        } else {
            const critical = [...BOOTSTRAP.oltp.products]
                .sort((a, b) => a.available - b.available)
                .slice(0, 5);
            topBox.innerHTML = tb(
                ['Produk', 'Tersedia', 'Status'],
                critical.map(p => [
                    escapeHtml(p.name),
                    `${p.available} ${escapeHtml(p.unit)}`,
                    p.available <= 0 ? pl('Habis', 'r') : (p.available <= Math.max(p.reorder_point, 10) ? pl('Kritis', 'o') : pl('Aman', 'g'))
                ])
            );
        }
    }
}

function renderHomeCharts(omzet, grossProfit, cashSales, creditSales, cogs) {
    const revCanvas = $('#homeRevenueChart');
    if (revCanvas && typeof Chart !== 'undefined') {
        if (state.charts.homeRevenue) {
            try { state.charts.homeRevenue.destroy(); } catch (_) {}
            state.charts.homeRevenue = null;
        }
        const trend = state.analyticsSummary?.trend || state.financialReport?.rows || null;
        const labels = Array.isArray(trend) && trend.length ? trend.map(r => r.label || r.period || r.date) : ['Periode Terpilih'];
        const revSeries = Array.isArray(trend) && trend.length ? trend.map(r => Number(r.sales_revenue || 0)) : [omzet];
        const gpSeries = Array.isArray(trend) && trend.length ? trend.map(r => Number(r.gross_profit || (Number(r.sales_revenue || 0) - Number(r.cogs || 0)))) : [grossProfit];

        state.charts.homeRevenue = new Chart(revCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    { label: 'Omzet', data: revSeries, backgroundColor: '#059669', borderRadius: 6 },
                    { label: 'Laba Kotor', data: gpSeries, backgroundColor: '#34d399', borderRadius: 6 },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } },
            },
        });
        const note = $('#homeTrendNote');
        if (note) {
            note.textContent = Array.isArray(trend) && trend.length
                ? `Periode ${getPeriodRange(state.period).start} s/d ${getPeriodRange(state.period).end} · Omzet ${rp(omzet)}, HPP ${rp(cogs)}, Laba kotor ${rp(grossProfit)}.`
                : `Agregat periode saat ini · Omzet ${rp(omzet)}, HPP ${rp(cogs)}, Laba kotor ${rp(grossProfit)}.`;
        }
    }

    const compCanvas = $('#homeCompositionChart');
    if (compCanvas && typeof Chart !== 'undefined') {
        if (state.charts.homeComp) {
            try { state.charts.homeComp.destroy(); } catch (_) {}
            state.charts.homeComp = null;
        }
        const total = cashSales + creditSales;
        const dataVals = total > 0 ? [cashSales, creditSales] : [0, 0];
        state.charts.homeComp = new Chart(compCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Tunai / Transfer', 'Piutang Anggota'],
                datasets: [{ data: dataVals, backgroundColor: ['#10b981', '#f59e0b'], borderWidth: 2, borderColor: '#fff' }],
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '65%' },
        });
        const legend = $('#homeCompositionLegend');
        if (legend) {
            legend.innerHTML = `<div class="d-flex justify-content-between small"><span>Tunai / Transfer</span><strong>${rp(cashSales)}</strong></div>`
                + `<div class="d-flex justify-content-between small mt-1"><span>Piutang Anggota</span><strong>${rp(creditSales)}</strong></div>`;
        }
    }
}

/* ---------- 2. TRANSAKSI & STOK (TANPA POS SIMULASI ACAK) ---------- */
S.ops = () => `${ph(
    'Transaksi & Operasional',
    'Pantau antrian order dari server dan kondisi persediaan gerai.'
)}${renderSubTabShell('ops', ['Pesanan & Pickup', 'Persediaan Stok'], [SO, SK])}`;

H.ops = () => {
    if (TB.ops === 0) loadManagerOrders(state.orderStatusFilter);
};

const ORDER_BADGES = {
    PENDING_PAYMENT: ['Menunggu Pembayaran', 'o'],
    PENDING_PICKUP: ['Siap Pickup (Kredit)', 'pl'],
    READY_FOR_PICKUP: ['Siap Diambil (Lunas)', 'g'],
    COMPLETED: ['Selesai', 'g'],
    CANCELLED: ['Dibatalkan', 'r'],
};

const SO = () => {
    const statuses = [
        ['', 'Semua Aktif'],
        ['PENDING_PAYMENT', 'Menunggu Bayar'],
        ['PENDING_PICKUP', 'Pickup Kredit'],
        ['READY_FOR_PICKUP', 'Siap Diambil'],
        ['COMPLETED', 'Selesai'],
    ];
    return `
        ${cdTable(
            'Daftar Pesanan',
            '<div id="opsOrdersTableWrap" class="p-3 text-muted small">Memuat antrian pesanan...</div>',
            `<select class="form-select form-select-sm" style="width:auto;min-width:170px;" id="opsOrderStatusFilter" onchange="loadManagerOrders(this.value)">
                ${statuses.map(([v, l]) => `<option value="${v}" ${state.orderStatusFilter === v ? 'selected' : ''}>${l}</option>`).join('')}
            </select>
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="loadManagerOrders(state.orderStatusFilter)"><i class="bi bi-arrow-clockwise"></i> Muat Ulang</button>`
        )}
        <div class="alert alert-light border small mt-3 mb-0">
            <i class="bi bi-info-circle text-success me-1"></i>
            Stok fisik berkurang dan penjualan/HPP dicatat atomik hanya setelah serah terima pickup dikonfirmasi.
        </div>
    `;
};

async function loadManagerOrders(status = '') {
    state.orderStatusFilter = status;
    const wrap = $('#opsOrdersTableWrap');
    if (wrap) wrap.innerHTML = '<div class="p-4 text-muted small">Memuat dari server...</div>';
    try {
        const q = status ? `?status=${encodeURIComponent(status)}` : '';
        const res = await managerApi(`/api/v1/cashier/orders${q}`);
        state.orders = Array.isArray(res.data) ? res.data : [];
        if (wrap) {
            wrap.className = '';
            wrap.innerHTML = tb(
                ['Order & Pickup', 'Channel', 'Item', 'Total', 'Metode', 'Status'],
                state.orders.map(o => {
                    const stInfo = ORDER_BADGES[o.status] || [o.status, 'i'];
                    const itemCount = Array.isArray(o.items) ? o.items.reduce((s, it) => s + Number(it.quantity || 0), 0) : 0;
                    return [
                        `<span class="fw-bold font-monospace">${escapeHtml(o.order_no || '-')}</span><br><small class="text-muted font-monospace">${escapeHtml(o.pickup_code || o.id)}</small>`,
                        escapeHtml(o.channel || '-'),
                        `${itemCount} unit`,
                        `<span class="fw-bold tokped-price">${rp(o.total_amount)}</span>`,
                        escapeHtml(o.payment_choice || '-'),
                        pl(stInfo[0], stInfo[1]),
                    ];
                }),
                'Tidak ada pesanan pada filter status ini.'
            );
        }
    } catch (err) {
        if (wrap) wrap.innerHTML = `<div class="alert alert-danger m-3 mb-0 small">${escapeHtml(err.message)}</div>`;
    }
}

const SK = () => cdTable(
    'Persediaan Stok Gerai (Data Terkini)',
    tb(
        ['Produk', 'SKU / Barcode', 'Stok Fisik', 'Reservasi', 'Tersedia', 'Status'],
        BOOTSTRAP.oltp.products.map(p => {
            const avail = Number(p.available || 0);
            const minThreshold = Math.max(Number(p.min_stock ?? p.reorder_point ?? 0), 10);
            const st = avail <= 0 ? pl('Stok Habis', 'r') : (avail <= minThreshold ? pl('Stok Kritis', 'o') : pl('Aman', 'g'));
            return [
                `<div class="d-flex align-items-center gap-2">${renderProductThumbHtml(p, 36)}<div><span class="fw-semibold text-dark">${escapeHtml(p.name)}</span><br><small class="text-muted">${escapeHtml(p.category)}</small></div></div>`,
                `<span class="font-monospace fw-bold">${escapeHtml(p.sku)}</span>${p.barcode ? `<br><small class="text-muted font-monospace">${escapeHtml(p.barcode)}</small>` : ''}`,
                `${p.on_hand} ${escapeHtml(p.unit)}`,
                `${p.reserved} ${escapeHtml(p.unit)}`,
                `<strong class="text-success">${avail} ${escapeHtml(p.unit)}</strong>`,
                st,
            ];
        }),
        'Belum ada data produk di database.'
    ),
    `<button type="button" class="btn btn-outline-primary btn-sm" onclick="openAdjustStockModal()"><i class="bi bi-sliders"></i> Penyesuaian Stok</button>
    <button type="button" class="btn btn-primary btn-sm" onclick="openReceiveStockModal()"><i class="bi bi-box-arrow-in-down"></i> Terima Stok Pemasok</button>`
);

function buildProductSelectOptions(selectedId = null) {
    return BOOTSTRAP.oltp.products.map(p => {
        const sel = selectedId && String(p.id) === String(selectedId) ? 'selected' : '';
        return `<option value="${escapeHtml(p.id)}" ${sel}>${escapeHtml(p.sku)} — ${escapeHtml(p.name)} (Stok: ${Number(p.on_hand || 0)} ${escapeHtml(p.unit)})</option>`;
    }).join('');
}

function openReceiveStockModal(preselectedProductId = null) {
    const targetProduct = preselectedProductId
        ? BOOTSTRAP.oltp.products.find(p => String(p.id) === String(preselectedProductId))
        : BOOTSTRAP.oltp.products[0];
    const defaultSupplierId = targetProduct?.supplier_id || '';
    const supOpts = '<option value="">-- Supplier Umum / Tanpa Pemasok --</option>'
        + BOOTSTRAP.oltp.suppliers.map(s => {
            const sel = defaultSupplierId && String(s.id) === String(defaultSupplierId) ? 'selected' : '';
            return `<option value="${escapeHtml(s.id)}" ${sel}>${escapeHtml(s.name)}</option>`;
        }).join('');
    const prodOpts = buildProductSelectOptions(preselectedProductId || targetProduct?.id);
    const costHint = targetProduct ? `HPP saat ini: ${rp(targetProduct.current_cost)}` : 'Kosongkan jika sama dengan HPP terakhir';

    openMgrModal('Terima Stok dari Pemasok', `
        <form id="receiveStockForm" onsubmit="submitReceiveStock(event)">
            ${fl('Produk *', `<select class="form-select" id="rcvProductId" required onchange="onRcvProductChange(this.value)">${prodOpts}</select>`)}
            ${fl('Pemasok', `<select class="form-select" id="rcvSupplierId">${supOpts}</select>`)}
            <div class="row g-2">
                <div class="col-6">${fl('Jumlah Masuk (Qty) *', '<input class="form-control" type="number" id="rcvQty" min="1" value="1" required>')}</div>
                <div class="col-6">${fl('Harga Beli / HPP Satuan Terbaru (Opsional)', `<input class="form-control" type="number" id="rcvCost" min="0" step="100" placeholder="Opsional"><small class="text-muted" id="rcvCostHint">${escapeHtml(costHint)}</small>`)}</div>
            </div>
            ${fl('Nomor Nota / Catatan Penerimaan', '<input class="form-control" id="rcvNotes" placeholder="Contoh: SJ-2026-001 / Faktur pemasok">')}
            <small class="text-muted d-block mb-3">Jika harga beli diisi, HPP dihitung ulang oleh server menggunakan Moving Weighted Average.</small>
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" onclick="closeMgrModal()">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm px-3" id="btnSubmitRcv">Simpan Penerimaan</button>
            </div>
        </form>
    `);
}

function onRcvProductChange(productId) {
    const p = BOOTSTRAP.oltp.products.find(x => String(x.id) === String(productId));
    const hint = $('#rcvCostHint');
    if (hint && p) hint.textContent = `HPP saat ini: ${rp(p.current_cost)}`;
    const supSel = $('#rcvSupplierId');
    if (supSel && p?.supplier_id) supSel.value = String(p.supplier_id);
}

async function submitReceiveStock(e) {
    e.preventDefault();
    const btn = $('#btnSubmitRcv');
    if (btn) btn.disabled = true;
    const costRaw = ($('#rcvCost')?.value ?? '').trim();
    const qtyVal = Number($('#rcvQty')?.value || 0);
    const prodId = $('#rcvProductId')?.value;
    if (!prodId || qtyVal < 1) {
        toast('Pilih produk dan masukkan jumlah masuk minimal 1.');
        if (btn) btn.disabled = false;
        return;
    }
    const itemPayload = {
        product_id: prodId,
        quantity: qtyVal,
    };
    if (costRaw !== '') itemPayload.unit_cost = Number(costRaw);

    try {
        const res = await managerApi('/api/v1/inventory/receipts', 'POST', {
            supplier_id: $('#rcvSupplierId')?.value || null,
            notes: $('#rcvNotes')?.value.trim() || null,
            items: [itemPayload],
        });
        closeMgrModal();
        toast(res.message || 'Penerimaan stok berhasil dicatat.');
        location.reload();
    } catch (err) {
        toast(err.status === 404 || err.status === 405
            ? 'Fitur pencatatan penerimaan stok belum tersedia saat ini.'
            : err.message);
    } finally {
        if (btn) btn.disabled = false;
    }
}

function openAdjustStockModal(preselectedProductId = null) {
    const targetProduct = preselectedProductId
        ? BOOTSTRAP.oltp.products.find(p => String(p.id) === String(preselectedProductId))
        : BOOTSTRAP.oltp.products[0];
    const prodOpts = buildProductSelectOptions(preselectedProductId || targetProduct?.id);
    const currentHint = targetProduct
        ? `Stok fisik saat ini: ${targetProduct.on_hand} ${targetProduct.unit} (Tersedia: ${targetProduct.available} ${targetProduct.unit})`
        : '';

    openMgrModal('Penyesuaian Stok Barang', `
        <form id="adjustStockForm" onsubmit="submitAdjustStock(event)">
            ${fl('Produk *', `<select class="form-select" id="adjProductId" required onchange="onAdjProductChange(this.value)">${prodOpts}</select><small class="text-muted d-block mt-1" id="adjStockHint">${escapeHtml(currentHint)}</small>`)}
            <div class="row g-2">
                <div class="col-6">${fl('Tipe Penyesuaian *', `
                    <select class="form-select" id="adjType" required onchange="onAdjTypeChange(this.value)">
                        <option value="ADDITION">Penambahan (+)</option>
                        <option value="DEDUCTION">Pengurangan (-)</option>
                        <option value="OPNAME">Stok Opname (Set Fisik)</option>
                    </select>`)}</div>
                <div class="col-6">${fl('<span id="adjQtyLabel">Jumlah Selisih *</span>', '<input class="form-control" type="number" id="adjQty" min="1" value="1" required>')}</div>
            </div>
            <div class="mb-2">
                <label class="form-label small fw-semibold text-secondary mb-1" for="adjReason">Alasan Penyesuaian (Wajib) *</label>
                <div class="d-flex flex-wrap gap-1 mb-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm pos-btn-compact" onclick="$('#adjReason').value='Barang rusak / cacat kemasan'">Rusak</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm pos-btn-compact" onclick="$('#adjReason').value='Barang kedaluwarsa'">Kedaluwarsa</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm pos-btn-compact" onclick="$('#adjReason').value='Selisih hasil hitung stok opname'">Selisih Opname</button>
                </div>
                <textarea class="form-control" id="adjReason" rows="2" required placeholder="Tuliskan alasan penyesuaian stok secara jelas..."></textarea>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" onclick="closeMgrModal()">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm px-3" id="btnSubmitAdj">Simpan Penyesuaian</button>
            </div>
        </form>
    `);
}

function onAdjProductChange(productId) {
    const p = BOOTSTRAP.oltp.products.find(x => String(x.id) === String(productId));
    const hint = $('#adjStockHint');
    if (hint && p) {
        hint.textContent = `Stok fisik saat ini: ${p.on_hand} ${p.unit} (Tersedia: ${p.available} ${p.unit})`;
    }
}

function onAdjTypeChange(typeVal) {
    const qtyEl = $('#adjQty');
    const lblEl = $('#adjQtyLabel');
    if (!qtyEl || !lblEl) return;
    if (typeVal === 'OPNAME') {
        lblEl.textContent = 'Jumlah Stok Fisik Baru *';
        qtyEl.min = '0';
    } else {
        lblEl.textContent = 'Jumlah Selisih *';
        qtyEl.min = '1';
        if (Number(qtyEl.value || 0) < 1) qtyEl.value = '1';
    }
}

async function submitAdjustStock(e) {
    e.preventDefault();
    const reason = ($('#adjReason')?.value || '').trim();
    const qty = Number($('#adjQty')?.value ?? -1);
    const adjType = $('#adjType')?.value || 'ADDITION';
    const prodId = $('#adjProductId')?.value;

    if (!prodId) {
        toast('Pilih produk terlebih dahulu.');
        return;
    }
    if (!reason) {
        toast('Alasan penyesuaian stok wajib diisi.');
        return;
    }
    if (Number.isNaN(qty) || (adjType === 'OPNAME' ? qty < 0 : qty < 1)) {
        toast(adjType === 'OPNAME' ? 'Stok fisik opname minimal 0.' : 'Jumlah selisih minimal 1.');
        return;
    }

    const btn = $('#btnSubmitAdj');
    if (btn) btn.disabled = true;
    try {
        const res = await managerApi('/api/v1/inventory/adjustments', 'POST', {
            product_id: prodId,
            adjustment_type: adjType,
            quantity: qty,
            reason,
        });
        closeMgrModal();
        toast(res.message || 'Penyesuaian stok berhasil dicatat.');
        location.reload();
    } catch (err) {
        toast(err.status === 404 || err.status === 405
            ? 'Fitur penyesuaian stok barang belum tersedia saat ini.'
            : err.message);
    } finally {
        if (btn) btn.disabled = false;
    }
}

/* ---------- 3. PIUTANG & KEBIJAKAN LIMIT GLOBAL (LIVE API) ---------- */
S.ar = () => `${ph(
    'Piutang & Kebijakan Kredit',
    'Pantau tagihan anggota, analisis aging piutang, dan atur limit kredit global koperasi.'
)}
<div class="row g-4 mb-4" id="arKpiGrid"></div>
<div class="row g-4 align-items-start">
    <div class="col-12 col-lg-7 d-flex flex-column gap-4">
        ${cdTable(
            'Tagihan Aktif &amp; Penunggak',
            '<div id="arReceivablesTableWrap" class="px-4 py-3 text-muted small">Memuat tagihan dari server...</div>',
            '<input class="form-control form-control-sm" id="arSearchInput" placeholder="Cari anggota / no order..." style="width:210px;" oninput="renderReceivablesTables()">'
        )}
        ${cdTable('Riwayat Perubahan Kebijakan Global', tb(
            ['Waktu', 'Limit Lama', 'Limit Baru', 'Alasan'],
            BOOTSTRAP.oltp.policy_logs.map(l => [
                escapeHtml(l.changed_at),
                l.old_limit_amount !== null ? rp(l.old_limit_amount) : '—',
                `<strong class="text-success">${rp(l.new_limit_amount)}</strong>`,
                escapeHtml(l.reason),
            ]),
            'Belum ada log perubahan kebijakan limit global.'
        ))}
        ${cd('Fasilitas Piutang Anggota', '<div id="arMemberStatusWrap"></div>', '', '')}
    </div>
    <div class="col-12 col-lg-5 d-flex flex-column gap-4">
        ${cd('Aging Piutang (5 Kelompok)', '<div id="arAgingWrap">Memuat...</div>', '', '')}
        ${cd('Kebijakan Kredit Global (Semua Anggota)', `
            <form id="creditPolicyForm" onsubmit="submitCreditPolicy(event)">
                ${fl('Limit Global Seragam (Rp)', '<input type="number" class="form-control fw-bold" id="inputGlobalLimit" min="0" step="1000" required placeholder="Masukkan nominal >= 0">')}
                ${fl('Masa Jatuh Tempo', '<input class="form-control bg-light text-muted" id="policyDueDays" value="7 hari kalender sejak serah terima" disabled>')}
                ${fl('Alasan Perubahan (Audit Log)', '<input class="form-control" id="policyReason" placeholder="Contoh: Penyesuaian rapat pengurus koperasi">')}
                <small class="text-muted d-block mb-3">Satu nilai untuk seluruh anggota. Tanpa bunga/denda otomatis. Menurunkan limit tidak mengubah tagihan lama.</small>
                <button type="submit" class="btn btn-primary w-100" id="btnSavePolicy">Simpan Kebijakan Limit</button>
            </form>
        `, '', '')}
    </div>
</div>`;

H.ar = () => loadReceivablesAndPolicy();

async function loadReceivablesAndPolicy() {
    renderMemberStatusToggles();
    try {
        const [policyRes, recvRes, agingRes] = await Promise.allSettled([
            managerApi('/api/v1/credit-policy'),
            managerApi('/api/v1/manager/receivables'),
            managerApi('/api/v1/manager/receivables/aging'),
        ]);

        if (policyRes.status === 'fulfilled') {
            state.creditPolicy = policyRes.value.data || null;
            const limitInput = $('#inputGlobalLimit');
            const dueInput = $('#policyDueDays');
            if (limitInput && state.creditPolicy) {
                limitInput.value = Number(state.creditPolicy.limit_amount ?? 0);
            }
            if (dueInput && state.creditPolicy) {
                dueInput.value = `${state.creditPolicy.due_days ?? 7} hari kalender sejak serah terima`;
            }
        }

        if (recvRes.status === 'fulfilled') {
            state.receivables = Array.isArray(recvRes.value.data) ? recvRes.value.data : [];
        }
        state.agingSummary = agingRes.status === 'fulfilled' ? (agingRes.value.data || agingRes.value) : null;

        renderArKpis();
        renderReceivablesTables();
        const agingWrap = $('#arAgingWrap');
        if (agingWrap) agingWrap.innerHTML = renderAgingBarsHtml(state.receivables);
    } catch (err) {
        toast(err.message);
    }
}

function renderArKpis() {
    const grid = $('#arKpiGrid');
    if (!grid) return;
    const rows = state.receivables || [];
    const activeRows = rows.filter(r => r.effective_status !== 'PAID' && Number(r.outstanding_amount || 0) > 0);
    const overdueRows = rows.filter(r => r.effective_status === 'OVERDUE');
    const totalOpen = activeRows.reduce((s, r) => s + Number(r.outstanding_amount || 0), 0);
    const totalOverdue = overdueRows.reduce((s, r) => s + Number(r.outstanding_amount || 0), 0);
    const overdueMemberCount = new Set(overdueRows.map(r => r.member_id)).size;

    const cards = [
        ['Total Piutang Terbuka', rp(totalOpen), `${activeRows.length} faktur belum lunas`, 'bi-receipt-cutoff', '#ecfdf5', '#059669', 'text-dark'],
        ['Piutang Jatuh Tempo', rp(totalOverdue), `${overdueMemberCount} anggota menunggak`, 'bi-exclamation-triangle', '#FFEAEA', '#EF144A', 'text-danger'],
        ['Limit Global Aktif', state.creditPolicy ? rp(state.creditPolicy.limit_amount) : 'Belum diatur', 'Seragam semua anggota', 'bi-sliders', '#f3f4f6', '#4b5563', 'text-dark'],
        ['Jatuh Tempo Baku', `${state.creditPolicy ? (state.creditPolicy.due_days ?? 7) : 7} Hari`, 'Kalender sejak serah terima', 'bi-calendar-check', '#ecfdf5', '#059669', 'text-success'],
    ];
    grid.innerHTML = cards.map(c => `<div class="col-6 col-md-3">${statCardHtml(c[0], c[1], c[2], c[3], c[4], c[5], c[6])}</div>`).join('');
}

function renderReceivablesTables() {
    const wrap = $('#arReceivablesTableWrap');
    if (!wrap) return;
    const q = ($('#arSearchInput')?.value || '').trim().toLowerCase();
    const filtered = (state.receivables || []).filter(r => {
        if (!q) return true;
        const hay = `${r.order?.order_no || ''} ${r.member?.full_name || ''} ${r.member?.employee_number || ''} ${r.member?.member_number || ''} ${r.effective_status || ''}`.toLowerCase();
        return hay.includes(q);
    });

    const stBadge = st => ({
        OVERDUE: pl('OVERDUE', 'r'),
        PARTIALLY_PAID: pl('PARTIALLY_PAID', 'pl'),
        OPEN: pl('OPEN', 'o'),
        PAID: pl('PAID', 'g'),
    }[st] || pl(st || '-', 'i'));

    wrap.className = '';
    wrap.innerHTML = tb(
        ['Order', 'Anggota', 'Jatuh Tempo', 'Awal', 'Sisa', 'Status'],
        filtered.map(r => {
            const memberLabel = r.member?.full_name
                ? `<span class="fw-semibold">${escapeHtml(r.member.full_name)}</span><br><small class="text-muted font-monospace">${escapeHtml(r.member.employee_number || r.member.member_number || '')}</small>`
                : escapeHtml(r.member_id || '-');
            return [
                `<span class="fw-bold font-monospace">${escapeHtml(r.order?.order_no || r.order_id || '-')}</span>`,
                memberLabel,
                escapeHtml(String(r.due_date || '-').slice(0, 10)),
                rp(r.original_amount),
                `<strong class="tokped-price">${rp(r.outstanding_amount)}</strong>`,
                stBadge(r.effective_status),
            ];
        }),
        'Belum ada tagihan piutang tercatat di server.'
    );
}

async function submitCreditPolicy(e) {
    e.preventDefault();
    const btn = $('#btnSavePolicy');
    const limitVal = Number($('#inputGlobalLimit')?.value);
    const reasonVal = $('#policyReason')?.value.trim() || null;

    if (Number.isNaN(limitVal) || limitVal < 0) {
        toast('Nominal limit global harus >= 0.');
        return;
    }

    if (btn) btn.disabled = true;
    try {
        const res = await managerApi('/api/v1/credit-policy', 'PATCH', {
            limit_amount: limitVal,
            active: true,
            reason: reasonVal,
        });
        toast(res.message || 'Kebijakan limit global berhasil disimpan.');
        await loadReceivablesAndPolicy();
    } catch (err) {
        toast(err.message);
    } finally {
        if (btn) btn.disabled = false;
    }
}

function renderMemberStatusToggles() {
    const wrap = $('#arMemberStatusWrap');
    if (!wrap) return;
    const activeMembers = BOOTSTRAP.oltp.members.filter(m => m.status === 'ACTIVE');
    if (!activeMembers.length) {
        wrap.innerHTML = '<small class="text-muted">Belum ada data anggota aktif.</small>';
        return;
    }
    wrap.innerHTML = activeMembers.map(m => {
        const isOn = m.credit_status === 'ACTIVE';
        const code = m.employee_number || m.member_number || m.id.slice(-6).toUpperCase();
        return `
            <div class="d-flex justify-content-between align-items-center py-2 border-bottom gap-2">
                <div class="min-w-0">
                    <div class="fw-semibold text-dark small text-truncate">${escapeHtml(m.full_name)}</div>
                    <small class="text-muted font-monospace" style="font-size:0.72rem;">${escapeHtml(code)} &bull; ${escapeHtml(m.department)}</small>
                </div>
                <input type="checkbox" class="mgr-switch" ${isOn ? 'checked' : ''} aria-label="Status piutang ${escapeHtml(m.full_name)}" onchange="toggleMemberCreditStatus('${escapeHtml(m.id)}', this)">
            </div>
        `;
    }).join('');
}

async function toggleMemberCreditStatus(memberId, checkboxEl) {
    const nextStatus = checkboxEl.checked ? 'ACTIVE' : 'BLOCKED';
    checkboxEl.disabled = true;
    try {
        const res = await managerApi(`/api/v1/members/${encodeURIComponent(memberId)}/credit-status`, 'PATCH', {
            status: nextStatus,
        });
        const memberObj = BOOTSTRAP.oltp.members.find(x => x.id === memberId);
        if (memberObj) memberObj.credit_status = nextStatus;
        toast(res.message || `Status fasilitas anggota diubah menjadi ${nextStatus}.`);
        await loadReceivablesAndPolicy();
    } catch (err) {
        checkboxEl.checked = !checkboxEl.checked;
        toast(err.message);
    } finally {
        checkboxEl.disabled = false;
    }
}

/* ---------- 4. MASTER DATA & IMPOR EXCEL ---------- */
S.data = () => `${ph('Master Data & Impor', 'Kelola master produk, anggota, pemasok, dan impor data Excel/CSV.')}${renderSubTabShell('data', ['Produk & Harga', 'Anggota', 'Pemasok', 'Impor Excel'], [DP, DM, DV, DI])}`;

H.data = () => {
    if (TB.data === 3) renderImportWizard();
};

function renderProductThumbHtml(p, size = 40) {
    const dim = `width:${size}px;height:${size}px;flex-shrink:0;`;
    const iconCls = size >= 64 ? 'fs-4' : 'small';
    const placeholder = `<div class="rounded-2 border bg-light text-muted d-inline-flex align-items-center justify-content-center" style="${dim}"><i class="bi bi-image ${iconCls}" aria-hidden="true"></i></div>`;
    if (!p?.image_url) return placeholder;
    return `<div class="d-inline-flex align-items-center justify-content-center flex-shrink-0" style="${dim}"><img src="${escapeHtml(p.image_url)}" alt="${escapeHtml(p.name || 'Produk')}" class="rounded-2 border w-100 h-100" style="object-fit:cover;" onerror="this.classList.add('d-none');this.nextElementSibling.classList.remove('d-none');"><div class="d-none rounded-2 border bg-light text-muted w-100 h-100 d-flex align-items-center justify-content-center"><i class="bi bi-image ${iconCls}" aria-hidden="true"></i></div></div>`;
}

const DP = () => cdTable(
    'Master Produk, Stok &amp; Harga Gerai',
    tb(
        ['Foto', 'Produk', 'Barcode', 'Kategori', 'HPP Acuan', 'Harga Jual', 'Stok Tersedia', 'Min. Stok', 'Status', 'Aksi'],
        BOOTSTRAP.oltp.products.map(p => {
            const st = !p.is_active
                ? pl('Nonaktif', 'r')
                : (p.available <= 0 ? pl('Stok Habis', 'r') : pl('Aktif', 'g'));
            const pid = escapeHtml(p.id);
            return [
                renderProductThumbHtml(p, 40),
                `<span class="fw-semibold text-dark">${escapeHtml(p.name)}</span><br><small class="text-muted font-monospace">${escapeHtml(p.sku)}</small>`,
                `<span class="font-monospace">${escapeHtml(p.barcode || '—')}</span>`,
                escapeHtml(p.category),
                rp(p.current_cost),
                `<strong class="tokped-price">${rp(p.selling_price)}</strong>`,
                `${p.available} ${escapeHtml(p.unit)}`,
                `${Number(p.min_stock ?? p.reorder_point ?? 0)} ${escapeHtml(p.unit)}`,
                st,
                renderActionDropdown(`
                    <li><button type="button" class="dropdown-item d-flex align-items-center gap-2 small" onclick="openEditProductModal('${pid}')"><i class="bi bi-pencil-square text-primary"></i><span>Edit Produk</span></button></li>
                    <li><button type="button" class="dropdown-item d-flex align-items-center gap-2 small" onclick="openReceiveStockModal('${pid}')"><i class="bi bi-box-arrow-in-down text-success"></i><span>Terima Stok</span></button></li>
                    <li><button type="button" class="dropdown-item d-flex align-items-center gap-2 small" onclick="openAdjustStockModal('${pid}')"><i class="bi bi-sliders text-secondary"></i><span>Penyesuaian Stok</span></button></li>
                `, `Aksi ${p.name}`),
            ];
        }),
        'Belum ada data produk di database.'
    ),
    `<div class="d-flex flex-wrap align-items-center gap-2"><a class="btn btn-outline-success btn-sm d-inline-flex align-items-center gap-1" href="/manager/excel/export?type=PRODUK&format=xlsx"><i class="bi bi-file-earmark-excel"></i><span>Ekspor Excel</span></a><a class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1" href="/manager/excel/export?type=PRODUK&format=csv"><i class="bi bi-filetype-csv"></i><span>CSV</span></a><button type="button" class="btn btn-primary btn-sm" onclick="openAddProductModal()"><i class="bi bi-plus-lg"></i> Tambah Produk</button></div>`
);

let productPreviewObjectUrl = null;

function openAddProductModal() {
    openProductFormModal(null);
}

function openEditProductModal(productId) {
    openProductFormModal(productId);
}

function openProductFormModal(productId = null) {
    const p = productId ? BOOTSTRAP.oltp.products.find(x => String(x.id) === String(productId)) : null;
    const isEdit = Boolean(p);
    if (productPreviewObjectUrl) {
        URL.revokeObjectURL(productPreviewObjectUrl);
        productPreviewObjectUrl = null;
    }

    const categories = BOOTSTRAP.oltp.categories || [];
    const matchedCatId = p?.category_id
        || categories.find(c => String(c.name).toLowerCase() === String(p?.category || '').toLowerCase())?.id
        || '';
    const catOpts = categories.length
        ? categories.map(c => `<option value="${escapeHtml(c.id)}" data-name="${escapeHtml(c.name)}" ${String(c.id) === String(matchedCatId) ? 'selected' : ''}>${escapeHtml(c.name)}</option>`).join('')
        : ['Sembako', 'Minyak & Gula', 'Minuman', 'Kebutuhan Harian', 'Umum'].map(name => `<option value="" data-name="${escapeHtml(name)}" ${String(p?.category || 'Sembako') === name ? 'selected' : ''}>${escapeHtml(name)}</option>`).join('');

    const supOpts = '<option value="">-- Pilih Pemasok (Opsional) --</option>'
        + BOOTSTRAP.oltp.suppliers.map(s => `<option value="${escapeHtml(s.id)}" ${p?.supplier_id && String(s.id) === String(p.supplier_id) ? 'selected' : ''}>${escapeHtml(s.name)}</option>`).join('');

    const presetUnits = ['Pcs', 'Kg', 'Liter', 'Dus', 'Bks', 'Sak', 'Botol', 'Pack'];
    const currentUnit = p?.unit || 'Pcs';
    if (!presetUnits.includes(currentUnit)) presetUnits.push(currentUnit);
    const unitOpts = presetUnits.map(u => `<option value="${escapeHtml(u)}" ${u === currentUnit ? 'selected' : ''}>${escapeHtml(u)}</option>`).join('');

    const currentStatus = !p || p.is_active ? 'ACTIVE' : 'INACTIVE';
    const previewInner = p?.image_url
        ? `<img src="${escapeHtml(p.image_url)}" alt="${escapeHtml(p.name)}" class="w-100 h-100" style="object-fit:cover;" onerror="this.classList.add('d-none');this.nextElementSibling.classList.remove('d-none');"><i class="d-none bi bi-image fs-3 text-muted" aria-hidden="true"></i>`
        : '<i class="bi bi-image fs-3 text-muted" aria-hidden="true"></i>';

    openMgrModal(isEdit ? `Edit Produk — ${p.name}` : 'Tambah Produk Master', `
        <form id="productMasterForm" onsubmit="submitProductForm(event, ${isEdit ? `'${escapeHtml(p.id)}'` : 'null'})">
            <div class="d-flex align-items-center gap-3 p-2.5 bg-light border rounded-3 mb-3">
                <div id="pfImagePreview" class="rounded-2 border bg-white d-flex align-items-center justify-content-center overflow-hidden flex-shrink-0" style="width:76px;height:76px;">
                    ${previewInner}
                </div>
                <div class="flex-grow-1 min-w-0">
                    <label class="form-label small fw-semibold text-secondary mb-1" for="pfImage">Foto Barang (JPG, PNG, WebP)</label>
                    <input type="file" class="form-control form-control-sm" id="pfImage" accept="image/jpeg,image/png,image/webp" onchange="previewProductImage(this)">
                    <small class="text-muted d-block mt-1" style="font-size:0.72rem;">${isEdit ? 'Unggah gambar baru jika ingin mengganti foto eksisting.' : 'Opsional · Maksimal ukuran file mengikuti aturan server.'}</small>
                </div>
            </div>
            <div class="row g-2">
                <div class="col-6">${fl('Kode SKU *', `<input class="form-control" id="pfSku" required value="${escapeHtml(p?.sku || '')}" placeholder="Contoh: SEM-001">`)}</div>
                <div class="col-6">${fl('Barcode (Opsional)', `<input class="form-control" id="pfBarcode" value="${escapeHtml(p?.barcode || '')}" placeholder="EAN-13 / Code128">`)}</div>
            </div>
            ${fl('Nama Produk *', `<input class="form-control" id="pfName" required value="${escapeHtml(p?.name || '')}" placeholder="Contoh: Beras Premium 5Kg">`)}
            <div class="row g-2">
                <div class="col-6">${fl('Kategori *', `<select class="form-select" id="pfCategory" required>${catOpts}</select>`)}</div>
                <div class="col-6">${fl('Pemasok Utama', `<select class="form-select" id="pfSupplier">${supOpts}</select>`)}</div>
            </div>
            <div class="row g-2">
                <div class="col-6">${fl('Satuan *', `<select class="form-select" id="pfUnit" required>${unitOpts}</select>`)}</div>
                <div class="col-6">${fl('Status Produk *', `
                    <select class="form-select" id="pfStatus" required>
                        <option value="ACTIVE" ${currentStatus === 'ACTIVE' ? 'selected' : ''}>ACTIVE (Aktif di Etalase)</option>
                        <option value="INACTIVE" ${currentStatus === 'INACTIVE' ? 'selected' : ''}>INACTIVE (Nonaktif)</option>
                    </select>`)}</div>
            </div>
            <div class="row g-2">
                <div class="col-6">${fl('Harga Modal / HPP (Rp) *', `<input type="number" class="form-control" id="pfCost" min="0" step="1" required value="${isEdit ? Number(p.current_cost || 0) : ''}">`)}</div>
                <div class="col-6">${fl('Harga Jual (Rp) *', `<input type="number" class="form-control" id="pfPrice" min="0" step="1" required value="${isEdit ? Number(p.selling_price || 0) : ''}">`)}</div>
            </div>
            <div class="row g-2">
                ${!isEdit ? `<div class="col-6">${fl('Stok Awal *', '<input type="number" class="form-control" id="pfInitialStock" min="0" value="0" required>')}</div>` : ''}
                <div class="${!isEdit ? 'col-6' : 'col-12'}">${fl('Batas Stok Minimum *', `<input type="number" class="form-control" id="pfMinStock" min="0" required value="${isEdit ? Number(p.min_stock ?? p.reorder_point ?? 10) : 10}">`)}</div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-2">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" onclick="closeMgrModal()">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm px-3" id="btnSaveProd">${isEdit ? 'Simpan Perubahan' : 'Simpan Produk'}</button>
            </div>
        </form>
    `);
}

function previewProductImage(input) {
    const box = $('#pfImagePreview');
    if (!box) return;
    if (productPreviewObjectUrl) {
        URL.revokeObjectURL(productPreviewObjectUrl);
        productPreviewObjectUrl = null;
    }
    const file = input?.files?.[0];
    if (!file) return;
    productPreviewObjectUrl = URL.createObjectURL(file);
    box.innerHTML = `<img src="${productPreviewObjectUrl}" alt="Preview Foto Produk" class="w-100 h-100" style="object-fit:cover;">`;
}

async function submitProductForm(e, productId = null) {
    e.preventDefault();
    const isEdit = Boolean(productId);
    const btn = $('#btnSaveProd');
    if (btn) btn.disabled = true;

    const fd = new FormData();
    if (isEdit) fd.append('_method', 'PATCH');
    fd.append('sku', ($('#pfSku')?.value || '').trim());
    const barcode = ($('#pfBarcode')?.value || '').trim();
    if (barcode !== '') fd.append('barcode', barcode);
    fd.append('name', ($('#pfName')?.value || '').trim());

    const catEl = $('#pfCategory');
    const catId = catEl?.value || '';
    const catName = catEl?.selectedOptions?.[0]?.dataset?.name || catEl?.selectedOptions?.[0]?.textContent || 'Umum';
    if (catId !== '') fd.append('category_id', catId);
    fd.append('category', catName);

    const supId = $('#pfSupplier')?.value || '';
    if (supId !== '') fd.append('supplier_id', supId);

    fd.append('unit', ($('#pfUnit')?.value || 'Pcs').trim());
    const statusVal = $('#pfStatus')?.value || 'ACTIVE';
    fd.append('status', statusVal);
    fd.append('is_active', statusVal === 'ACTIVE' ? '1' : '0');

    const costVal = String(Number($('#pfCost')?.value || 0));
    const priceVal = String(Number($('#pfPrice')?.value || 0));
    const minStockVal = String(Number($('#pfMinStock')?.value || 0));
    fd.append('current_cost', costVal);
    fd.append('cost_price', costVal);
    fd.append('selling_price', priceVal);
    fd.append('price', priceVal);
    fd.append('min_stock', minStockVal);
    fd.append('reorder_point', minStockVal);

    if (!isEdit) {
        fd.append('initial_stock', String(Number($('#pfInitialStock')?.value || 0)));
    }

    const imgFile = $('#pfImage')?.files?.[0];
    if (imgFile) {
        fd.append('image', imgFile);
    }

    const endpoint = isEdit ? `/api/v1/products/${encodeURIComponent(productId)}` : '/api/v1/products';
    try {
        const res = await managerApi(endpoint, 'POST', fd);
        closeMgrModal();
        toast(res.message || (isEdit ? 'Data produk berhasil diperbarui.' : 'Produk baru berhasil disimpan.'));
        location.reload();
    } catch (err) {
        toast(err.status === 404 || err.status === 405
            ? 'Fitur penyimpanan data master produk belum tersedia saat ini.'
            : err.message);
    } finally {
        if (btn) btn.disabled = false;
    }
}

const DM = () => cdTable(
    'Daftar Anggota &amp; Peran Akses',
    tb(
        ['Nama Anggota', 'NIM / NIP', 'Jurusan', 'Role', 'Keanggotaan', 'Piutang', 'Aksi'],
        BOOTSTRAP.oltp.members.map(m => {
            const mid = escapeHtml(m.id);
            const isMemberActive = String(m.status || 'ACTIVE').toUpperCase() === 'ACTIVE';
            return [
                `<span class="fw-semibold text-dark">${escapeHtml(m.full_name)}</span>${m.email ? `<br><small class="text-muted">${escapeHtml(m.email)}</small>` : ''}`,
                `<span class="font-monospace">${escapeHtml(m.employee_number || m.member_number || '-')}</span>`,
                escapeHtml(m.department),
                (m.roles || []).map(r => pl(r, r === 'MANAGER' ? 'r' : (r === 'CASHIER' ? 'pl' : 'i'))).join(' '),
                pl(m.status, isMemberActive ? 'g' : 'r'),
                pl(m.credit_status, m.credit_status === 'ACTIVE' ? 'g' : 'o'),
                renderActionDropdown(`
                    <li><button type="button" class="dropdown-item d-flex align-items-center gap-2 small" onclick="openEditMemberModal('${mid}')"><i class="bi bi-pencil-square text-primary"></i><span>Edit Anggota</span></button></li>
                    <li><button type="button" class="dropdown-item d-flex align-items-center gap-2 small ${isMemberActive ? 'text-danger' : 'text-success'}" onclick="toggleMemberAccountStatus('${mid}')"><i class="bi ${isMemberActive ? 'bi-person-slash' : 'bi-person-check'}"></i><span>${isMemberActive ? 'Nonaktifkan Anggota' : 'Aktifkan Anggota'}</span></button></li>
                `, `Aksi ${m.full_name}`),
            ];
        }),
        'Belum ada data anggota terdaftar.'
    ),
    `<div class="d-flex flex-wrap align-items-center gap-2"><a class="btn btn-outline-success btn-sm d-inline-flex align-items-center gap-1" href="/manager/excel/export?type=ANGGOTA&format=xlsx"><i class="bi bi-file-earmark-excel"></i><span>Ekspor Excel</span></a><a class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1" href="/manager/excel/export?type=ANGGOTA&format=csv"><i class="bi bi-filetype-csv"></i><span>CSV</span></a><button type="button" class="btn btn-primary btn-sm" onclick="openAddMemberModal()"><i class="bi bi-plus-lg"></i> Tambah Anggota</button></div>`
);

function openAddMemberModal() {
    openMemberFormModal(null);
}

function openEditMemberModal(memberId) {
    openMemberFormModal(memberId);
}

function openMemberFormModal(memberId = null) {
    const m = memberId ? BOOTSTRAP.oltp.members.find(x => String(x.id) === String(memberId)) : null;
    const isEdit = Boolean(m);
    const currentCode = m ? (m.employee_number || m.member_number || '') : '';
    const primaryRole = Array.isArray(m?.roles) && m.roles.length ? String(m.roles[0]).toUpperCase() : 'MEMBER';
    const currentStatus = String(m?.status || 'ACTIVE').toUpperCase() === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE';

    openMgrModal(isEdit ? `Edit Anggota — ${m.full_name}` : 'Tambah Anggota Koperasi', `
        <form id="memberMasterForm" onsubmit="submitMemberForm(event, ${isEdit ? `'${escapeHtml(m.id)}'` : 'null'})">
            ${fl('Nama Lengkap *', `<input class="form-control" id="mfFullName" required value="${escapeHtml(m?.full_name || '')}" placeholder="Nama lengkap pegawai / anggota">`)}
            <div class="row g-2">
                <div class="col-12 col-md-6">${fl('Email *', `<input type="email" class="form-control" id="mfEmail" required value="${escapeHtml(m?.email || '')}" placeholder="nama@polman-bandung.ac.id">`)}</div>
                <div class="col-12 col-md-6">${fl('NIM / NIP *', `<input class="form-control" id="mfCode" required value="${escapeHtml(currentCode)}" placeholder="Contoh: 224443075 atau 198501012010011001">`)}</div>
            </div>
            ${fl('Jurusan *', `<input class="form-control" id="mfDepartment" required value="${escapeHtml(m?.department || '')}" placeholder="Contoh: Teknik Mesin / Teknik Otomasi Manufaktur">`)}
            <div class="row g-2">
                <div class="col-6">${fl('Peran Akun *', `
                    <select class="form-select" id="mfRole" required>
                        <option value="MEMBER" ${primaryRole === 'MEMBER' ? 'selected' : ''}>MEMBER (Anggota)</option>
                        <option value="CASHIER" ${primaryRole === 'CASHIER' ? 'selected' : ''}>CASHIER (Kasir)</option>
                        <option value="MANAGER" ${primaryRole === 'MANAGER' ? 'selected' : ''}>MANAGER (Manajer)</option>
                    </select>`)}</div>
                <div class="col-6">${fl('Status Keanggotaan *', `
                    <select class="form-select" id="mfStatus" required>
                        <option value="ACTIVE" ${currentStatus === 'ACTIVE' ? 'selected' : ''}>ACTIVE (Aktif)</option>
                        <option value="INACTIVE" ${currentStatus === 'INACTIVE' ? 'selected' : ''}>INACTIVE (Nonaktif)</option>
                    </select>`)}</div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-2">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" onclick="closeMgrModal()">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm px-3" id="btnSaveMember">${isEdit ? 'Simpan Perubahan' : 'Simpan Anggota'}</button>
            </div>
        </form>
    `);
}

async function submitMemberForm(e, memberId = null) {
    e.preventDefault();
    const isEdit = Boolean(memberId);
    const btn = $('#btnSaveMember');
    if (btn) btn.disabled = true;

    const existing = isEdit ? BOOTSTRAP.oltp.members.find(x => String(x.id) === String(memberId)) : null;
    const codeVal = ($('#mfCode')?.value || '').trim();
    const upperCode = codeVal.toUpperCase();
    const roleVal = $('#mfRole')?.value || 'MEMBER';

    const payload = {
        full_name: ($('#mfFullName')?.value || '').trim(),
        email: ($('#mfEmail')?.value || '').trim(),
        employee_number: upperCode.startsWith('MEM-') ? (existing?.employee_number || null) : codeVal,
        member_number: upperCode.startsWith('EMP-') ? (existing?.member_number || null) : codeVal,
        department: ($('#mfDepartment')?.value || '').trim(),
        role: roleVal,
        roles: [roleVal],
        status: $('#mfStatus')?.value || 'ACTIVE',
    };

    const endpoint = isEdit ? `/api/v1/members/${encodeURIComponent(memberId)}` : '/api/v1/members';
    const method = isEdit ? 'PATCH' : 'POST';

    try {
        const res = await managerApi(endpoint, method, payload);
        closeMgrModal();
        toast(res.message || (isEdit ? 'Data anggota berhasil diperbarui.' : 'Anggota baru berhasil ditambahkan.'));
        location.reload();
    } catch (err) {
        toast(err.status === 404 || err.status === 405
            ? 'Fitur pengelolaan data anggota belum tersedia saat ini.'
            : err.message);
    } finally {
        if (btn) btn.disabled = false;
    }
}

async function toggleMemberAccountStatus(memberId) {
    const m = BOOTSTRAP.oltp.members.find(x => String(x.id) === String(memberId));
    if (!m) return;
    const isCurrentlyActive = String(m.status || 'ACTIVE').toUpperCase() === 'ACTIVE';
    const nextStatus = isCurrentlyActive ? 'INACTIVE' : 'ACTIVE';

    try {
        const res = await managerApi(`/api/v1/members/${encodeURIComponent(memberId)}`, 'PATCH', {
            status: nextStatus,
        });
        m.status = nextStatus;
        toast(res.message || `Status keanggotaan ${m.full_name} diubah menjadi ${nextStatus}.`);
        route();
    } catch (err) {
        toast(err.status === 404 || err.status === 405
            ? 'Fitur perubahan status keanggotaan belum tersedia saat ini.'
            : err.message);
    }
}

const DV = () => cdTable(
    'Daftar Pemasok',
    tb(
        ['Kode', 'Nama Pemasok', 'Kontak', 'Alamat', 'Lead Time', 'Status', 'Aksi'],
        BOOTSTRAP.oltp.suppliers.map(s => {
            const sid = escapeHtml(s.id);
            const isSupActive = Boolean(s.is_active);
            return [
                `<span class="font-monospace">${escapeHtml(s.code || '-')}</span>`,
                `<span class="fw-semibold text-dark">${escapeHtml(s.name)}</span>`,
                escapeHtml(s.phone || '-'),
                `<span class="text-muted small d-inline-block text-truncate" style="max-width:220px;" title="${escapeHtml(s.address || '-')}">${escapeHtml(s.address || '-')}</span>`,
                `${s.lead_time_days ?? 1} hari`,
                pl(isSupActive ? 'Aktif' : 'Nonaktif', isSupActive ? 'g' : 'r'),
                renderActionDropdown(`
                    <li><button type="button" class="dropdown-item d-flex align-items-center gap-2 small" onclick="openEditSupplierModal('${sid}')"><i class="bi bi-pencil-square text-primary"></i><span>Edit Pemasok</span></button></li>
                    <li><button type="button" class="dropdown-item d-flex align-items-center gap-2 small ${isSupActive ? 'text-danger' : 'text-success'}" onclick="toggleSupplierStatus('${sid}')"><i class="bi ${isSupActive ? 'bi-slash-circle' : 'bi-check-circle'}"></i><span>${isSupActive ? 'Nonaktifkan Pemasok' : 'Aktifkan Pemasok'}</span></button></li>
                `, `Aksi ${s.name}`),
            ];
        }),
        'Belum ada data pemasok tersimpan di tabel suppliers.'
    ),
    `<div class="d-flex flex-wrap align-items-center gap-2"><a class="btn btn-outline-success btn-sm d-inline-flex align-items-center gap-1" href="/manager/excel/export?type=PEMASOK&format=xlsx"><i class="bi bi-file-earmark-excel"></i><span>Ekspor Excel</span></a><a class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1" href="/manager/excel/export?type=PEMASOK&format=csv"><i class="bi bi-filetype-csv"></i><span>CSV</span></a><button type="button" class="btn btn-primary btn-sm" onclick="openAddSupplierModal()"><i class="bi bi-plus-lg"></i> Tambah Pemasok</button></div>`
);

function openAddSupplierModal() {
    openSupplierFormModal(null);
}

function openEditSupplierModal(supplierId) {
    openSupplierFormModal(supplierId);
}

function openSupplierFormModal(supplierId = null) {
    const s = supplierId ? BOOTSTRAP.oltp.suppliers.find(x => String(x.id) === String(supplierId)) : null;
    const isEdit = Boolean(s);
    const currentStatus = !s || s.is_active ? 'ACTIVE' : 'INACTIVE';

    openMgrModal(isEdit ? `Edit Pemasok — ${s.name}` : 'Tambah Pemasok Baru', `
        <form id="supplierMasterForm" onsubmit="submitSupplierForm(event, ${isEdit ? `'${escapeHtml(s.id)}'` : 'null'})">
            <div class="row g-2">
                <div class="col-5">${fl('Kode Pemasok *', `<input class="form-control" id="sfCode" required value="${escapeHtml(s?.code || '')}" placeholder="Contoh: SUP-001">`)}</div>
                <div class="col-7">${fl('Nama Pemasok / Perusahaan *', `<input class="form-control" id="sfName" required value="${escapeHtml(s?.name || '')}" placeholder="Contoh: PT Sumber Makmur">`)}</div>
            </div>
            <div class="row g-2">
                <div class="col-6">${fl('Kontak / No. HP', `<input class="form-control" id="sfPhone" value="${escapeHtml(s?.phone || '')}" placeholder="08xxxxxxxxxx">`)}</div>
                <div class="col-6">${fl('Status *', `
                    <select class="form-select" id="sfStatus" required>
                        <option value="ACTIVE" ${currentStatus === 'ACTIVE' ? 'selected' : ''}>ACTIVE (Aktif)</option>
                        <option value="INACTIVE" ${currentStatus === 'INACTIVE' ? 'selected' : ''}>INACTIVE (Nonaktif)</option>
                    </select>`)}</div>
            </div>
            ${fl('Alamat Pemasok', `<textarea class="form-control" id="sfAddress" rows="2" placeholder="Alamat lengkap gudang / kantor pemasok">${escapeHtml(s?.address || '')}</textarea>`)}
            <div class="d-flex justify-content-end gap-2 mt-2">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" onclick="closeMgrModal()">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm px-3" id="btnSaveSupplier">${isEdit ? 'Simpan Perubahan' : 'Simpan Pemasok'}</button>
            </div>
        </form>
    `);
}

async function submitSupplierForm(e, supplierId = null) {
    e.preventDefault();
    const isEdit = Boolean(supplierId);
    const btn = $('#btnSaveSupplier');
    if (btn) btn.disabled = true;

    const statusVal = $('#sfStatus')?.value || 'ACTIVE';
    const payload = {
        code: ($('#sfCode')?.value || '').trim(),
        name: ($('#sfName')?.value || '').trim(),
        phone: ($('#sfPhone')?.value || '').trim() || null,
        address: ($('#sfAddress')?.value || '').trim() || null,
        status: statusVal,
        is_active: statusVal === 'ACTIVE',
    };

    const endpoint = isEdit ? `/api/v1/suppliers/${encodeURIComponent(supplierId)}` : '/api/v1/suppliers';
    const method = isEdit ? 'PATCH' : 'POST';

    try {
        const res = await managerApi(endpoint, method, payload);
        closeMgrModal();
        toast(res.message || (isEdit ? 'Data pemasok berhasil diperbarui.' : 'Pemasok baru berhasil disimpan.'));
        location.reload();
    } catch (err) {
        toast(err.status === 404 || err.status === 405
            ? 'Fitur pengelolaan data pemasok belum tersedia saat ini.'
            : err.message);
    } finally {
        if (btn) btn.disabled = false;
    }
}

async function toggleSupplierStatus(supplierId) {
    const s = BOOTSTRAP.oltp.suppliers.find(x => String(x.id) === String(supplierId));
    if (!s) return;
    const nextActive = !Boolean(s.is_active);
    try {
        const res = await managerApi(`/api/v1/suppliers/${encodeURIComponent(supplierId)}`, 'PATCH', {
            is_active: nextActive,
            status: nextActive ? 'ACTIVE' : 'INACTIVE',
        });
        s.is_active = nextActive;
        toast(res.message || `Status pemasok ${s.name} diubah menjadi ${nextActive ? 'Aktif' : 'Nonaktif'}.`);
        route();
    } catch (err) {
        toast(err.status === 404 || err.status === 405
            ? 'Fitur perubahan status pemasok belum tersedia saat ini.'
            : err.message);
    }
}

const IMPORT_STEPS = ['Upload & Pilih Jenis', 'Preview & Validasi', 'Konfirmasi Simpan', 'Selesai'];

const DI = () => `
    <div class="mgr-stepper mb-3" id="stp"></div>
    <div id="ib" class="mb-4"></div>
    ${cdTable('Riwayat Impor Data', tb(
        ['Batch ID', 'Tipe Data', 'Nama File', 'Diterima', 'Ditolak', 'Status'],
        BOOTSTRAP.oltp.import_batches.map(b => [
            `<span class="font-monospace">${escapeHtml(String(b.id).slice(0, 8))}</span>`,
            escapeHtml(b.import_type || b.entity_type || b.data_type || '-'),
            escapeHtml(b.file_name || b.filename || b.original_filename || '-'),
            b.valid_rows ?? b.accepted_count ?? 0,
            b.error_rows ?? b.rejected_count ?? 0,
            pl(b.status || '-', b.status === 'COMMITTED' || b.status === 'COMPLETED' ? 'g' : 'o'),
        ]),
        'Belum ada riwayat impor data.'
    ))}
`;

function renderImportWizard() {
    const stp = $('#stp');
    const ib = $('#ib');
    if (!stp || !ib) return;

    stp.innerHTML = IMPORT_STEPS.map((s, i) => `<span class="mgr-step-pill ${i === state.importStep ? 'active' : (i < state.importStep ? 'done' : '')}">${escapeHtml(s)}</span>`).join('');

    if (state.importStep === 0) {
        ib.innerHTML = cd('Unggah File Excel / CSV', `
            <form id="importUploadForm" onsubmit="submitImportUpload(event)">
                <div class="row g-3">
                    <div class="col-12 col-md-6">${fl('Jenis Data', `<select class="form-select" id="impType" onchange="toggleImportCutoffField(this.value)"><option value="PRODUK">Produk &amp; Harga</option><option value="ANGGOTA">Anggota Koperasi</option><option value="PEMASOK">Pemasok / Supplier</option><option value="STOK">Stok Awal (Cut-Off)</option><option value="TRANSAKSI">Histori Transaksi</option></select>`)}</div>
                    <div class="col-12 col-md-6">${fl('File XLSX / CSV', '<input class="form-control" type="file" id="impFile" accept=".xlsx,.xls,.csv" required>')}</div>
                </div>
                <div id="impCutoffWrap" class="d-none">
                    ${fl('Tanggal Cut-Off Stok Awal (Wajib untuk Stok Awal)', '<input class="form-control" type="date" id="impCutoffDate">')}
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-2"><button type="submit" class="btn btn-primary btn-sm px-3" id="btnUploadImport"><i class="bi bi-upload me-1"></i>Unggah &amp; Validasi Preview</button><button type="button" class="btn btn-outline-success btn-sm px-3" onclick="window.location.href='/manager/excel/template?type='+encodeURIComponent($('#impType').value)+'&format=xlsx'"><i class="bi bi-file-earmark-arrow-down me-1"></i>Unduh Template (.xlsx)</button><button type="button" class="btn btn-outline-secondary btn-sm px-3" onclick="window.location.href='/manager/excel/template?type='+encodeURIComponent($('#impType').value)+'&format=csv'"><i class="bi bi-filetype-csv me-1"></i>Template (.csv)</button><button type="button" class="btn btn-outline-primary btn-sm px-3" onclick="window.location.href='/manager/excel/export?type='+encodeURIComponent($('#impType').value)+'&format=xlsx'"><i class="bi bi-box-arrow-up-right me-1"></i>Ekspor Data Saat Ini (.xlsx)</button></div>
            </form>
        `);
    } else if (state.importStep === 1) {
        const pv = state.importPreview || {};
        const errors = Array.isArray(pv.errors) ? pv.errors : [];
        const validCount = Number(pv.valid_count ?? pv.valid_rows ?? 0);
        ib.innerHTML = cd('Pratinjau &amp; Validasi Baris', `
            <div class="d-flex gap-2 mb-3">
                ${pl(`${validCount} baris valid`, 'g')}
                ${pl(`${errors.length} baris error`, errors.length ? 'r' : 'g')}
            </div>
            ${tb(['Baris', 'Identitas', 'Masalah'], errors.map(er => [er.row_number ?? '-', escapeHtml(er.identifier || er.field_name || '-'), pl(er.message || 'Error', 'r')]), 'Tidak ada error validasi.')}
            <div class="d-flex gap-2 mt-3">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="state.importStep=0;renderImportWizard()">Kembali</button>
                <button type="button" class="btn btn-primary btn-sm" ${validCount === 0 ? 'disabled' : ''} onclick="state.importStep=2;renderImportWizard()">Lanjut ke Konfirmasi</button>${errors.length && state.importBatch?.id ? '<a class="btn btn-outline-danger btn-sm" href="/manager/excel/errors/'+encodeURIComponent(state.importBatch.id)+'"><i class="bi bi-download me-1"></i>Ekspor Baris Error (.xlsx)</a>' : ''}
            </div>
        `);
    } else if (state.importStep === 2) {
        ib.innerHTML = cd('Konfirmasi Simpan Data Impor', `
            <div class="alert alert-warning small">Histori sebelum tanggal cut-off tidak mengubah stok berjalan atau membuat piutang aktif.</div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="impConfirmCheck" onchange="$('#btnCommitImp').disabled=!this.checked">
                <label class="form-check-label small fw-semibold" for="impConfirmCheck">
                    Saya telah memeriksa pratinjau validasi dan menyetujui penyimpanan data impor ini.
                </label>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="state.importStep=1;renderImportWizard()">Kembali</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnCommitImp" disabled onclick="executeImportCommit()">Simpan Data Impor</button>
            </div>
        `);
    } else {
        ib.innerHTML = cd('Impor Selesai', `
            <div class="alert alert-success small mb-3">Data impor berhasil disimpan dan aman dari duplikasi.</div>
            <div class="d-flex flex-wrap gap-2"><button type="button" class="btn btn-primary btn-sm" onclick="location.reload()"><i class="bi bi-arrow-clockwise me-1"></i>Muat Ulang Tabel Data</button><button type="button" class="btn btn-outline-primary btn-sm" onclick="state.importStep=0;renderImportWizard()">Impor Berkas Baru</button></div>
        `);
    }
}

function toggleImportCutoffField(val) {
    const wrap = $('#impCutoffWrap');
    const dateInput = $('#impCutoffDate');
    const show = val === 'STOK';
    if (wrap) wrap.classList.toggle('d-none', !show);
    if (dateInput) dateInput.required = show;
}

async function submitImportUpload(e) {
    e.preventDefault();
    const fileInput = $('#impFile');
    const typeVal = $('#impType')?.value;
    const cutoffVal = $('#impCutoffDate')?.value;
    if (!fileInput?.files?.length) {
        toast('Pilih berkas XLSX/CSV terlebih dahulu.');
        return;
    }
    if (typeVal === 'STOK' && !cutoffVal) {
        toast('Tanggal cut-off wajib diisi untuk impor stok awal.');
        return;
    }

    const btn = $('#btnUploadImport');
    if (btn) btn.disabled = true;
    try {
        const fd = new FormData();
        fd.append('file', fileInput.files[0]);
        fd.append('type', typeVal);
        if (cutoffVal) fd.append('cut_off_date', cutoffVal);

        const created = await managerApi('/api/v1/imports', 'POST', fd);
        state.importBatch = created.data || created;
        const batchId = state.importBatch?.id;
        if (batchId) {
            const previewRes = await managerApi(`/api/v1/imports/${encodeURIComponent(batchId)}/preview`);
            state.importPreview = previewRes.data || previewRes;
        }
        state.importStep = 1;
        renderImportWizard();
    } catch (err) {
        toast(err.status === 404 || err.status === 405
            ? 'Layanan unggah dan validasi file impor belum tersedia saat ini.'
            : err.message);
    } finally {
        if (btn) btn.disabled = false;
    }
}

async function executeImportCommit() {
    const batchId = state.importBatch?.id;
    if (!batchId) return;
    const btn = $('#btnCommitImp');
    if (btn) btn.disabled = true;
    try {
        const idemKey = `imp-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
        const res = await managerApi(`/api/v1/imports/${encodeURIComponent(batchId)}/commit`, 'POST', {
            idempotency_key: idemKey,
        });
        toast(res.message || 'Data impor berhasil disimpan.');
        state.importStep = 3;
        renderImportWizard();
    } catch (err) {
        toast(err.message);
        if (btn) btn.disabled = false;
    }
}

/* ---------- 5. LAPORAN, BIAYA OPERASIONAL, ANALITIK & FORECAST ---------- */
S.rep = () => {
    const activeKey = normalizePeriodKey(state.period);
    const { start, end } = getPeriodRange(activeKey);
    const qs = `period=${encodeURIComponent(activeKey)}&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`;
    return `${ph(
        'Laporan & Analitik',
        'Laporan keuangan terpadu, pencatatan biaya operasional, analitik produk, serta prediksi permintaan dan restok.',
        `<a id="btnExportPdf" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1.5" href="/api/v1/reports/financial/export?${qs}&format=pdf" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf"></i><span>Ekspor PDF</span></a>
        <a id="btnExportXlsx" class="btn btn-outline-success btn-sm d-inline-flex align-items-center gap-1.5" href="/api/v1/reports/financial/export?${qs}&format=xlsx" target="_blank" rel="noopener"><i class="bi bi-file-earmark-excel"></i><span>Ekspor Excel</span></a>`
    )}${renderSubTabShell('rep', ['Keuangan', 'Biaya Operasional', 'Analitik', 'Prediksi Permintaan & Restok'], [RK, RB, RA, RF])}`;
};

H.rep = () => {
    syncPeriodControlsAndExports();
    if (TB.rep === 0 || TB.rep === 2 || TB.rep === 3) loadReportsAndAnalytics(state.period);
};

async function loadReportsAndAnalytics(period = state.period || 'month') {
    state.period = normalizePeriodKey(period);
    syncPeriodControlsAndExports();
    const { start, end } = getPeriodRange(state.period);
    const q = `?period=${encodeURIComponent(state.period)}&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`;
    const [finRes, sumRes, topRes, fcRes] = await Promise.allSettled([
        managerApi(`/api/v1/reports/financial${q}`),
        managerApi(`/api/v1/analytics/summary${q}`),
        managerApi(`/api/v1/analytics/top-products${q}`),
        managerApi(`/api/v1/analytics/forecasts?horizon=${state.forecastHorizon}`),
    ]);
    state.financialReport = finRes.status === 'fulfilled' ? (finRes.value.data || finRes.value) : null;
    state.analyticsSummary = sumRes.status === 'fulfilled' ? (sumRes.value.data || sumRes.value) : null;
    state.topProducts = topRes.status === 'fulfilled' ? (topRes.value.data || topRes.value) : null;
    state.forecasts = fcRes.status === 'fulfilled' ? (fcRes.value.data || fcRes.value) : state.forecasts;

    if (TB.rep === 0 && $('#repFinancialWrap')) renderReportFinancialSection();
    if (TB.rep === 2 && $('#repAnalyticsLiveWrap')) renderReportAnalyticsSection();
    if (TB.rep === 3 && $('#repForecastWrap')) renderReportForecastSection();
}

const RK = () => `
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        ${renderPeriodButtonsHtml(state.period, 'rep')}
        <small class="text-muted font-monospace" id="repPeriodRangeLabel">${escapeHtml(getPeriodRange(state.period).start)} s/d ${escapeHtml(getPeriodRange(state.period).end)}</small>
    </div>
    <div id="repFinancialWrap" class="text-muted small">Memuat ringkasan laporan keuangan...</div>
`;

function renderReportFinancialSection() {
    const wrap = $('#repFinancialWrap');
    if (!wrap) return;
    const rangeLbl = $('#repPeriodRangeLabel');
    const { start, end } = getPeriodRange(state.period);
    if (rangeLbl) rangeLbl.textContent = `${start} s/d ${end}`;

    const fin = state.financialReport || state.analyticsSummary;
    if (!fin) {
        const oltp = computeLocalOltpSummaryForPeriod(state.period);
        const gp = oltp.sales_revenue - oltp.cogs;
        wrap.innerHTML = `
            <div class="alert alert-light border small mb-3">Menampilkan ringkasan transaksi selesai periode ${escapeHtml(oltp.start)} s/d ${escapeHtml(oltp.end)}.</div>
            ${cdTable(`Ringkasan Transaksi (${escapeHtml(oltp.start)} s/d ${escapeHtml(oltp.end)})`, tb(
                ['Komponen', 'Nilai'],
                [
                    ['Omzet Penjualan Selesai', rp(oltp.sales_revenue)],
                    ['HPP Barang Terjual', rp(oltp.cogs)],
                    ['Laba Kotor (Estimasi)', `<strong>${rp(gp)}</strong>`],
                    ['Biaya Operasional Tercatat', rp(oltp.opex)],
                    ['Estimasi Laba Bersih', `<strong class="text-success">${rp(gp - oltp.opex)}</strong>`],
                    ['Kas Masuk Terverifikasi', rp(oltp.verified_payments)],
                    ['Penjualan Piutang Baru', rp(oltp.credit_sales)],
                ]
            ))}
        `;
        return;
    }

    const m = fin.metrics || {};
    const cogsIncomplete = fin.data_quality ? !fin.data_quality.cogs_complete : false;
    const expIncomplete = fin.data_quality ? !fin.data_quality.expenses_complete : false;
    const periodStart = fin.period?.start || start;
    const periodEnd = fin.period?.end || end;
    const banner = (fin.is_stale ? '<div class="alert alert-warning small mb-3">Data analitik sedang dalam <strong>sinkronisasi tertunda</strong>. Periksa waktu pembaruan terakhir.</div>' : '')
        + ((cogsIncomplete || expIncomplete) ? '<div class="alert alert-light border small mb-3">HPP atau biaya operasional belum lengkap. Laba bersih berstatus <strong>estimasi</strong>.</div>' : '');

    wrap.innerHTML = banner + cdTable(`Laporan Keuangan (${escapeHtml(periodStart)} s/d ${escapeHtml(periodEnd)} · per ${escapeHtml(fin.last_updated_at || '-')})`, tb(
        ['Komponen', 'Nilai'],
        [
            ['Omzet Penjualan Selesai', rp(m.sales_revenue)],
            ['HPP Barang Terjual', rp(m.cogs)],
            ['Laba Kotor', `<strong>${rp(m.gross_profit)}</strong>`],
            ['Biaya Operasional', rp(m.operating_expenses)],
            ['Estimasi Laba Bersih', `<strong class="text-success">${rp(m.estimated_net_profit)}</strong>`],
            ['Kas Masuk Terverifikasi', rp(m.cash_receipts)],
            ['Penjualan Piutang Baru', rp(m.new_credit_sales)],
            ['Piutang Terbuka (Akhir Periode)', rp(m.receivables_outstanding_as_of_end)],
            ['Piutang Jatuh Tempo (Akhir Periode)', rp(m.overdue_outstanding_as_of_end)],
        ]
    ));
}

const RB = () => `
    <div class="row g-3 g-xl-4">
        <div class="col-12 col-lg-7">
            ${cdTable('Daftar Biaya Operasional', tb(
                ['Tanggal', 'Kategori', 'Deskripsi', 'Jumlah'],
                BOOTSTRAP.oltp.expenses.map(e => [
                    escapeHtml(String(e.expense_date || '-').slice(0, 10)),
                    escapeHtml(e.category || '-'),
                    escapeHtml(e.description || '-'),
                    `<strong>${rp(e.amount)}</strong>`,
                ]),
                'Belum ada catatan biaya operasional.'
            ))}
        </div>
        <div class="col-12 col-lg-5">
            ${cd('Catat Biaya Operasional', `
                <form id="expenseForm" onsubmit="submitOperatingExpense(event)">
                    ${fl('Tanggal Biaya', `<input type="date" class="form-control" id="expDate" value="${escapeHtml(BOOTSTRAP.today_jakarta)}" required>`)}
                    ${fl('Jenis / Kategori Biaya', '<select class="form-select" id="expCategory" required><option value="Listrik">Listrik &amp; Utilitas</option><option value="Sewa">Sewa Gerai</option><option value="Perlengkapan">ATK &amp; Perlengkapan</option><option value="Gaji">Honor Petugas</option><option value="Lainnya">Lainnya</option></select>')}
                    ${fl('Deskripsi Biaya *', '<input class="form-control" id="expDesc" required maxlength="255" placeholder="Contoh: Tagihan listrik chiller November">')}
                    ${fl('Jumlah (Rp)', '<input type="number" class="form-control" id="expAmount" min="1" step="100" required>')}
                    ${fl('Bukti Pembayaran (Opsional)', '<input type="file" class="form-control" id="expProof" accept="image/*,.pdf">')}
                    <button type="submit" class="btn btn-primary w-100" id="btnSaveExp">Simpan Biaya Operasional</button>
                </form>
            `)}
        </div>
    </div>
`;

async function submitOperatingExpense(e) {
    e.preventDefault();
    const btn = $('#btnSaveExp');
    if (btn) btn.disabled = true;
    try {
        const fd = new FormData();
        fd.append('expense_date', $('#expDate')?.value);
        fd.append('category', $('#expCategory')?.value);
        fd.append('description', $('#expDesc')?.value.trim());
        fd.append('amount', $('#expAmount')?.value);
        const proof = $('#expProof');
        if (proof?.files?.length) fd.append('proof', proof.files[0]);

        const res = await managerApi('/api/v1/operating-expenses', 'POST', fd);
        toast(res.message || 'Biaya operasional berhasil dicatat.');
        location.reload();
    } catch (err) {
        toast(err.status === 404 || err.status === 405
            ? 'Fitur pencatatan biaya operasional belum tersedia saat ini.'
            : err.message);
    } finally {
        if (btn) btn.disabled = false;
    }
}

const RA = () => {
    const outOfStock = BOOTSTRAP.oltp.products.filter(p => Number(p.available || 0) <= 0);
    const deadStockCandidates = BOOTSTRAP.oltp.products.filter(p => Number(p.available || 0) > 0 && Number(p.sales_count || 0) === 0);

    return `
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            ${renderPeriodButtonsHtml(state.period, 'rep')}
            <small class="text-muted font-monospace" id="repAnalyticsPeriodLabel">${escapeHtml(getPeriodRange(state.period).start)} s/d ${escapeHtml(getPeriodRange(state.period).end)}</small>
        </div>
        <div id="repAnalyticsLiveWrap" class="mb-4">
            <div class="row g-3 g-xl-4">
                <div class="col-12 col-lg-7">
                    ${cd('Grafik Tren Penjualan &amp; Laba Kotor', '<div class="mgr-chart-wrap"><canvas id="repAnalyticsTrendChart"></canvas></div><small class="text-muted d-block mt-2" id="repAnalyticsTrendNote">Memuat tren penjualan...</small>')}
                </div>
                <div class="col-12 col-lg-5">
                    ${cdTable('Produk Terlaris Periode Ini', '<div id="repTopProductsTableWrap" class="p-3 text-muted small">Memuat produk terlaris...</div>')}
                </div>
            </div>
        </div>
        <div class="row g-3 g-xl-4">
            <div class="col-12 col-lg-6">
                ${cdTable('Stok Habis / Kritis (Perlu Perhatian Segera)', tb(
                    ['SKU', 'Produk', 'Tersedia', 'Status'],
                    outOfStock.map(p => [escapeHtml(p.sku), escapeHtml(p.name), `0 ${escapeHtml(p.unit)}`, pl('Stok Habis', 'r')]),
                    'Tidak ada produk yang stoknya habis.'
                ))}
            </div>
            <div class="col-12 col-lg-6">
                ${cdTable('Stok Mengendap / Dead Stock (Stok &gt; 0, Belum Terjual)', tb(
                    ['SKU', 'Produk', 'Stok Tersedia', 'Terjual'],
                    deadStockCandidates.map(p => [escapeHtml(p.sku), escapeHtml(p.name), `${p.available} ${escapeHtml(p.unit)}`, `${p.sales_count}`]),
                    'Tidak ada barang dead stock dengan stok mengendap.'
                ))}
            </div>
        </div>
    `;
};

function renderReportAnalyticsSection() {
    const { start, end } = getPeriodRange(state.period);
    const lbl = $('#repAnalyticsPeriodLabel');
    if (lbl) lbl.textContent = `${start} s/d ${end}`;

    const trendCanvas = $('#repAnalyticsTrendChart');
    if (trendCanvas && typeof Chart !== 'undefined') {
        if (state.charts.repAnalyticsTrend) {
            try { state.charts.repAnalyticsTrend.destroy(); } catch (_) {}
            state.charts.repAnalyticsTrend = null;
        }
        const trend = state.financialReport?.rows || state.analyticsSummary?.trend || [];
        const labels = trend.length ? trend.map(r => r.label || r.period || r.date) : [start];
        const revSeries = trend.length ? trend.map(r => Number(r.sales_revenue || 0)) : [0];
        const gpSeries = trend.length ? trend.map(r => Number(r.gross_profit || 0)) : [0];

        state.charts.repAnalyticsTrend = new Chart(trendCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    { label: 'Omzet', data: revSeries, backgroundColor: '#059669', borderRadius: 6 },
                    { label: 'Laba Kotor', data: gpSeries, backgroundColor: '#34d399', borderRadius: 6 },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } },
            },
        });

        const note = $('#repAnalyticsTrendNote');
        if (note) {
            const m = state.analyticsSummary?.metrics || state.financialReport?.metrics || {};
            const omz = Number(m.sales_revenue ?? computeLocalOltpSummaryForPeriod(state.period).sales_revenue);
            const gp = Number(m.gross_profit ?? (omz - computeLocalOltpSummaryForPeriod(state.period).cogs));
            note.textContent = `Periode ${start} s/d ${end} · Total Omzet ${rp(omz)} · Laba Kotor ${rp(gp)}`;
        }
    }

    const topWrap = $('#repTopProductsTableWrap');
    if (topWrap) {
        const items = Array.isArray(state.topProducts?.items || state.topProducts)
            ? (state.topProducts?.items || state.topProducts)
            : [];
        topWrap.className = '';
        topWrap.innerHTML = tb(
            ['Produk', 'Terjual', 'Omzet'],
            items.map(it => [
                `<span class="fw-semibold text-dark">${escapeHtml(it.name || it.sku || '-')}</span><br><small class="text-muted font-monospace">${escapeHtml(it.sku || '')}</small>`,
                `${it.units ?? it.sold_qty ?? 0} ${escapeHtml(it.unit || 'unit')}`,
                `<strong class="tokped-price">${rp(it.sales_revenue ?? it.omzet ?? 0)}</strong>`,
            ]),
            `Belum ada penjualan produk pada periode ${start} s/d ${end}.`
        );
    }
}

const RF = () => `
    <div class="alert alert-light border small mb-3">
        <i class="bi bi-shield-check text-success me-1"></i>
        Prediksi ini hanya merupakan saran analitik. Sistem <strong>tidak pernah</strong> membuat pesanan pembelian otomatis ke pemasok tanpa persetujuan manual Manajer.
    </div>
    <div class="mgr-tabs mb-3">
        <button type="button" class="btn-tab ${state.forecastHorizon === 7 ? 'active' : ''}" onclick="state.forecastHorizon=7;TB.rep=3;route()">Prediksi 7 Hari ke Depan</button>
        <button type="button" class="btn-tab ${state.forecastHorizon === 30 ? 'active' : ''}" onclick="state.forecastHorizon=30;TB.rep=3;route()">Prediksi 30 Hari ke Depan</button>
    </div>
    <div id="repForecastWrap" class="text-muted small">Memuat hasil prediksi permintaan...</div>
`;

function renderReportForecastSection() {
    const wrap = $('#repForecastWrap');
    if (!wrap) return;
    if (!state.forecasts) {
        wrap.innerHTML = '<div class="alert alert-light border small mb-0">Memuat data prediksi permintaan dan saran restok...</div>';
        return;
    }
    const items = state.forecasts.items || state.forecasts || [];
    const is30 = Number(state.forecastHorizon) === 30;
    const urgBadge = u => ({
        TINGGI: pl('Urgensi Tinggi', 'r'),
        SEDANG: pl('Perlu Restok', 'o'),
        RENDAH: pl('Stok Aman', 'g'),
    }[u] || pl(u || 'Aman', 'g'));

    wrap.innerHTML = cdTable(`Rekomendasi Restok &amp; Evaluasi Model (Prediksi ${state.forecastHorizon} Hari ke Depan)`, tb(
        ['Produk', 'Stok Tersedia', `Proyeksi ${state.forecastHorizon} Hari`, 'Saran Restok', 'Urgensi', 'Evaluasi / Akurasi'],
        items.map(f => {
            const predicted = is30 ? (f.forecast_30d ?? f.predicted_units ?? 0) : (f.forecast_7d ?? f.predicted_units ?? 0);
            const recQty = is30
                ? Number(f.recommended_restock_qty_30d ?? f.recommended_restock_qty ?? f.recommended_qty ?? 0)
                : Number(f.recommended_restock_qty_7d ?? f.recommended_restock_qty ?? f.recommended_qty ?? 0);
            const unitLbl = escapeHtml(f.unit || 'unit');
            return [
                `<span class="fw-semibold text-dark">${escapeHtml(f.name || f.sku || '-')}</span><br><small class="text-muted font-monospace">${escapeHtml(f.sku || '')}</small>`,
                `${f.available_stock ?? 0} ${unitLbl}`,
                `<strong>${predicted} ${unitLbl}</strong><br><small class="text-muted">Rata-rata ${f.avg_daily_sales ?? 0}/hari</small>`,
                recQty > 0
                    ? `<strong class="text-danger">+${recQty} ${unitLbl}</strong>`
                    : `<span class="text-success fw-semibold">0 (Cukup)</span>`,
                urgBadge(f.urgency),
                f.insufficient_data ? pl('Data Histori Kurang', 'o') : pl(`Akurasi Prediksi: ${f.wape ?? '-'}%`, 'g'),
            ];
        }),
        'Belum ada data prediksi permintaan.'
    ));
}

/* ---------- 6. SISTEM & AUDIT LOG ---------- */
S.sys = () => `${ph('Sistem & Audit Log', 'Konfigurasi waktu operasional, status layanan, dan jejak aktivitas sistem.')}
<div class="row g-3 g-xl-4 mb-4">
    <div class="col-12 col-md-4">${statCardHtml('Zona Waktu Operasional', 'WIB (UTC+7)', 'Asia/Jakarta · Pekan dimulai Senin', 'bi-clock', '#ecfdf5', '#059669', 'text-dark')}</div>
    <div class="col-12 col-md-4">${statCardHtml('Kebijakan Kredit Aktif', state.creditPolicy ? rp(state.creditPolicy.limit_amount) : 'Buka menu Piutang', 'Jatuh tempo terkunci 7 hari', 'bi-sliders', '#f3f4f6', '#4b5563', 'text-dark')}</div>
    <div class="col-12 col-md-4">${statCardHtml('Ketahanan Operasional', state.analyticsSummary?.last_updated_at || 'Operasional Mandiri', 'Transaksi gerai berjalan normal setiap saat', 'bi-shield-check', '#ecfdf5', '#059669', 'text-success')}</div>
</div>
${cdTable('Jejak Aktivitas Sistem', tb(
    ['Waktu (WIB)', 'Aktor', 'Aksi', 'Entitas', 'Target'],
    BOOTSTRAP.oltp.audit_logs.map(a => {
        const rawTarget = String(a.entity_id || '').trim();
        const formattedTarget = /^[0-9a-f]{8}-[0-9a-f]{4}-/i.test(rawTarget)
            ? '#' + rawTarget.slice(-6).toUpperCase()
            : (rawTarget || '-');
        return [
            escapeHtml(a.created_at),
            escapeHtml(a.actor),
            pl(a.action, 'i'),
            escapeHtml(a.entity_type),
            `<span class="font-monospace">${escapeHtml(formattedTarget)}</span>`,
        ];
    }),
    'Belum ada rekaman aktivitas sistem.'
))}`;

/* ---------- BOOTSTRAP ---------- */
document.addEventListener('DOMContentLoaded', () => {
    if (!location.hash) {
        history.replaceState(null, '', '#' + (BOOTSTRAP.initial_page || 'home'));
    }

    document.querySelectorAll('.sidebar .nav-link[data-mgr-page], .mobile-role-nav a[data-mgr-page]').forEach(link => {
        link.addEventListener('click', e => {
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
            const targetPage = link.dataset.mgrPage;
            if (!targetPage || !S[targetPage]) return;
            e.preventDefault();
            if (Object.prototype.hasOwnProperty.call(TB, targetPage)) {
                TB[targetPage] = 0;
            }
            BOOTSTRAP.initial_page = targetPage;
            const nextUrl = `${link.pathname}#${targetPage}`;
            if (`${location.pathname}${location.hash}` !== nextUrl) {
                history.pushState({ mgrPage: targetPage }, '', nextUrl);
            }
            route();
        });
    });

    window.addEventListener('popstate', () => route());
    route();
});
</script>
@endpush
