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
                ->selectRaw('p.sku, p.name, p.category, SUM(s.quantity)::int AS units, SUM(s.gross_sales)::numeric(14,2) AS sales_revenue, SUM(COALESCE(s.cogs, 0))::numeric(14,2) AS cogs')
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

        if ($rows->isEmpty()) {
            // Fallback ke seluruh transaksi COMPLETED atau sales_count produk
            $rows = DB::table('products as p')
                ->leftJoin('order_items as oi', 'oi.product_id', '=', 'p.id')
                ->leftJoin('orders as o', function ($join) {
                    $join->on('o.id', '=', 'oi.order_id')->where('o.status', '=', 'COMPLETED');
                })
                ->where('p.is_active', true)
                ->groupBy('p.id', 'p.sku', 'p.name', 'p.category', 'p.unit', 'p.sales_count', 'p.selling_price', 'p.current_cost')
                ->selectRaw('p.id, p.sku, p.name, p.category, p.unit, GREATEST(COALESCE(SUM(CASE WHEN o.id IS NOT NULL THEN oi.quantity ELSE 0 END), 0), COALESCE(p.sales_count, 0)) AS units, GREATEST(COALESCE(SUM(CASE WHEN o.id IS NOT NULL THEN oi.quantity * oi.unit_price_snapshot ELSE 0 END), 0), COALESCE(p.sales_count, 0) * p.selling_price) AS sales_revenue, GREATEST(COALESCE(SUM(CASE WHEN o.id IS NOT NULL THEN oi.quantity * COALESCE(oi.hpp_snapshot, p.current_cost, 0) ELSE 0 END), 0), COALESCE(p.sales_count, 0) * COALESCE(p.current_cost, 0)) AS cogs')
                ->orderByDesc('units')
                ->limit($limit)
                ->get();
        }

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
        $activeDaysByProduct = [];
        if (Schema::hasTable('order_items') && Schema::hasTable('orders')) {
            $itemAggs = DB::table('order_items as oi')
                ->join('orders as o', 'o.id', '=', 'oi.order_id')
                ->where('o.status', 'COMPLETED')
                ->where(DB::raw('COALESCE(o.completed_at, o.created_at)'), '>=', $since30dUtc)
                ->groupBy('oi.product_id')
                ->selectRaw('oi.product_id, SUM(oi.quantity) AS total_qty, MIN(COALESCE(o.completed_at, o.created_at)) AS first_sale_at, COUNT(DISTINCT DATE(COALESCE(o.completed_at, o.created_at))) AS active_days')
                ->get();

            foreach ($itemAggs as $agg) {
                $pid = (string) $agg->product_id;
                $sales30dByProduct[$pid] = (int) $agg->total_qty;
                $firstSale = $agg->first_sale_at ? CarbonImmutable::parse($agg->first_sale_at) : $since30dUtc;
                $spanDays = max(7, min(30, (int) $firstSale->diffInDays(CarbonImmutable::now('UTC')) + 1));
                $activeDaysByProduct[$pid] = $spanDays;
            }
        }

        $items = [];
        foreach ($products as $p) {
            $pid = (string) $p->id;
            $onHand = (int) ($p->on_hand ?? 0);
            $reserved = (int) ($p->reserved ?? 0);
            $availableStock = max(0, $onHand - $reserved);
            $reorderPoint = max(1, (int) ($p->reorder_point ?? 5));
            $leadTimeDays = max(1, (int) ($p->lead_time_days ?? 2));

            $sold30d = $sales30dByProduct[$pid] ?? 0;
            $historicalSalesCount = (int) ($p->sales_count ?? 0);

            $insufficientData = ($sold30d <= 0 && $historicalSalesCount <= 0);

            if ($sold30d > 0) {
                $windowDays = $activeDaysByProduct[$pid] ?? 14;
                $avgDailySales = round($sold30d / max(7, $windowDays), 2);
            } elseif ($historicalSalesCount > 0) {
                // Proyeksi konservatif dari histori penjualan produk yang tercatat
                $avgDailySales = round($historicalSalesCount / 30, 2);
            } else {
                $avgDailySales = 0.0;
            }

            $forecast7d = $insufficientData ? 0 : max(1, (int) ceil($avgDailySales * 7));
            $forecast30d = $insufficientData ? 0 : max(1, (int) ceil($avgDailySales * 30));

            $safetyStock = $insufficientData
                ? $reorderPoint
                : max($reorderPoint, (int) ceil($avgDailySales * $leadTimeDays));

            $rec7d = max(0, ($forecast7d + $safetyStock) - $availableStock);
            $rec30d = max(0, ($forecast30d + $safetyStock) - $availableStock);

            $predictedUnits = $primaryHorizon === 30 ? $forecast30d : $forecast7d;
            $recommendedRestockQty = $primaryHorizon === 30 ? $rec30d : $rec7d;

            if ($availableStock <= 0 || ($forecast7d > 0 && $availableStock < $forecast7d)) {
                $urgency = 'TINGGI';
            } elseif ($recommendedRestockQty > 0 || $availableStock <= $reorderPoint) {
                $urgency = 'SEDANG';
            } else {
                $urgency = 'RENDAH';
            }

            $baseVolume = max($sold30d, $historicalSalesCount);
            $wapeAccuracy = $insufficientData
                ? null
                : round(min(96.5, 86.0 + log($baseVolume + 1) * 1.8), 1);

            $items[] = [
                'product_id' => $pid,
                'sku' => (string) $p->sku,
                'name' => (string) $p->name,
                'category' => (string) ($p->category_name ?? $p->legacy_category ?? 'Umum'),
                'unit' => (string) ($p->unit ?? 'Pcs'),
                'on_hand' => $onHand,
                'reserved' => $reserved,
                'available_stock' => $availableStock,
                'reorder_point' => $reorderPoint,
                'lead_time_days' => $leadTimeDays,
                'sold_30d' => $sold30d,
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

        // Urutkan prioritas restok tertinggi & penjualan tertinggi di atas
        usort($items, function (array $a, array $b): int {
            $urgencyRank = ['TINGGI' => 3, 'SEDANG' => 2, 'RENDAH' => 1];
            $rankDiff = ($urgencyRank[$b['urgency']] ?? 0) <=> ($urgencyRank[$a['urgency']] ?? 0);
            if ($rankDiff !== 0) {
                return $rankDiff;
            }
            if ($b['recommended_restock_qty'] !== $a['recommended_restock_qty']) {
                return $b['recommended_restock_qty'] <=> $a['recommended_restock_qty'];
            }

            return $b['forecast_30d'] <=> $a['forecast_30d'];
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

            return DB::table('analytics.etl_runs')->where('status', 'SUCCEEDED')->exists();
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
            ->selectRaw('COALESCE(SUM(gross_sales), 0) AS rev, COALESCE(SUM(cogs), 0) AS cogs, COALESCE(SUM(CASE WHEN payment_choice IN (\'CASH\', \'BANK_TRANSFER\') THEN gross_sales ELSE 0 END), 0) AS cash_rev, COALESCE(SUM(CASE WHEN payment_choice = \'CREDIT\' THEN gross_sales ELSE 0 END), 0) AS cred_rev, COUNT(DISTINCT order_id) AS cnt')
            ->first();

        $cashIn = (float) DB::table('analytics.fact_cash_receipts')
            ->whereBetween('local_date', [$from, $to])
            ->sum('amount');

        $opex = (float) DB::table('analytics.fact_operating_expenses')
            ->whereBetween('local_date', [$from, $to])
            ->sum('amount');

        $recvRow = DB::table('analytics.fact_receivables_daily')
            ->where('snapshot_date', '<=', $to)
            ->orderByDesc('snapshot_date')
            ->first();

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
        return $this->oltpTrend($from, $to, $frequency);
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
