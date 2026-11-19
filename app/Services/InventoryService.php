<?php

namespace App\Services;

use App\Contracts\InventoryServiceInterface;
use App\Exceptions\BusinessRuleException;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Layanan inventori terpadu untuk root aplikasi (menjembatani kontrak PIC C dan saldo FASE B).
 * Menjaga hierarki lock kanonik: products -> inventory_balances agar bebas deadlock.
 */
class InventoryService implements InventoryServiceInterface
{
    public function receive(
        string $productId,
        int $quantity,
        string $reason,
        string $actorUserId,
        string $sourceReference
    ): void {
        if ($quantity <= 0) {
            throw new BusinessRuleException('Kuantitas penerimaan stok harus lebih dari nol.', 422, 'INVALID_QUANTITY');
        }

        DB::transaction(function () use ($productId, $quantity, $reason, $actorUserId, $sourceReference): void {
            // Kunci product terlebih dahulu untuk menyelaraskan urutan lock dengan TransactionService & WeightedAverageCostService
            $product = Product::where('id', $productId)->lockForUpdate()->first();
            if (!$product) {
                throw new BusinessRuleException('Produk tidak ditemukan untuk penerimaan stok.', 404, 'PRODUCT_NOT_FOUND');
            }

            $inventory = InventoryBalance::where('product_id', $product->id)->lockForUpdate()->first();
            if (!$inventory) {
                InventoryBalance::create([
                    'product_id' => $product->id,
                    'on_hand' => $quantity,
                    'reserved' => 0,
                ]);
            } else {
                $inventory->increment('on_hand', $quantity);
            }

            $cleanReason = trim($reason) !== '' ? trim($reason) : 'PURCHASE_RECEIPT';
            $movementType = in_array(strtoupper($cleanReason), ['PURCHASE_RECEIPT', 'ADJUSTMENT'], true)
                ? strtoupper($cleanReason)
                : 'PURCHASE_RECEIPT';

            $movement = StockMovement::create([
                'product_id' => $product->id,
                'quantity_change' => $quantity,
                'type' => $movementType,
                'reference_id' => Str::isUuid($sourceReference) ? $sourceReference : null,
                'reason' => trim("{$cleanReason} ({$sourceReference}) oleh {$actorUserId}"),
            ]);
            app(AuditService::class)->record('STOCK_RECEIVED', 'StockMovement', (string) $movement->id, null, [
                'product_id' => $productId, 'quantity' => $quantity, 'source_reference' => $sourceReference,
            ], $actorUserId);
        });
    }

    public function getOnHand(string $productId): int
    {
        $query = InventoryBalance::where('product_id', $productId);
        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }

        return (int) ($query->value('on_hand') ?? 0);
    }

    public function getAvailable(string $productId): int
    {
        $query = InventoryBalance::where('product_id', $productId);
        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }

        $balance = $query->first(['on_hand', 'reserved']);
        if (!$balance) {
            return 0;
        }

        return (int) $balance->on_hand - (int) $balance->reserved;
    }

    // Order orchestration already holds the product lock, and all changes belong to its transaction.
    public function reserve(string $orderId, string $productId, int $quantity): void
    {
        $this->requireTransaction();
        $balance = InventoryBalance::where('product_id', $productId)->lockForUpdate()->first();
        if (!$balance || $quantity <= 0 || $balance->on_hand - $balance->reserved < $quantity || $balance->reserved < 0) {
            throw new BusinessRuleException('Stok tersedia tidak mencukupi.', 422, 'INSUFFICIENT_STOCK');
        }
        $balance->increment('reserved', $quantity);
        StockReservation::create(['order_id' => $orderId, 'product_id' => $productId, 'quantity' => $quantity, 'status' => 'ACTIVE']);
    }

    public function fulfill(StockReservation $reservation, InventoryBalance $balance): StockMovement
    {
        $this->assertReservation($reservation, $balance);
        $balance->update([
            'on_hand' => (int) $balance->on_hand - (int) $reservation->quantity,
            'reserved' => (int) $balance->reserved - (int) $reservation->quantity,
        ]);
        $reservation->update(['status' => 'FULFILLED']);
        return StockMovement::create([
            'product_id' => $reservation->product_id, 'quantity_change' => -$reservation->quantity,
            'type' => 'SALE_PICKUP', 'reference_id' => $reservation->order_id, 'reason' => 'Serah terima pesanan selesai',
        ]);
    }

    public function release(StockReservation $reservation, InventoryBalance $balance): void
    {
        $this->assertReservation($reservation, $balance);
        $balance->decrement('reserved', $reservation->quantity);
        $reservation->update(['status' => 'CANCELLED']);
    }

    public function sell(InventoryBalance $balance, int $quantity, string $orderId): void
    {
        $this->requireTransaction();
        if ($quantity <= 0 || $balance->reserved < 0 || $balance->on_hand - $balance->reserved < $quantity) {
            throw new BusinessRuleException('Stok tersedia tidak mencukupi.', 422, 'INSUFFICIENT_STOCK');
        }
        $balance->decrement('on_hand', $quantity);
        StockMovement::create(['product_id' => $balance->product_id, 'quantity_change' => -$quantity,
            'type' => 'POS_SALE', 'reference_id' => $orderId, 'reason' => 'Penjualan langsung POS']);
    }

    public function adjust(string $productId, int $delta, string $reason, string $actorUserId, string $sourceReference): void
    {
        if ($delta === 0 || trim($reason) === '' || !Str::isUuid($sourceReference)) {
            throw new BusinessRuleException('Penyesuaian wajib memiliki delta, alasan dan referensi UUID.', 422, 'INVALID_ADJUSTMENT');
        }
        DB::transaction(function () use ($productId, $delta, $reason, $actorUserId, $sourceReference): void {
            Product::where('id', $productId)->lockForUpdate()->firstOrFail();
            $balance = InventoryBalance::where('product_id', $productId)->lockForUpdate()->firstOrFail();
            $movement = StockMovement::where('product_id', $productId)->where('type', 'ADJUSTMENT')->where('reference_id', $sourceReference)->first();
            if ($movement) {
                if ((int) $movement->quantity_change !== $delta || $movement->reason !== $reason) {
                    throw new BusinessRuleException('Referensi adjustment telah digunakan.', 409, 'IDEMPOTENCY_CONFLICT');
                }
                return;
            }
            $new = (int) $balance->on_hand + $delta;
            if ($new < $balance->reserved || $balance->reserved < 0 || $new > 2147483647) {
                throw new BusinessRuleException('Penyesuaian melanggar saldo reservasi.', 422, 'INSUFFICIENT_STOCK');
            }
            $old = (int) $balance->on_hand;
            $balance->update(['on_hand' => $new]);
            $movement = StockMovement::create(['product_id' => $productId, 'quantity_change' => $delta,
                'type' => 'ADJUSTMENT', 'reference_id' => $sourceReference, 'reason' => $reason]);
            app(AuditService::class)->record('STOCK_ADJUSTED', 'StockMovement', (string) $movement->id,
                ['on_hand' => $old], ['on_hand' => $new, 'reason' => $reason], $actorUserId);
        }, 3);
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Mutasi pesanan wajib berada dalam transaksi database.');
        }
    }

    private function assertReservation(StockReservation $reservation, InventoryBalance $balance): void
    {
        $this->requireTransaction();
        if ($reservation->status !== 'ACTIVE' || $reservation->product_id !== $balance->product_id
            || $reservation->quantity <= 0 || $balance->reserved < $reservation->quantity || $balance->on_hand < $balance->reserved) {
            throw new BusinessRuleException('Reservasi tidak valid.', 422, 'INVALID_RESERVATION');
        }
    }
}
