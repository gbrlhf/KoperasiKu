<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Member;
use App\Models\Order;
use App\Models\Payment;
use App\Services\TransactionService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * CashierController (Portal Khusus Kasir & Petugas Gerai KoperasiKu)
 *
 * Mengelola operasional gerai:
 * - Monitoring stok barang (Stok fisik, reservasi pickup, stok tersedia kasir)
 * - Transaksi langsung meja kasir (POS Kasir & Barcode Lookup)
 * - Antrian pickup pesanan online anggota
 * - Pelunasan piutang konsumsi
 */
class CashierController extends Controller
{
    public function index(): View
    {
        $stockItems = DB::table('products as p')
            ->leftJoin('inventory_balances as b', 'b.product_id', '=', 'p.id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where('p.is_active', true)
            ->select('p.id', 'p.sku', 'p.barcode', 'p.name', 'p.selling_price', 'p.unit', 'p.reorder_point', 'c.name as category', 'p.category as legacy_category', 'b.on_hand', 'b.reserved')
            ->orderBy('p.sku')->get()->map(function ($p) {
                $available = (int) $p->on_hand - (int) $p->reserved;
                return ['id' => (string) $p->id, 'sku' => $p->sku, 'barcode' => $p->barcode ?? $p->sku, 'name' => $p->name,
                    'category' => $p->category ?? $p->legacy_category ?? '-', 'sell_price' => (float) $p->selling_price,
                    'on_hand' => (int) $p->on_hand, 'reserved' => (int) $p->reserved, 'available' => $available,
                    'unit' => $p->unit, 'status' => $available <= 0 ? 'OUT' : ($available <= $p->reorder_point ? 'LOW' : 'SAFE')];
            });
        $stockSummary = ['total_sku' => $stockItems->count(), 'safe_stock_count' => $stockItems->where('status', 'SAFE')->count(),
            'low_stock_count' => $stockItems->where('status', 'LOW')->count(), 'out_of_stock_count' => $stockItems->where('status', 'OUT')->count(),
            'total_reserved_items' => $stockItems->sum('reserved')];
        $pickupQueue = Order::with('items')->where('channel', 'ONLINE_PICKUP')
            ->whereIn('status', ['PENDING_PAYMENT', 'PENDING_PICKUP', 'READY_FOR_PICKUP'])
            ->whereNotNull('pickup_code')->orderBy('created_at')->limit(100)->get()->map(fn ($order) => $this->pickupData($order))->all();
        $membersDirectory = Member::whereRaw('UPPER(status) = ?', ['ACTIVE'])->orderBy('full_name')->get()->map(function ($member) {
            $summary = app(TransactionService::class)->getMemberCreditSummary((string) $member->id);
            return ['id' => (string) $member->id, 'name' => $member->full_name, 'unit' => $member->employee_number,
                'status' => $summary['eligible_for_credit'] ? 'TERVERIFIKASI' : 'DIBEKUKAN',
                'available_limit' => $summary['available_limit'], 'total_limit' => $summary['global_limit'],
                'is_overdue' => $summary['has_overdue'], 'overdue_days' => $summary['has_overdue'] ? (int) \Carbon\CarbonImmutable::parse($member->receivables()->where('outstanding_amount', '>', 0)->min('due_date'), 'Asia/Jakarta')->diffInDays(\Carbon\CarbonImmutable::today('Asia/Jakarta')) : 0,
                'unpaid_invoices' => $member->receivables()->where('outstanding_amount', '>', 0)->count()];
        })->all();
        return view('cashier.pos', compact('stockSummary', 'stockItems', 'pickupQueue', 'membersDirectory'));
    }

    public function pickupLookup(Request $request)
    {
        $data = $request->validate(['code' => 'required|string|max:100']);
        $code = strtoupper(trim($data['code']));
        $order = Order::with('items')->where('channel', 'ONLINE_PICKUP')
            ->where(fn ($q) => $q->whereRaw('UPPER(pickup_code) = ?', [$code])->orWhereRaw('UPPER(order_no) = ?', [$code]))->first();
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Kode pickup tidak ditemukan.'], 404);
        }
        return response()->json(['success' => true, 'data' => $this->pickupData($order)]);
    }

    private function pickupData(Order $order): array
    {
        $member = Member::find($order->member_id);
        $pending = Payment::where('order_id', $order->id)->where('status', 'PENDING_VERIFICATION')->oldest()->first();
        $verified = Payment::where('order_id', $order->id)->where('status', 'VERIFIED')->sum('amount');
        $hold = $order->creditHold;
        $ready = $order->payment_choice === 'CREDIT'
            ? $order->status === 'PENDING_PICKUP' && $hold?->status === 'ACTIVE'
            : $order->status === 'READY_FOR_PICKUP';
        return ['id' => (string) $order->id, 'pickup_code' => $order->pickup_code,
            'member_id' => $member?->employee_number ?? '-', 'member_name' => $member?->full_name ?? '-',
            'created_at' => $order->created_at->timezone('Asia/Jakarta')->format('d M Y H:i'),
            'items_summary' => $order->items->map(fn ($item) => (DB::table('products')->where('id', $item->product_id)->value('name') ?? 'Produk') . ' (' . $item->quantity . ')')->implode(', '),
            'items_count' => $order->items->sum('quantity'), 'payment_method' => $order->payment_choice,
            'total_amount' => (float) $order->total_amount, 'status' => $order->status, 'can_handover' => $ready,
            'payment_status' => $ready ? ($order->payment_choice === 'CREDIT' ? 'CREDIT_APPROVED' : 'VERIFIED') : ($pending ? 'PENDING_VERIFICATION' : 'PENDING_PAYMENT'),
            'transfer_ref' => $pending?->external_reference, 'pending_payment_id' => $pending?->id,
            'pending_payment_amount' => $pending ? (float) $pending->amount : null,
            'remaining_amount' => max(0, (float) $order->total_amount - (float) $verified - (float) ($pending?->amount ?? 0))];
    }

    public function antrian(): View
    {
        return $this->index();
    }

    public function stok(): View
    {
        return $this->index();
    }

    public function piutang(): View
    {
        $globalCreditLimit = (float) (\App\Models\CreditPolicy::where('active', true)->latest('effective_at')->value('limit_amount') ?? 0);
        $today = \Carbon\CarbonImmutable::today('Asia/Jakarta');
        $debtorMembers = Member::orderBy('full_name')->get()->map(function ($m) use ($today) {
            $credit = app(TransactionService::class)->getMemberCreditSummary((string) $m->id);
            $invoices = $m->receivables()->with('order')->where('outstanding_amount', '>', 0)->orderBy('due_date')->get();
            return ['id' => (string) $m->id, 'name' => $m->full_name, 'unit' => $m->employee_number ?? '-',
                'phone' => '-', 'status' => $credit['credit_account_status'], 'has_overdue' => $credit['has_overdue'],
                'global_limit' => $credit['global_limit'], 'total_debt' => $credit['outstanding_receivable'],
                'available_limit' => $credit['available_limit'], 'active_hold' => $credit['active_credit_hold'], 'earliest_due_date' => $invoices->first()?->due_date?->format('d M Y'),
                'days_remaining' => $invoices->isEmpty() ? 0 : (int) $today->diffInDays($invoices->first()->due_date->toDateString(), false),
                'invoices' => $invoices->map(fn ($r) => ['id' => (string) $r->id, 'order_no' => $r->order?->order_no ?? '-',
                    'pickup_date' => $r->order?->completed_at ? \Carbon\Carbon::parse($r->order->completed_at)->format('d M Y') : '-',
                    'due_date' => $r->due_date->format('d M Y'), 'days_left' => (int) $today->diffInDays($r->due_date->toDateString(), false),
                    'items_summary' => $r->order?->order_no ?? '-', 'original_amount' => $r->original_amount,
                    'paid_amount' => $r->original_amount - $r->outstanding_amount, 'remaining_amount' => $r->outstanding_amount,
                    'status' => $r->effectiveStatus()])->all()];
        })->all();
        $pendingTransfers = Payment::whereNull('order_id')->where('method', 'BANK_TRANSFER')->where('status', 'PENDING_VERIFICATION')->with('member')->oldest()->get();
        return view('cashier.piutang', compact('debtorMembers', 'globalCreditLimit', 'pendingTransfers'));
    }
}
