<?php

namespace App\Console\Commands;

use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Services\TransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpirePendingOrdersCommand extends Command
{
    protected $signature = 'orders:expire-pending {--hours=24 : Batas umur pesanan PENDING_PAYMENT dalam jam}';

    protected $description = 'Membatalkan otomatis pesanan ONLINE_PICKUP berstatus PENDING_PAYMENT yang melewati batas waktu agar stok dan credit hold terlepas.';

    public function handle(TransactionService $transactionService): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $cutoffUtc = CarbonImmutable::now('UTC')->subHours($hours);

        $expiredOrderIds = Order::query()
            ->where('channel', 'ONLINE_PICKUP')
            ->where('status', 'PENDING_PAYMENT')
            ->where('created_at', '<', $cutoffUtc)
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('payments')
                    ->whereColumn('payments.order_id', 'orders.id')
                    ->whereIn('payments.status', ['VERIFIED', 'PENDING_VERIFICATION']);
            })
            ->orderBy('created_at')
            ->pluck('id');

        $cancelled = 0;
        $skipped = 0;

        foreach ($expiredOrderIds as $orderId) {
            try {
                $res = $transactionService->cancelOrder((string) $orderId, null);
                if (! ($res['idempotent'] ?? false)) {
                    $cancelled++;
                }
            } catch (BusinessRuleException $e) {
                $skipped++;
                Log::info('Skip auto-expire pending order', [
                    'order_id' => (string) $orderId,
                    'error_code' => $e->errorCode,
                    'message' => $e->getMessage(),
                ]);
            } catch (\Throwable $e) {
                $skipped++;
                Log::warning('Failed auto-expire pending order', [
                    'order_id' => (string) $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Selesai memeriksa pesanan kedaluwarsa (> {$hours} jam): {$cancelled} dibatalkan, {$skipped} dilewati.");

        return self::SUCCESS;
    }
}
