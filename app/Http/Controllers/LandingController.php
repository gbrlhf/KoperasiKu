<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * LandingController (E-Commerce Storefront KoperasiKu)
 *
 * Mengelola etalase toko online e-commerce untuk konsumen & anggota KoperasiKu.
 * Setiap produk dilengkapi foto resolusi tinggi, ulasan, spesifikasi, dan deskripsi detail
 * ala marketplace modern (Tokopedia/Shopee) dengan alur modal interaktif.
 */
class LandingController extends Controller
{
    public function index(): View
    {
        $products = \App\Models\Product::with('inventory')->where('is_active', true)->orderBy('sku')->get();
        $categoryNames = \App\Models\Category::pluck('name', 'id');
        $catalog = $products->map(fn ($p) => [
            'id' => (string) $p->id, 'sku' => $p->sku, 'name' => $p->name,
            'category' => $categoryNames->get($p->category_id) ?? ($p->category ?: '-'), 'price' => (float) $p->selling_price,
            'original_price' => (float) ($p->original_price ?: $p->selling_price),
            'available_stock' => max(0, (int) $p->inventory?->on_hand - (int) $p->inventory?->reserved),
            'unit' => $p->unit ?? '-', 'badge' => $p->badge ?? '', 'rating' => $p->rating ?? 0,
            'reviews_count' => 0, 'sold_count' => (string) ($p->sales_count ?? 0).' terjual',
            'icon' => 'bi-box-seam', 'image' => $p->image ?? '', 'description' => $p->name, 'specs' => [],
        ])->all();
        $categories = \App\Models\Category::orderBy('name')->get()->map(fn ($c) => [
            'id' => $c->id, 'name' => $c->name, 'icon' => $c->icon ?? 'bi-box',
            'item_count' => $products->filter(fn ($p) => $p->category_id === $c->id || strcasecmp((string) $p->category, (string) $c->name) === 0)->count().' Produk',
        ])->all();
        $bestDeals = array_slice($catalog, 0, 5);
        $groceryProducts = array_slice($catalog, 5, 5);
        $householdProducts = array_slice($catalog, 10, 5);
        $snackBeverages = array_slice($catalog, 15);

        $howToOrder = [
            [
                'step' => '1',
                'icon' => 'bi-cart-check',
                'title' => 'Pilih Barang',
                'desc' => 'Klik produk untuk melihat rincian spesifikasi dan tambahkan ke keranjang.',
            ],
            [
                'step' => '2',
                'icon' => 'bi-wallet2',
                'title' => 'Checkout Pesanan',
                'desc' => 'Pilih metode bayar: Tunai, Transfer, atau Piutang Anggota 7 Hari.',
            ],
            [
                'step' => '3',
                'icon' => 'bi-qr-code',
                'title' => 'Dapatkan Kode Pickup',
                'desc' => 'Sistem menerbitkan Kode Pickup unik (contoh: PKP-84920).',
            ],
            [
                'step' => '4',
                'icon' => 'bi-shop',
                'title' => 'Ambil di Gerai',
                'desc' => 'Tunjukkan kode ke kasir di Jl. Kanayakan No. A11 Dago.',
            ],
        ];

        // 6. Metode Pembayaran Resmi
        $paymentMethods = [
            [
                'code' => 'CASH',
                'title' => 'Tunai di Kasir (Cash)',
                'icon' => 'bi-cash-coin',
                'badge' => 'Langsung',
                'desc' => 'Bayar saat mengambil barang di gerai kasir.',
                'features' => ['Tanpa biaya administrasi', 'Bukti cetak struk langsung', 'Cocok untuk belanja harian'],
            ],
            [
                'code' => 'TRANSFER',
                'title' => 'Transfer Bank / QRIS',
                'icon' => 'bi-bank',
                'badge' => 'Non-Tunai',
                'desc' => 'Transfer ke rekening resmi koperasi sebelum pengambilan.',
                'features' => ['Verifikasi cepat di kasir', 'Catatan mutasi digital', 'Aman & tercatat'],
            ],
            [
                'code' => 'CREDIT',
                'title' => 'Piutang Anggota (Kredit Konsumsi)',
                'icon' => 'bi-calendar-check',
                'badge' => 'Fasilitas Anggota',
                'desc' => 'Bunga 0%, jatuh tempo tepat 7 hari kalender setelah barang fisik diambil.',
                'features' => ['Bunga 0% murni konsumsi', 'Tempo tepat 7 hari', 'Khusus anggota aktif'],
            ],
        ];

        // 7. Statistik Koperasi
        $statistics = [
            ['label' => 'Anggota Aktif', 'value' => (string) \App\Models\Member::whereRaw("UPPER(status) = 'ACTIVE'")->count()],
            ['label' => 'Produk Tersedia', 'value' => (string) $products->count()],
            ['label' => 'Pesanan Terlayani', 'value' => (string) \App\Models\Order::where('status', 'COMPLETED')->count()],
            ['label' => 'Kepuasan Anggota', 'value' => '—'],
        ];

        // 8. FAQ KoperasiKu
        $faqs = [
            [
                'question' => 'Bagaimana cara belanja di etalase KoperasiKu?',
                'answer' => 'Cukup klik produk untuk melihat spesifikasi detail seperti di marketplace, lalu klik "+ Keranjang" atau "Beli Sekarang". Anda akan mendapatkan Kode Pickup unik untuk mengambil pesanan di gerai KOPEG Mart.',
            ],
            [
                'question' => 'Apakah pesanan bisa diantar ke rumah?',
                'answer' => 'Sesuai aturan sistem KoperasiKu, layanan kami adalah PICKUP ONLY (ambil langsung di gerai koperasi, tanpa kurir pengiriman) sehingga bebas dari biaya kirim.',
            ],
            [
                'question' => 'Apa syarat menggunakan pembayaran Piutang Anggota (Tempo 7 Hari)?',
                'answer' => 'Fasilitas piutang belanja hanya tersedia bagi anggota aktif yang tidak memiliki tunggakan lewat jatuh tempo. Pembayaran jatuh tempo tepat 7 hari kalender setelah barang fisik diambil di kasir.',
            ],
            [
                'question' => 'Kapan jam buka gerai KOPEG Mart untuk pengambilan barang?',
                'answer' => 'Gerai KOPEG Mart berlokasi di Jl. Kanayakan No. A11 (depan Kampus POLMAN Bandung), melayani pengambilan pesanan setiap Senin s.d. Sabtu pukul 07.00 – 18.00 WIB (Minggu dan libur nasional tutup).',
            ],
        ];

        // 9. Status Autentikasi Pengunjung (Default: Belum Login)
        $isLoggedIn = session()->has('user');
        $sessionUser = session('user');

        $member = null;
        $receivableStatus = null;
        $activeOrders = [];
        $orderHistory = [];

        $identity = auth()->user();
        $isLoggedIn = $identity !== null;
        if ($identity) {
            $profile = $identity->member;
            $member = ['id' => $profile?->employee_number ?? (string) $identity->id,
                'name' => $profile?->full_name ?? $identity->email, 'unit' => $profile?->department ?? '-',
                'email' => $identity->email, 'status' => $profile?->status ?? $identity->status];
            if ($profile) {
                $credit = app(\App\Services\TransactionService::class)->getMemberCreditSummary((string) $profile->id);
                $today = \Carbon\CarbonImmutable::today('Asia/Jakarta');
                $invoices = $profile->receivables()->with('order')->where('outstanding_amount', '>', 0)->orderBy('due_date')->get();
                $receivableStatus = array_merge($credit, [
                    'has_debt' => $invoices->isNotEmpty(), 'total_debt' => $credit['outstanding_receivable'],
                    'credit_limit' => $credit['global_limit'], 'is_overdue' => $credit['has_overdue'],
                    'days_remaining' => $invoices->isEmpty() ? 0 : (int) $today->diffInDays($invoices->first()->due_date->toDateString(), false),
                    'limit_used_percent' => $credit['global_limit'] > 0 ? 100 * (1 - $credit['available_limit'] / $credit['global_limit']) : 0,
                    'active_invoices' => $invoices->map(fn ($r) => ['invoice_no' => $r->order?->order_no ?? $r->id,
                        'items_summary' => $r->order?->order_no ?? '-', 'amount' => $r->outstanding_amount,
                        'due_date' => $r->due_date->format('d M Y'), 'days_left' => (int) $today->diffInDays($r->due_date->toDateString(), false),
                        'status' => $r->effectiveStatus()])->all(),
                ]);
                $orders = $profile->orders()->with('items')->latest()->get();
                $mapOrder = function ($o) use ($products) {
                    $items = $o->items->map(fn ($i) => ['name' => $products->firstWhere('id', $i->product_id)?->name ?? 'Produk', 'qty' => $i->quantity, 'unit' => $products->firstWhere('id', $i->product_id)?->unit ?? '-', 'price' => (float) $i->unit_price_snapshot]);
                    return ['pickup_code' => $o->pickup_code, 'order_no' => $o->order_no ?? $o->id,
                        'date' => $o->created_at->timezone('Asia/Jakarta')->format('d M Y H:i'), 'status' => $o->status,
                        'status_label' => $o->status, 'status_step' => $o->status === 'READY_FOR_PICKUP' ? 3 : 1,
                        'payment_method' => $o->payment_choice, 'payment_label' => $o->payment_choice,
                        'total_amount' => (float) $o->total_amount, 'items_count' => $o->items->sum('quantity'),
                        'items' => $items->all(), 'items_summary' => $items->pluck('name')->implode(', '),
                        'picked_up_at' => $o->completed_at ? \Carbon\Carbon::parse($o->completed_at)->timezone('Asia/Jakarta')->format('d M Y H:i') : '-',
                        'store_location' => 'Gerai koperasi', 'store_hours' => '-'];
                };
                $activeOrders = $orders->whereIn('status', ['PENDING_PAYMENT','PENDING_PICKUP','READY_FOR_PICKUP'])->whereNotNull('pickup_code')->map($mapOrder)->values()->all();
                $orderHistory = $orders->whereIn('status', ['COMPLETED','CANCELLED'])->map($mapOrder)->values()->all();
            }
        }

        // Master lookup map & daftar lengkap semua produk koperasi
        $allProducts = array_merge($bestDeals, $groceryProducts, $householdProducts, $snackBeverages);
        $allProductsMap = [];
        foreach ($allProducts as $p) {
            $allProductsMap[$p['id']] = $p;
        }

        return view('landing', compact(
            'categories',
            'bestDeals',
            'groceryProducts',
            'householdProducts',
            'snackBeverages',
            'allProducts',
            'howToOrder',
            'paymentMethods',
            'statistics',
            'faqs',
            'allProductsMap',
            'isLoggedIn',
            'member',
            'receivableStatus',
            'activeOrders',
            'orderHistory'
        ));
    }
}
