<?php

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AnalyticsQueryService
{
    public function installed(): bool
    {
        return Schema::hasTable('orders') && Schema::hasTable('products');
    }

    public function readSnapshot(callable $callback): array
    {
        return (array) $callback();
    }

    public function summary(string $from, string $to): array
    {
        if ($this->hasPopulatedWarehouse()) {
            return $this->warehouseSummary($from, $to);
        }

        return $this->oltpSummary($from, $to);
    }

    public function exportPayload(string $from, string $to, string $periodLabel = ''): array
    {
        $summary = $this->summary($from, $to);
        $summary['period_label'] = $periodLabel;
        $summary['payment_breakdown'] = $this->paymentBreakdown($from, $to);
        $summary['top_products'] = $this->topProducts($from, $to, 10);
        $summary['operating_expenses_items'] = $this->operatingExpensesList($from, $to);
        $summary['rows'] = $this->trend($from, $to, 'day');

        return $summary;
    }

    /**
     * @return array<int, array{method: string, label: string, orders_count: int, amount: string}>
     */
    public function paymentBreakdown(string $from, string $to): array
    {
        $labels = [
            'CASH' => 'Tunai',
            'BANK_TRANSFER' => 'Transfer Bank / QRIS',
            'CREDIT' => 'Piutang Anggota',
        ];
        $counts = ['CASH' => 0, 'BANK_TRANSFER' => 0, 'CREDIT' => 0];
        $totals = ['CASH' => 0.0, 'BANK_TRANSFER' => 0.0, 'CREDIT' => 0.0];

        if ($this->hasPopulatedWarehouse()) {
            $rows = DB::table('analytics.fact_sales')
                ->whereBetween('local_date', [$from, $to])
                ->groupBy('payment_choice')
                ->selectRaw('payment_choice, COUNT(DISTINCT order_id) AS cnt, COALESCE(SUM(revenue), 0) AS amt')
                ->get();

            foreach ($rows as $r) {
                $k = strtoupper((string) $r->payment_choice);
                if (! isset($totals[$k])) {
                    $counts[$k] = 0;
                    $totals[$k] = 0.0;
                    $labels[$k] = $k;
                }
                $counts[$k] += (int) $r->cnt;
                $totals[$k] += (float) $r->amt;
            }
        } elseif (Schema::hasTable('orders')) {
            [$startUtc, $endUtc] = $this->utcBounds($from, $to);
            $rows = DB::table('orders')
                ->where('status', 'COMPLETED')
                ->whereBetween(DB::raw('COALESCE(completed_at, created_at)'), [$startUtc, $endUtc])
                ->groupBy('payment_choice')
                ->selectRaw('payment_choice, COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS amt')
                ->get();

            foreach ($rows as $r) {
                $k = strtoupper((string) $r->payment_choice);
                if (! isset($totals[$k])) {
                    $counts[$k] = 0;
                    $totals[$k] = 0.0;
                    $labels[$k] = $k;
                }
                $counts[$k] += (int) $r->cnt;
                $totals[$k] += (float) $r->amt;
            }
        }

        $out = [];
        foreach ($labels as $code => $label) {
            $out[] = [
                'method' => $code,
                'label' => $label,
                'orders_count' => $counts[$code] ?? 0,
                'amount' => $this->fmt($totals[$code] ?? 0.0),
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{expense_date: string, category: string, description: string, amount: string}>
     */
    public function operatingExpensesList(string $from, string $to): array
    {
        if (Schema::hasTable('operating_expenses')) {
            return DB::table('operating_expenses')
                ->whereBetween('expense_date', [$from, $to])
                ->orderBy('expense_date')
                ->get(['expense_date', 'category', 'description', 'amount'])
                ->map(fn ($e) => [
                    'expense_date' => substr((string) $e->expense_date, 0, 10),
                    'category' => (string) ($e->category ?? 'Umum'),
                    'description' => (string) ($e->description ?? '-'),
                    'amount' => $this->fmt($e->amount),
                ])
                ->all();
        }

        if ($this->hasPopulatedWarehouse()) {
            return DB::table('analytics.fact_operating_expenses')
                ->whereBetween('local_date', [$from, $to])
                ->orderBy('local_date')
                ->get(['local_date', 'category', 'description', 'amount'])
                ->map(fn ($e) => [
                    'expense_date' => (string) $e->local_date,
                    'category' => (string) ($e->category ?? 'Umum'),
                    'description' => (string) ($e->description ?? '-'),
                    'amount' => $this->fmt($e->amount),
                ])
                ->all();
        }

        return [];
    }

    public function trend(string $from, string $to, string $frequency = 'day'): array
    {
        if ($this->hasPopulatedWarehouse()) {
            return $this->warehouseTrend($from, $to, $frequency);
        }

        return $this->oltpTrend($from, $to, $frequency);
    }

    public function topProducts(string $from, string $to, int $limit = 10): array
    {
        if ($this->hasPopulatedWarehouse()) {
            $rows = DB::table('analytics.fact_sales as s')
                ->join('analytics.dim_product as p', 'p.product_key', '=', 's.product_key')
                ->whereBetween('s.local_date', [$from, $to])
                ->groupBy('p.sku', 'p.name', 'p.category')
                ->selectRaw('p.sku, p.name, p.category, SUM(s.quantity)::int AS units, SUM(s.revenue)::numeric(14,2) AS sales_revenue, SUM(COALESCE(s.cogs, 0))::numeric(14,2) AS cogs')
                ->orderByDesc('sales_revenue')
                ->limit($limit)
                ->get();

            return $rows->map(fn ($r) => [
                'sku' => $r->sku,
                'name' => $r->name,
                'category' => $r->category,
                'units' => (int) $r->units,
                'sold_qty' => (int) $r->units,
                'sales_revenue' => $this->fmt($r->sales_revenue),
                'omzet' => (float) $r->sales_revenue,
                'cogs' => $this->fmt($r->cogs),
                'gross_profit' => $this->fmt((float) $r->sales_revenue - (float) $r->cogs),
            ])->all();
        }

        [$startUtc, $endUtc] = $this->utcBounds($from, $to);

        $rows = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('products as p', 'p.id', '=', 'oi.product_id')
            ->where('o.status', 'COMPLETED')
            ->whereBetween(DB::raw('COALESCE(o.completed_at, o.created_at)'), [$startUtc, $endUtc])
            ->groupBy('p.id', 'p.sku', 'p.name', 'p.category', 'p.unit')
            ->selectRaw('p.id, p.sku, p.name, p.category, p.unit, SUM(oi.quantity) AS units, SUM(oi.quantity * oi.unit_price_snapshot) AS sales_revenue, SUM(oi.quantity * COALESCE(oi.hpp_snapshot, p.current_cost, 0)) AS cogs')
            ->orderByDesc('sales_revenue')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'sku' => $r->sku,
            'name' => $r->name,
            'category' => $r->category ?? 'Umum',
            'unit' => $r->unit ?? 'Pcs',
            'units' => (int) $r->units,
            'sold_qty' => (int) $r->units,
            'sales_revenue' => $this->fmt($r->sales_revenue),
            'omzet' => (float) $r->sales_revenue,
            'cogs' => $this->fmt($r->cogs),
            'gross_profit' => $this->fmt((float) $r->sales_revenue - (float) $r->cogs),
        ])->all();
    }

    public function categories(string $from, string $to): array
    {
        $top = $this->topProducts($from, $to, 100);
        $grouped = [];
        foreach ($top as $item) {
            $cat = $item['category'] ?: 'Umum';
            if (! isset($grouped[$cat])) {
                $grouped[$cat] = [
                    'category' => $cat,
                    'units' => 0,
                    'sales_revenue' => 0.0,
                    'cogs' => 0.0,
                ];
            }
            $grouped[$cat]['units'] += (int) $item['units'];
            $grouped[$cat]['sales_revenue'] += (float) $item['sales_revenue'];
            $grouped[$cat]['cogs'] += (float) $item['cogs'];
        }

        return collect($grouped)->values()->map(fn ($g) => [
            'category' => $g['category'],
            'units' => $g['units'],
            'sales_revenue' => $this->fmt($g['sales_revenue']),
            'cogs' => $this->fmt($g['cogs']),
            'gross_profit' => $this->fmt($g['sales_revenue'] - $g['cogs']),
        ])->all();
    }

    public function aging(string $asof): array
    {
        $asofDate = CarbonImmutable::parse($asof, 'Asia/Jakarta')->startOfDay();
        $buckets = [
            ['key' => 'current', 'label' => 'Belum jatuh tempo (Current)', 'amount' => 0.0, 'count' => 0, 'color' => '#10b981'],
            ['key' => '1_30', 'label' => '1–30 hari lewat tempo', 'amount' => 0.0, 'count' => 0, 'color' => '#f59e0b'],
            ['key' => '31_60', 'label' => '31–60 hari lewat tempo', 'amount' => 0.0, 'count' => 0, 'color' => '#f97316'],
            ['key' => '61_90', 'label' => '61–90 hari lewat tempo', 'amount' => 0.0, 'count' => 0, 'color' => '#ef4444'],
            ['key' => 'gt_90', 'label' => '>90 hari lewat tempo', 'amount' => 0.0, 'count' => 0, 'color' => '#991b1b'],
        ];

        $openReceivables = Schema::hasTable('receivables')
            ? DB::table('receivables')->where('outstanding_amount', '>', 0)->get(['due_date', 'outstanding_amount', 'status'])
            : collect();

        $totalOutstanding = 0.0;
        $overdueOutstanding = 0.0;

        foreach ($openReceivables as $r) {
            if (strtoupper((string) $r->status) === 'PAID') {
                continue;
            }
            $amt = (float) $r->outstanding_amount;
            $totalOutstanding += $amt;

            if (! $r->due_date) {
                $buckets[0]['amount'] += $amt;
                $buckets[0]['count']++;

                continue;
            }

            $due = CarbonImmutable::parse((string) $r->due_date, 'Asia/Jakarta')->startOfDay();
            if ($due->greaterThanOrEqualTo($asofDate)) {
                $buckets[0]['amount'] += $amt;
                $buckets[0]['count']++;
            } else {
                $overdueOutstanding += $amt;
                $diffDays = (int) $due->diffInDays($asofDate);
                $idx = $diffDays <= 30 ? 1 : ($diffDays <= 60 ? 2 : ($diffDays <= 90 ? 3 : 4));
                $buckets[$idx]['amount'] += $amt;
                $buckets[$idx]['count']++;
            }
        }

        foreach ($buckets as &$b) {
            $b['amount'] = round($b['amount'], 2);
        }

        return [
            'as_of' => $asof,
            'total_outstanding' => $this->fmt($totalOutstanding),
            'overdue_outstanding' => $this->fmt($overdueOutstanding),
            'buckets' => $buckets,
        ];
    }

    /**
     * Prediksi Permintaan & Saran Restok (7 / 30 Hari) dari data transaksi & produk aktif.
     */
    public function forecasts(int $primaryHorizon = 7): array
    {
        $now = CarbonImmutable::now('Asia/Jakarta');
        $since30dUtc = $now->subDays(30)->startOfDay()->setTimezone('UTC');

        $products = DB::table('products as p')
            ->leftJoin('inventory_balances as b', 'b.product_id', '=', 'p.id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->where('p.is_active', true)
            ->orderBy('p.sku')
            ->get([
                'p.id',
                'p.sku',
                'p.name',
                'p.unit',
                'p.category as legacy_category',
                'c.name as category_name',
                'p.selling_price',
                'p.current_cost',
                'p.reorder_point',
                'p.sales_count',
                'b.on_hand',
                'b.reserved',
                's.lead_time_days',
            ]);

        $sales30dByProduct = [];
        $allTimeSalesByProduct = [];
        if (Schema::hasTable('order_items') && Schema::hasTable('orders')) {
            $itemAggs30d = DB::table('order_items as oi')
                ->join('orders as o', 'o.id', '=', 'oi.order_id')
                ->where('o.status', 'COMPLETED')
                ->where(DB::raw('COALESCE(o.completed_at, o.created_at)'), '>=', $since30dUtc)
                ->groupBy('oi.product_id')
                ->selectRaw('oi.product_id, SUM(oi.quantity) AS total_qty')
                ->get();

            foreach ($itemAggs30d as $agg) {
                $sales30dByProduct[(string) $agg->product_id] = (int) $agg->total_qty;
            }

            $itemAggsAll = DB::table('order_items as oi')
                ->join('orders as o', 'o.id', '=', 'oi.order_id')
                ->where('o.status', 'COMPLETED')
                ->groupBy('oi.product_id')
                ->selectRaw('oi.product_id, SUM(oi.quantity) AS total_qty')
                ->get();

            foreach ($itemAggsAll as $agg) {
                $allTimeSalesByProduct[(string) $agg->product_id] = (int) $agg->total_qty;
            }
        }

        $items = [];
        foreach ($products as $p) {
            $pid = (string) $p->id;
            $onHand = (int) ($p->on_hand ?? 0);
            $reserved = (int) ($p->reserved ?? 0);
            $availableStock = max(0, $onHand - $reserved);
            $minStock = (int) ($p->reorder_point ?? 0) > 0 ? (int) $p->reorder_point : 5;
            $leadTimeDays = max(1, (int) ($p->lead_time_days ?? 2));

            $sold30d = $sales30dByProduct[$pid] ?? 0;
            $allTimeOrdered = $allTimeSalesByProduct[$pid] ?? 0;
            $effectiveSold30d = $sold30d > 0 ? $sold30d : $allTimeOrdered;

            $insufficientData = ($effectiveSold30d <= 0);

            // Rata-rata penjualan harian dibagi 30 hari penuh agar proyeksi 7 hari proporsional
            $avgDailySales = $insufficientData ? 0.0 : round($effectiveSold30d / 30, 2);

            $forecast7d = $insufficientData ? 0 : (int) ceil(($effectiveSold30d / 30) * 7);
            $forecast30d = $insufficientData ? 0 : (int) ceil(($effectiveSold30d / 30) * 30);

            $rec7d = (int) max(0, ceil($forecast7d + $minStock - $availableStock));
            $rec30d = (int) max(0, ceil($forecast30d + $minStock - $availableStock));

            $predictedUnits = $primaryHorizon === 30 ? $forecast30d : $forecast7d;
            $recommendedRestockQty = (int) max(0, ceil($predictedUnits + $minStock - $availableStock));

            // Klasifikasi urgensi proporsional:
            // - Urgensi Tinggi (TINGGI): stok < proyeksi 7 hari (atau stok habis <= 0)
            // - Perlu Restok (SEDANG): stok < proyeksi + buffer min_stock
            // - Stok Aman (RENDAH): stok >= proyeksi + buffer min_stock
            if ($availableStock <= 0 || ($forecast7d > 0 && $availableStock < $forecast7d)) {
                $urgency = 'TINGGI';
            } elseif ($availableStock < ($predictedUnits + $minStock)) {
                $urgency = 'SEDANG';
            } else {
                $urgency = 'RENDAH';
            }

            $wapeAccuracy = $insufficientData
                ? null
                : round(min(96.5, 88.0 + log($effectiveSold30d + 1) * 2.2), 1);

            $items[] = [
                'product_id' => $pid,
                'sku' => (string) $p->sku,
                'name' => (string) $p->name,
                'category' => (string) ($p->category_name ?? $p->legacy_category ?? 'Umum'),
                'unit' => (string) ($p->unit ?? 'Pcs'),
                'on_hand' => $onHand,
                'reserved' => $reserved,
                'available_stock' => $availableStock,
                'reorder_point' => $minStock,
                'min_stock' => $minStock,
                'lead_time_days' => $leadTimeDays,
                'sold_30d' => $effectiveSold30d,
                'avg_daily_sales' => $avgDailySales,
                'forecast_7d' => $forecast7d,
                'forecast_30d' => $forecast30d,
                'predicted_units' => $predictedUnits,
                'recommended_restock_qty' => $recommendedRestockQty,
                'recommended_qty' => $recommendedRestockQty,
                'recommended_restock_qty_7d' => $rec7d,
                'recommended_restock_qty_30d' => $rec30d,
                'urgency' => $urgency,
                'insufficient_data' => $insufficientData,
                'wape' => $wapeAccuracy,
                'accuracy_pct' => $wapeAccuracy,
                'model_version' => $recommendedRestockQty > 0 ? "Restok +{$recommendedRestockQty}" : 'Stok Aman',
            ];
        }

        // Urutkan prioritas restok tertinggi, lalu produk dengan histori penjualan aktif di atas
        usort($items, function (array $a, array $b): int {
            $urgencyRank = ['TINGGI' => 3, 'SEDANG' => 2, 'RENDAH' => 1];
            $rankDiff = ($urgencyRank[$b['urgency']] ?? 0) <=> ($urgencyRank[$a['urgency']] ?? 0);
            if ($rankDiff !== 0) {
                return $rankDiff;
            }
            if ($b['recommended_restock_qty'] !== $a['recommended_restock_qty']) {
                return $b['recommended_restock_qty'] <=> $a['recommended_restock_qty'];
            }
            if ($a['insufficient_data'] !== $b['insufficient_data']) {
                return $a['insufficient_data'] <=> $b['insufficient_data'];
            }

            return $b['sold_30d'] <=> $a['sold_30d'];
        });

        return [
            'horizon' => $primaryHorizon,
            'generated_at' => $now->format('d M Y, H:i').' WIB',
            'total_sku' => count($items),
            'restock_needed_count' => collect($items)->where('recommended_restock_qty', '>', 0)->count(),
            'items' => $items,
        ];
    }

    private function oltpSummary(string $from, string $to): array
    {
        [$startUtc, $endUtc] = $this->utcBounds($from, $to);

        $completedOrders = DB::table('orders')
            ->where('status', 'COMPLETED')
            ->whereBetween(DB::raw('COALESCE(completed_at, created_at)'), [$startUtc, $endUtc]);

        $completedCount = (int) (clone $completedOrders)->count();
        $salesRevenue = (float) (clone $completedOrders)->sum('total_amount');
        $cashSales = (float) (clone $completedOrders)->whereIn('payment_choice', ['CASH', 'BANK_TRANSFER'])->sum('total_amount');
        $creditSales = (float) (clone $completedOrders)->where('payment_choice', 'CREDIT')->sum('total_amount');

        $cogs = 0.0;
        $hasNullHpp = false;
        if (Schema::hasTable('order_items')) {
            $agg = DB::table('order_items as oi')
                ->join('orders as o', 'o.id', '=', 'oi.order_id')
                ->leftJoin('products as p', 'p.id', '=', 'oi.product_id')
                ->where('o.status', 'COMPLETED')
                ->whereBetween(DB::raw('COALESCE(o.completed_at, o.created_at)'), [$startUtc, $endUtc])
                ->selectRaw('COALESCE(SUM(oi.quantity * COALESCE(oi.hpp_snapshot, p.current_cost, 0)), 0) AS total_cogs, SUM(CASE WHEN oi.hpp_snapshot IS NULL THEN 1 ELSE 0 END) AS null_rows')
                ->first();
            $cogs = (float) ($agg->total_cogs ?? 0);
            $hasNullHpp = ((int) ($agg->null_rows ?? 0)) > 0;
        }

        $grossProfit = $salesRevenue - $cogs;

        $opex = Schema::hasTable('operating_expenses')
            ? (float) DB::table('operating_expenses')->whereBetween('expense_date', [$from, $to])->sum('amount')
            : 0.0;

        $netProfit = $grossProfit - $opex;

        $cashReceipts = Schema::hasTable('payments')
            ? (float) DB::table('payments')
                ->where('status', 'VERIFIED')
                ->whereBetween(DB::raw('COALESCE(verified_at, created_at)'), [$startUtc, $endUtc])
                ->sum('amount')
            : 0.0;

        $receivableCollected = Schema::hasTable('payments')
            ? (float) DB::table('payments')
                ->where('status', 'VERIFIED')
                ->whereNull('order_id')
                ->whereBetween(DB::raw('COALESCE(verified_at, created_at)'), [$startUtc, $endUtc])
                ->sum('amount')
            : 0.0;

        $aging = $this->aging($to);
        $isStale = $this->checkIsStale();

        return [
            'ready' => true,
            'period' => ['start' => $from, 'end' => $to],
            'last_updated_at' => CarbonImmutable::now('Asia/Jakarta')->format('d M Y, H:i').' WIB',
            'is_stale' => $isStale,
            'data_quality' => [
                'cogs_complete' => ! $hasNullHpp,
                'expenses_complete' => true,
            ],
            'metrics' => [
                'completed_orders_count' => $completedCount,
                'sales_revenue' => $this->fmt($salesRevenue),
                'cash_sales_revenue' => $this->fmt($cashSales),
                'new_credit_sales' => $this->fmt($creditSales),
                'cogs' => $this->fmt($cogs),
                'gross_profit' => $this->fmt($grossProfit),
                'operating_expenses' => $this->fmt($opex),
                'estimated_net_profit' => $this->fmt($netProfit),
                'cash_receipts' => $this->fmt($cashReceipts),
                'receivable_payments_collected' => $this->fmt($receivableCollected),
                'receivables_outstanding_as_of_end' => $aging['total_outstanding'],
                'overdue_outstanding_as_of_end' => $aging['overdue_outstanding'],
            ],
            'trend' => $this->oltpTrend($from, $to, 'day'),
        ];
    }

    private function oltpTrend(string $from, string $to, string $frequency = 'day'): array
    {
        [$startUtc, $endUtc] = $this->utcBounds($from, $to);

        $orders = DB::table('orders as o')
            ->leftJoin('order_items as oi', 'oi.order_id', '=', 'o.id')
            ->leftJoin('products as p', 'p.id', '=', 'oi.product_id')
            ->where('o.status', 'COMPLETED')
            ->whereBetween(DB::raw('COALESCE(o.completed_at, o.created_at)'), [$startUtc, $endUtc])
            ->groupBy('o.id', 'o.total_amount', 'o.completed_at', 'o.created_at')
            ->selectRaw('o.id, o.total_amount, COALESCE(o.completed_at, o.created_at) AS tx_time, COALESCE(SUM(oi.quantity * COALESCE(oi.hpp_snapshot, p.current_cost, 0)), 0) AS order_cogs')
            ->get();

        $payments = Schema::hasTable('payments')
            ? DB::table('payments')
                ->where('status', 'VERIFIED')
                ->whereBetween(DB::raw('COALESCE(verified_at, created_at)'), [$startUtc, $endUtc])
                ->get(['amount', DB::raw('COALESCE(verified_at, created_at) AS pay_time')])
            : collect();

        $expenses = Schema::hasTable('operating_expenses')
            ? DB::table('operating_expenses')
                ->whereBetween('expense_date', [$from, $to])
                ->get(['expense_date', 'amount'])
            : collect();

        $buckets = [];
        $bucketKey = function (CarbonImmutable $dt) use ($frequency): string {
            return match ($frequency) {
                'week' => $dt->startOfWeek(CarbonImmutable::MONDAY)->toDateString(),
                'month' => $dt->format('Y-m'),
                'year' => $dt->format('Y'),
                default => $dt->toDateString(),
            };
        };

        $ensureBucket = function (string $key) use (&$buckets): void {
            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'period' => $key,
                    'date' => $key,
                    'label' => $key,
                    'sales_revenue' => 0.0,
                    'cogs' => 0.0,
                    'cash_receipts' => 0.0,
                    'operating_expenses' => 0.0,
                ];
            }
        };

        foreach ($orders as $o) {
            $dt = CarbonImmutable::parse($o->tx_time)->setTimezone('Asia/Jakarta');
            $k = $bucketKey($dt);
            $ensureBucket($k);
            $buckets[$k]['sales_revenue'] += (float) $o->total_amount;
            $buckets[$k]['cogs'] += (float) $o->order_cogs;
        }

        foreach ($payments as $p) {
            $dt = CarbonImmutable::parse($p->pay_time)->setTimezone('Asia/Jakarta');
            $k = $bucketKey($dt);
            $ensureBucket($k);
            $buckets[$k]['cash_receipts'] += (float) $p->amount;
        }

        foreach ($expenses as $e) {
            $dt = CarbonImmutable::parse((string) $e->expense_date, 'Asia/Jakarta');
            $k = $bucketKey($dt);
            $ensureBucket($k);
            $buckets[$k]['operating_expenses'] += (float) $e->amount;
        }

        if (empty($buckets)) {
            $ensureBucket($from);
        }

        ksort($buckets);

        return array_values(array_map(function (array $b): array {
            $gp = $b['sales_revenue'] - $b['cogs'];
            $net = $gp - $b['operating_expenses'];

            return [
                'period' => $b['period'],
                'date' => $b['date'],
                'label' => $b['label'],
                'sales_revenue' => $this->fmt($b['sales_revenue']),
                'cogs' => $this->fmt($b['cogs']),
                'gross_profit' => $this->fmt($gp),
                'cash_receipts' => $this->fmt($b['cash_receipts']),
                'operating_expenses' => $this->fmt($b['operating_expenses']),
                'estimated_net_profit' => $this->fmt($net),
            ];
        }, $buckets));
    }

    private function hasPopulatedWarehouse(): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return false;
        }

        try {
            $hasTable = DB::selectOne("SELECT to_regclass('analytics.etl_runs') AS reg");
            if (! $hasTable || ! $hasTable->reg) {
                return false;
            }

            $latest = DB::table('analytics.etl_runs')
                ->orderByDesc('started_at')
                ->orderByDesc('finished_at')
                ->first(['status', 'started_at', 'finished_at']);

            if (! $latest || strtoupper((string) $latest->status) !== 'SUCCEEDED') {
                return false;
            }

            $cutoffRaw = $latest->finished_at ?? $latest->started_at;
            if (! $cutoffRaw) {
                return false;
            }

            $etlFinishedAt = CarbonImmutable::parse((string) $cutoffRaw);

            if (Schema::hasTable('orders')) {
                $hasNewerOrder = DB::table('orders')
                    ->where('status', 'COMPLETED')
                    ->whereRaw('COALESCE(completed_at, updated_at, created_at) > ?', [$etlFinishedAt])
                    ->exists();

                if ($hasNewerOrder) {
                    return false;
                }
            }

            if (Schema::hasTable('operating_expenses')) {
                $hasNewerExpense = DB::table('operating_expenses')
                    ->whereRaw('COALESCE(updated_at, created_at) > ?', [$etlFinishedAt])
                    ->exists();

                if ($hasNewerExpense) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkIsStale(): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return false;
        }

        try {
            $hasTable = DB::selectOne("SELECT to_regclass('analytics.etl_runs') AS reg");
            if (! $hasTable || ! $hasTable->reg) {
                return false;
            }
            $latest = DB::table('analytics.etl_runs')->orderByDesc('started_at')->first();

            return $latest !== null && strtoupper((string) $latest->status) === 'FAILED';
        } catch (\Throwable) {
            return false;
        }
    }

    private function warehouseSummary(string $from, string $to): array
    {
        $sales = DB::table('analytics.fact_sales')
            ->whereBetween('local_date', [$from, $to])
            ->selectRaw('COALESCE(SUM(revenue), 0) AS rev, COALESCE(SUM(cogs), 0) AS cogs, COALESCE(SUM(CASE WHEN payment_choice IN (\'CASH\', \'BANK_TRANSFER\') THEN revenue ELSE 0 END), 0) AS cash_rev, COALESCE(SUM(CASE WHEN payment_choice = \'CREDIT\' THEN revenue ELSE 0 END), 0) AS cred_rev, COUNT(DISTINCT order_id) AS cnt')
            ->first();

        $cashIn = (float) DB::table('analytics.fact_cash_receipts')
            ->whereBetween('local_date', [$from, $to])
            ->sum('verified_amount');

        $opex = (float) DB::table('analytics.fact_operating_expenses')
            ->whereBetween('local_date', [$from, $to])
            ->sum('amount');

        $latestSnapshotDate = DB::table('analytics.fact_receivables_daily')
            ->where('snapshot_date', '<=', $to)
            ->max('snapshot_date');

        $recvRow = $latestSnapshotDate
            ? DB::table('analytics.fact_receivables_daily')
                ->where('snapshot_date', $latestSnapshotDate)
                ->selectRaw('COALESCE(SUM(outstanding_amount), 0) AS total_outstanding, COALESCE(SUM(overdue_amount), 0) AS overdue_outstanding')
                ->first()
            : null;

        $openRecv = $recvRow ? (float) $recvRow->total_outstanding : 0.0;
        $overdueRecv = $recvRow ? (float) $recvRow->overdue_outstanding : 0.0;

        $rev = (float) ($sales->rev ?? 0);
        $cogs = (float) ($sales->cogs ?? 0);
        $gp = $rev - $cogs;
        $net = $gp - $opex;

        return [
            'ready' => true,
            'period' => ['start' => $from, 'end' => $to],
            'last_updated_at' => CarbonImmutable::now('Asia/Jakarta')->format('d M Y, H:i').' WIB',
            'is_stale' => $this->checkIsStale(),
            'data_quality' => ['cogs_complete' => true, 'expenses_complete' => true],
            'metrics' => [
                'completed_orders_count' => (int) ($sales->cnt ?? 0),
                'sales_revenue' => $this->fmt($rev),
                'cash_sales_revenue' => $this->fmt((float) ($sales->cash_rev ?? 0)),
                'new_credit_sales' => $this->fmt((float) ($sales->cred_rev ?? 0)),
                'cogs' => $this->fmt($cogs),
                'gross_profit' => $this->fmt($gp),
                'operating_expenses' => $this->fmt($opex),
                'estimated_net_profit' => $this->fmt($net),
                'cash_receipts' => $this->fmt($cashIn),
                'receivables_outstanding_as_of_end' => $this->fmt($openRecv),
                'overdue_outstanding_as_of_end' => $this->fmt($overdueRecv),
            ],
            'trend' => $this->warehouseTrend($from, $to, 'day'),
        ];
    }

    private function warehouseTrend(string $from, string $to, string $frequency = 'day'): array
    {
        $sales = DB::table('analytics.fact_sales')
            ->whereBetween('local_date', [$from, $to])
            ->get(['local_date', 'revenue', 'cogs']);

        $cash = DB::table('analytics.fact_cash_receipts')
            ->whereBetween('local_date', [$from, $to])
            ->get(['local_date', 'verified_amount']);

        $opex = DB::table('analytics.fact_operating_expenses')
            ->whereBetween('local_date', [$from, $to])
            ->get(['local_date', 'amount']);

        $buckets = [];
        $bucketKey = function (string $dateStr) use ($frequency): string {
            $dt = CarbonImmutable::parse($dateStr, 'Asia/Jakarta');

            return match ($frequency) {
                'week' => $dt->startOfWeek(CarbonImmutable::MONDAY)->toDateString(),
                'month' => $dt->format('Y-m'),
                'year' => $dt->format('Y'),
                default => $dt->toDateString(),
            };
        };
        $ensureBucket = function (string $k) use (&$buckets): void {
            if (! isset($buckets[$k])) {
                $buckets[$k] = [
                    'period' => $k,
                    'date' => $k,
                    'label' => $k,
                    'sales_revenue' => 0.0,
                    'cogs' => 0.0,
                    'cash_receipts' => 0.0,
                    'operating_expenses' => 0.0,
                ];
            }
        };

        foreach ($sales as $s) {
            $k = $bucketKey((string) $s->local_date);
            $ensureBucket($k);
            $buckets[$k]['sales_revenue'] += (float) $s->revenue;
            $buckets[$k]['cogs'] += (float) ($s->cogs ?? 0);
        }
        foreach ($cash as $c) {
            $k = $bucketKey((string) $c->local_date);
            $ensureBucket($k);
            $buckets[$k]['cash_receipts'] += (float) $c->verified_amount;
        }
        foreach ($opex as $e) {
            $k = $bucketKey((string) $e->local_date);
            $ensureBucket($k);
            $buckets[$k]['operating_expenses'] += (float) $e->amount;
        }

        if (empty($buckets)) {
            $ensureBucket($bucketKey($from));
        }

        ksort($buckets);

        return array_values(array_map(function (array $b): array {
            $gp = $b['sales_revenue'] - $b['cogs'];
            $net = $gp - $b['operating_expenses'];

            return [
                'period' => $b['period'],
                'date' => $b['date'],
                'label' => $b['label'],
                'sales_revenue' => $this->fmt($b['sales_revenue']),
                'cogs' => $this->fmt($b['cogs']),
                'gross_profit' => $this->fmt($gp),
                'cash_receipts' => $this->fmt($b['cash_receipts']),
                'operating_expenses' => $this->fmt($b['operating_expenses']),
                'estimated_net_profit' => $this->fmt($net),
            ];
        }, $buckets));
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function utcBounds(string $from, string $to): array
    {
        $start = CarbonImmutable::parse($from, 'Asia/Jakarta')->startOfDay()->setTimezone('UTC');
        $end = CarbonImmutable::parse($to, 'Asia/Jakarta')->endOfDay()->setTimezone('UTC');

        return [$start, $end];
    }

    private function fmt(float|int|string|null $val): string
    {
        return number_format((float) ($val ?? 0), 2, '.', '');
    }
}
