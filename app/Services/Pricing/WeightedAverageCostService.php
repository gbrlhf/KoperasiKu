<?php

namespace App\Services\Pricing;

use App\Exceptions\BusinessRuleException;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;

class WeightedAverageCostService
{
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {}

    /**
     * Formula WAC:
     * new_cost = ((old_on_hand * old_cost) + (received_qty * unit_cost)) / (old_on_hand + received_qty)
     *
     * @return array{old_wac: float, new_wac: float}
     */
    public function receiveWithCostUpdate(
        string $productId,
        int $receivedQty,
        float $unitCost,
        string $referenceType,
        string $referenceId,
        ?string $actorUserId = null,
        ?string $reason = null
    ): array {
        if ($receivedQty <= 0) {
            throw new BusinessRuleException('Kuantitas penerimaan harus lebih dari 0.');
        }

        if ($unitCost < 0) {
            throw new BusinessRuleException('Harga beli satuan tidak boleh negatif.');
        }

        return DB::transaction(function () use ($productId, $receivedQty, $unitCost, $referenceType, $referenceId, $actorUserId, $reason): array {
            $product = Product::query()->whereKey($productId)->lockForUpdate()->firstOrFail();
            $balance = InventoryBalance::query()->where('product_id', $productId)->lockForUpdate()->first();

            $oldOnHand = max(0, (int) ($balance?->on_hand ?? 0));
            $oldCost = (float) $product->current_cost;

            $totalNewStock = $oldOnHand + $receivedQty;
            $newCost = $totalNewStock > 0
                ? round((($oldOnHand * $oldCost) + ($receivedQty * $unitCost)) / $totalNewStock, 2)
                : round($unitCost, 2);

            $this->inventoryService->receive(
                productId: $productId,
                quantity: $receivedQty,
                reason: $reason ?? 'PURCHASE_RECEIPT',
                actorUserId: (string) ($actorUserId ?? 'SYSTEM'),
                sourceReference: $referenceId
            );

            $product->update([
                'current_cost' => $newCost,
            ]);

            if (round($oldCost, 2) !== $newCost) {
                ProductPriceHistory::create([
                    'product_id' => $product->id,
                    'old_sell_price' => $product->selling_price,
                    'new_sell_price' => $product->selling_price,
                    'old_cost' => $oldCost,
                    'new_cost' => $newCost,
                    'changed_by' => $actorUserId,
                    'reason' => "Update HPP Rata-rata Tertimbang (WAC) dari penerimaan #{$referenceId}",
                    'changed_at' => now(),
                ]);
            }

            return [
                'old_wac' => $oldCost,
                'new_wac' => $newCost,
            ];
        });
    }
}
