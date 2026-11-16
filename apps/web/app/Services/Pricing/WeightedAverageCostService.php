<?php

namespace App\Services\Pricing;

use App\Contracts\InventoryServiceInterface;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use Illuminate\Support\Facades\DB;

class WeightedAverageCostService
{
    public function __construct(
        protected InventoryServiceInterface $inventoryService
    ) {}

    public function receiveWithCostUpdate(
        string $productId,
        int $incomingQty,
        float $incomingUnitCost,
        string $receiptReference,
        string $actorUserId
    ): void {
        DB::transaction(function () use ($productId, $incomingQty, $incomingUnitCost, $receiptReference, $actorUserId) {
            $product = Product::where('id', $productId)->lockForUpdate()->firstOrFail();

            $currentOnHand = $this->inventoryService->getOnHand($productId);
            $oldCost = (float) $product->current_cost;

            $totalUnits = $currentOnHand + $incomingQty;
            $newCost = $totalUnits > 0
                ? round((($currentOnHand * $oldCost) + ($incomingQty * $incomingUnitCost)) / $totalUnits, 2)
                : round($incomingUnitCost, 2);

            if ($newCost !== $oldCost) {
                ProductPriceHistory::create([
                    'product_id' => $product->id,
                    'old_cost' => $oldCost,
                    'new_cost' => $newCost,
                    'old_sell_price' => $product->selling_price,
                    'new_sell_price' => $product->selling_price,
                    'changed_by' => $actorUserId,
                    'reason' => "Penerimaan stok supplier ({$receiptReference})",
                    'changed_at' => now(),
                ]);

                $product->update(['current_cost' => $newCost]);
            }

            $this->inventoryService->receive(
                productId: $product->id,
                quantity: $incomingQty,
                reason: 'PURCHASE_RECEIPT',
                actorUserId: $actorUserId,
                sourceReference: $receiptReference
            );
        });
    }
}