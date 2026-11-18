<?php

namespace App\Services;

use App\Contracts\InventoryServiceInterface;
use App\Exceptions\BusinessRuleException;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockMovement;
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

            StockMovement::create([
                'product_id' => $product->id,
                'quantity_change' => $quantity,
                'type' => $movementType,
                'reference_id' => Str::isUuid($sourceReference) ? $sourceReference : null,
                'reason' => trim("{$cleanReason} ({$sourceReference}) oleh {$actorUserId}"),
            ]);
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

        return max(0, (int) $balance->on_hand - (int) $balance->reserved);
    }
}
