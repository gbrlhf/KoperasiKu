<?php

namespace App\Services\Pricing;

use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

class PriceChangeService
{
    public function __construct(
        private readonly AuditService $auditService
    ) {}

    public function updateSellingPrice(Product $product, float $newSellingPrice, ?string $actorUserId, ?string $reason = null): Product
    {
        return DB::transaction(function () use ($product, $newSellingPrice, $actorUserId, $reason): Product {
            $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            $oldSellingPrice = (float) $lockedProduct->selling_price;
            $currentCost = (float) $lockedProduct->current_cost;

            if (round($oldSellingPrice, 2) === round($newSellingPrice, 2)) {
                return $lockedProduct;
            }

            $lockedProduct->update([
                'selling_price' => round($newSellingPrice, 2),
            ]);

            $history = ProductPriceHistory::create([
                'product_id' => $lockedProduct->id,
                'old_sell_price' => $oldSellingPrice,
                'new_sell_price' => round($newSellingPrice, 2),
                'old_cost' => $currentCost,
                'new_cost' => $currentCost,
                'changed_by' => $actorUserId,
                'reason' => $reason ?? 'Perubahan harga jual oleh Manajer',
                'changed_at' => now(),
            ]);

            $this->auditService->record(
                action: 'PRODUCT_SELLING_PRICE_UPDATED',
                entityType: 'product',
                entityId: (string) $lockedProduct->id,
                oldValues: ['selling_price' => $oldSellingPrice],
                newValues: [
                    'history_id' => $history->id,
                    'selling_price' => round($newSellingPrice, 2),
                    'reason' => $reason,
                ],
                actorId: $actorUserId
            );

            return $lockedProduct->fresh();
        });
    }
}
