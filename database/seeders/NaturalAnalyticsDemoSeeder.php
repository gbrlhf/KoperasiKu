<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeder data transaksi 30 hari terakhir & biaya operasional yang idempoten
 * agar grafik penjualan, laporan keuangan, dan prediksi restok tampak realistis.
 */
class NaturalAnalyticsDemoSeeder extends Seeder
{
    private const ORDER_PREFIX = 'DEMO-2026-';
    private const EXPENSE_TAG = '[DEMO-2026]';
    private const IDEMPOTENCY_PREFIX = 'demo-2026-';

    public function run(): void
    {
        $nowWib = CarbonImmutable::now('Asia/Jakarta');

        DB::transaction(function () use ($nowWib): void {
            $this->cleanupPreviousDemoData();

            $productsBySku = DB::table('products')
                ->where('is_active', true)
                ->get(['id', 'sku', 'name', 'selling_price', 'current_cost'])
                ->keyBy('sku');

            if ($productsBySku->isEmpty()) {
                $this->command?->warn('Tidak ada produk aktif di database. Seeder dilewati.');

                return;
            }

            $cashierId = $this->resolveStaffUserId('CASHIER')
                ?? $this->resolveStaffUserId('MANAGER');

            $members = DB::table('members')
                ->where('status', 'ACTIVE')
                ->orderBy('member_number')
                ->get(['id', 'full_name'])
                ->values();

            // Target kuantitas 30 hari per SKU untuk membentuk sebaran urgensi natural:
            // - 3 Fast-moving Urgent Tinggi (MNY-001, AIR-001, TLR-001)
            // - 3 Perlu Restok (SEM-001, KCP-001, KPI-001)
            // - 4 Medium Stok Aman (GUL-001, TEH-001, SBN-001, KOP-001)
            // - Sisa SKU aktif menjadi Slow-moving (1-5 unit / 30 hari)
            $targetSkuQty = [
                'MNY-001' => 108,
                'AIR-001' => 78,
                'TLR-001' => 70,
                'SEM-001' => 108,
                'KCP-001' => 70,
                'KPI-001' => 62,
                'GUL-001' => 42,
                'TEH-001' => 40,
                'SBN-001' => 32,
                'KOP-001' => 30,
            ];

            $slowDefaults = [
                'BRS-005' => 5,
                'BRS-010' => 4,
                'BSK-001' => 4,
                'BSK-002' => 5,
                'GRM-001' => 5,
                'GUL-002' => 3,
                'KRP-001' => 5,
                'LNT-001' => 3,
                'MNY-002' => 5,
                'MRG-001' => 4,
                'PRG-001' => 4,
                'PST-001' => 3,
                'SBN-002' => 4,
                'SBN-003' => 3,
                'SOS-001' => 4,
                'SRD-001' => 4,
                'SSU-001' => 5,
                'SUS-001' => 5,
                'TIS-001' => 4,
                'TPG-001' => 5,
                'TSU-001' => 4,
            ];

            $skuTotals = [];
            foreach ($productsBySku as $sku => $prod) {
                if (isset($targetSkuQty[$sku])) {
                    $skuTotals[$sku] = $targetSkuQty[$sku];
                } else {
                    $skuTotals[$sku] = $slowDefaults[$sku] ?? ((crc32((string) $sku) % 5) + 1);
                }
            }

            // Jadwal 54 transaksi sepanjang 30 hari terakhir (lebih ramai di hari kerja & awal bulan)
            $orderSlots = $this->buildOrderSlots($nowWib, 54);
            $orderCount = count($orderSlots);

            // Alokasikan setiap SKU ke slot order secara merata dengan variasi jumlah item
            $orderBuckets = array_fill(0, $orderCount, []);
            $cursor = 0;
            foreach ($skuTotals as $sku => $totalQty) {
                $remaining = $totalQty;
                $isFast = $totalQty >= 60;
                $isMedium = $totalQty >= 20 && $totalQty < 60;

                while ($remaining > 0) {
                    $chunk = $isFast
                        ? min($remaining, ($cursor % 3) + 2)
                        : ($isMedium ? min($remaining, ($cursor % 2) + 1) : 1);

                    $slotIdx = ($cursor * ($isFast ? 3 : ($isMedium ? 5 : 7))) % $orderCount;
                    $orderBuckets[$slotIdx][$sku] = ($orderBuckets[$slotIdx][$sku] ?? 0) + $chunk;
                    $remaining -= $chunk;
                    $cursor++;
                }
            }

            $createdOrders = 0;
            foreach ($orderSlots as $idx => $slot) {
                $bucket = $orderBuckets[$idx];
                if (empty($bucket)) {
                    // Fallback agar tidak ada order kosong
                    $fallbackSku = $productsBySku->keys()->first();
                    $bucket[$fallbackSku] = 1;
                }

                $orderId = (string) Str::uuid();
                $orderNo = sprintf('%s%03d', self::ORDER_PREFIX, $idx + 1);
                $paymentChoice = $this->pickPaymentChoice($idx, $members->isNotEmpty());
                $channel = ($idx % 3 === 0 && $members->isNotEmpty()) ? 'ONLINE_PICKUP' : 'POS';
                $memberId = ($paymentChoice === 'CREDIT' || $channel === 'ONLINE_PICKUP' || $idx % 2 === 0) && $members->isNotEmpty()
                    ? (string) $members[$idx % $members->count()]->id
                    : null;

                $totalAmount = 0.0;
                $itemRows = [];
                $completedUtc = $slot['wib']->setTimezone('UTC')->format('Y-m-d H:i:s');
                $createdUtc = $channel === 'ONLINE_PICKUP'
                    ? $slot['wib']->subMinutes(25)->setTimezone('UTC')->format('Y-m-d H:i:s')
                    : $completedUtc;

                foreach ($bucket as $sku => $qty) {
                    $prod = $productsBySku->get($sku);
                    if (! $prod) {
                        continue;
                    }
                    $unitPrice = round((float) $prod->selling_price, 2);
                    $hpp = round((float) $prod->current_cost, 2);
                    $totalAmount += $unitPrice * $qty;

                    $itemRows[] = [
                        'id' => (string) Str::uuid(),
                        'order_id' => $orderId,
                        'product_id' => $prod->id,
                        'quantity' => $qty,
                        'unit_price_snapshot' => $unitPrice,
                        'hpp_snapshot' => $hpp,
                        'created_at' => $createdUtc,
                        'updated_at' => $completedUtc,
                    ];
                }

                DB::table('orders')->insert([
                    'id' => $orderId,
                    'member_id' => $memberId,
                    'channel' => $channel,
                    'status' => 'COMPLETED',
                    'payment_choice' => $paymentChoice,
                    'completed_at' => $completedUtc,
                    'created_at' => $createdUtc,
                    'updated_at' => $completedUtc,
                    'order_no' => $orderNo,
                    'pickup_code' => $channel === 'ONLINE_PICKUP' ? sprintf('DMO-%05d', $idx + 101) : null,
                    'total_amount' => round($totalAmount, 2),
                    'idempotency_key' => self::IDEMPOTENCY_PREFIX . 'ord-' . ($idx + 1),
                    'pickup_idempotency_key' => $channel === 'ONLINE_PICKUP' ? self::IDEMPOTENCY_PREFIX . 'pkp-' . ($idx + 1) : null,
                ]);

                DB::table('order_items')->insert($itemRows);

                if (in_array($paymentChoice, ['CASH', 'BANK_TRANSFER'], true)) {
                    DB::table('payments')->insert([
                        'id' => (string) Str::uuid(),
                        'order_id' => $orderId,
                        'member_id' => $memberId,
                        'amount' => round($totalAmount, 2),
                        'method' => $paymentChoice,
                        'status' => 'VERIFIED',
                        'verified_by' => $cashierId,
                        'external_reference' => $paymentChoice === 'BANK_TRANSFER' ? sprintf('TRF-DEMO-%03d', $idx + 1) : null,
                        'idempotency_key' => self::IDEMPOTENCY_PREFIX . 'pay-' . ($idx + 1),
                        'verified_at' => $completedUtc,
                        'created_at' => $completedUtc,
                        'updated_at' => $completedUtc,
                    ]);
                } elseif ($paymentChoice === 'CREDIT' && $memberId !== null) {
                    $receivableId = (string) Str::uuid();
                    $dueDate = $slot['wib']->addDays(7)->format('Y-m-d');
                    $isSettled = $slot['days_ago'] >= 4;

                    DB::table('receivables')->insert([
                        'id' => $receivableId,
                        'order_id' => $orderId,
                        'member_id' => $memberId,
                        'original_amount' => round($totalAmount, 2),
                        'outstanding_amount' => $isSettled ? 0.00 : round($totalAmount, 2),
                        'due_date' => $dueDate,
                        'status' => $isSettled ? 'PAID' : 'OPEN',
                        'created_at' => $completedUtc,
                        'updated_at' => $completedUtc,
                    ]);

                    if ($isSettled) {
                        $settledWib = $slot['wib']->addDays(2)->setTime(11, 15);
                        if ($settledWib->greaterThan($nowWib)) {
                            $settledWib = $nowWib->subHours(2);
                        }
                        $settledUtc = $settledWib->setTimezone('UTC')->format('Y-m-d H:i:s');
                        $payId = (string) Str::uuid();
                        $settleMethod = ($idx % 2 === 0) ? 'BANK_TRANSFER' : 'CASH';

                        DB::table('payments')->insert([
                            'id' => $payId,
                            'order_id' => null,
                            'member_id' => $memberId,
                            'amount' => round($totalAmount, 2),
                            'method' => $settleMethod,
                            'status' => 'VERIFIED',
                            'verified_by' => $cashierId,
                            'external_reference' => $settleMethod === 'BANK_TRANSFER' ? sprintf('REC-DEMO-%03d', $idx + 1) : null,
                            'idempotency_key' => self::IDEMPOTENCY_PREFIX . 'recpay-' . ($idx + 1),
                            'verified_at' => $settledUtc,
                            'created_at' => $settledUtc,
                            'updated_at' => $settledUtc,
                        ]);

                        DB::table('receivable_payment_allocations')->insert([
                            'id' => (string) Str::uuid(),
                            'payment_id' => $payId,
                            'receivable_id' => $receivableId,
                            'allocated_amount' => round($totalAmount, 2),
                            'created_at' => $settledUtc,
                            'updated_at' => $settledUtc,
                        ]);
                    }
                }

                $createdOrders++;
            }

            $this->seedOperatingExpenses($nowWib, $cashierId);

            $this->command?->info("NaturalAnalyticsDemoSeeder selesai: {$createdOrders} transaksi COMPLETED (30 hari terakhir) dan biaya operasional berhasil di-seed.");
        });
    }

    private function cleanupPreviousDemoData(): void
    {
        $demoOrderIds = DB::table('orders')
            ->where('order_no', 'like', self::ORDER_PREFIX . '%')
            ->pluck('id')
            ->all();

        if (! empty($demoOrderIds)) {
            $demoReceivableIds = DB::table('receivables')
                ->whereIn('order_id', $demoOrderIds)
                ->pluck('id')
                ->all();

            if (! empty($demoReceivableIds)) {
                DB::table('receivable_payment_allocations')
                    ->whereIn('receivable_id', $demoReceivableIds)
                    ->delete();
                DB::table('receivables')
                    ->whereIn('id', $demoReceivableIds)
                    ->delete();
            }

            DB::table('payments')
                ->whereIn('order_id', $demoOrderIds)
                ->delete();

            DB::table('order_items')
                ->whereIn('order_id', $demoOrderIds)
                ->delete();

            DB::table('orders')
                ->whereIn('id', $demoOrderIds)
                ->delete();
        }

        DB::table('payments')
            ->where('idempotency_key', 'like', self::IDEMPOTENCY_PREFIX . '%')
            ->delete();

        DB::table('operating_expenses')
            ->where('description', 'like', '%' . self::EXPENSE_TAG . '%')
            ->delete();
    }

    /**
     * Sebar slot transaksi sepanjang 30 hari terakhir (WIB):
     * lebih ramai di awal bulan & hari kerja, lebih sepi di akhir pekan.
     *
     * @return array<int, array{days_ago: int, wib: CarbonImmutable}>
     */
    private function buildOrderSlots(CarbonImmutable $nowWib, int $targetCount): array
    {
        $weights = [];
        for ($d = 29; $d >= 0; $d--) {
            $date = $nowWib->subDays($d);
            $dayOfMonth = (int) $date->format('j');
            $isWeekend = $date->isWeekend();

            if ($isWeekend) {
                $count = ($d % 4 === 0) ? 1 : 0;
            } elseif ($dayOfMonth <= 7 || $dayOfMonth >= 25) {
                $count = ($d % 2 === 0) ? 3 : 2;
            } else {
                $count = ($d % 3 === 0) ? 2 : 1;
            }

            // Pastikan hari ini ($d === 0) minimal memiliki 2 transaksi jika bukan akhir pekan, atau 1 jika akhir pekan
            if ($d === 0 && $count === 0) {
                $count = 1;
            }

            $weights[$d] = $count;
        }

        // Sesuaikan jumlah total agar tepat $targetCount
        $currentSum = array_sum($weights);
        $dCursor = 1;
        while ($currentSum < $targetCount) {
            $day = $nowWib->subDays($dCursor);
            if (! $day->isWeekend()) {
                $weights[$dCursor]++;
                $currentSum++;
            }
            $dCursor = ($dCursor + 3) % 29 + 1;
        }
        while ($currentSum > $targetCount) {
            if ($weights[$dCursor] > 1) {
                $weights[$dCursor]--;
                $currentSum--;
            }
            $dCursor = ($dCursor + 2) % 29 + 1;
        }

        $slots = [];
        foreach ($weights as $daysAgo => $count) {
            for ($i = 0; $i < $count; $i++) {
                $hour = min((int) $nowWib->format('H'), 9 + ($i * 3));
                if ($daysAgo > 0) {
                    $hour = 9 + (($i * 3 + $daysAgo) % 8);
                }
                $minute = (($daysAgo * 13) + ($i * 17)) % 55;
                $wib = $nowWib->subDays($daysAgo)->setTime(max(8, $hour), $minute, ($i * 11) % 50);
                if ($wib->greaterThan($nowWib)) {
                    $wib = $nowWib->subMinutes(15 + $i * 5);
                }
                $slots[] = ['days_ago' => $daysAgo, 'wib' => $wib];
            }
        }

        return $slots;
    }

    private function pickPaymentChoice(int $idx, bool $hasMembers): string
    {
        $mod = $idx % 10;
        if ($mod < 5) {
            return 'CASH';
        }
        if ($mod < 8) {
            return 'BANK_TRANSFER';
        }

        return $hasMembers ? 'CREDIT' : 'CASH';
    }

    private function seedOperatingExpenses(CarbonImmutable $nowWib, ?string $actorId): void
    {
        $monthStart = $nowWib->startOfMonth();
        $daysAvailable = max(1, $nowWib->day);

        $expenses = [
            [
                'day_offset' => min(2, $daysAvailable - 1),
                'category' => 'UTILITAS',
                'amount' => 425000,
                'description' => self::EXPENSE_TAG . ' Pembayaran token listrik & air gerai koperasi bulan berjalan',
            ],
            [
                'day_offset' => min(5, $daysAvailable - 1),
                'category' => 'PERLENGKAPAN',
                'amount' => 185000,
                'description' => self::EXPENSE_TAG . ' Pembelian kantong kemasan, plastik sembako, dan kertas struk kasir',
            ],
            [
                'day_offset' => min(9, $daysAvailable - 1),
                'category' => 'OPERASIONAL',
                'amount' => 150000,
                'description' => self::EXPENSE_TAG . ' Kebersihan rak display & perawatan area gudang gerai',
            ],
            [
                'day_offset' => max(0, $daysAvailable - 2),
                'category' => 'LOGISTIK',
                'amount' => 240000,
                'description' => self::EXPENSE_TAG . ' Biaya bongkar muat & pengantaran stok beras serta minyak goreng',
            ],
        ];

        $nowUtc = $nowWib->setTimezone('UTC')->format('Y-m-d H:i:s');
        foreach ($expenses as $e) {
            $expenseDate = $monthStart->addDays($e['day_offset']);
            if ($expenseDate->greaterThan($nowWib)) {
                $expenseDate = $nowWib;
            }

            DB::table('operating_expenses')->insert([
                'id' => (string) Str::uuid(),
                'created_by' => $actorId,
                'expense_date' => $expenseDate->format('Y-m-d'),
                'amount' => $e['amount'],
                'category' => $e['category'],
                'description' => $e['description'],
                'proof_path' => null,
                'created_at' => $nowUtc,
                'updated_at' => $nowUtc,
            ]);
        }
    }

    private function resolveStaffUserId(string $roleCode): ?string
    {
        return DB::table('user_roles')
            ->where('role_code', $roleCode)
            ->value('user_id');
    }
}
