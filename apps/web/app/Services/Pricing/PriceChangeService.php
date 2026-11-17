<?php

namespace App\Services\Pricing;

use App\Models\Product;
use App\Models\ProductPriceHistory;
use Illuminate\Support\Facades\DB;

class PriceChangeService
{
    public function updatePricing(
        string $productId,
        float $newSellPrice,
        ?float $newCost,
        string $reason,
        string $actorUserId
    ): array {
        return DB::transaction(function () use ($productId, $newSellPrice, $newCost, $reason, $actorUserId) {
            $product = Product::where('id', $productId)->lockForUpdate()->firstOrFail();

            $oldSell = (float) $product->selling_price;
            $oldCost = (float) $product->current_cost;
            $finalCost = $newCost !== null ? round($newCost, 2) : $oldCost;
            $finalSell = round($newSellPrice, 2);

            ProductPriceHistory::create([
                'product_id' => $product->id,
                'old_cost' => $oldCost,
                'new_cost' => $finalCost,
                'old_sell_price' => $oldSell,
                'new_sell_price' => $finalSell,
                'changed_by' => $actorUserId,
                'reason' => $reason,
                'changed_at' => now(),
            ]);

            $product->update([
                'selling_price' => $finalSell,
                'current_cost' => $finalCost,
            ]);

            $warnings = [];
            if ($finalSell < $finalCost) {
                $warnings[] = 'WARNING_SELLING_PRICE_BELOW_COST: Harga jual lebih rendah dari HPP acuan.';
            }

            return [
                'product' => $product->fresh()->makeVisible('current_cost'),
                'warnings' => $warnings,
            ];
        });
    }
}