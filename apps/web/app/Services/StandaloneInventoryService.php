<?php

namespace App\Services;

use App\Contracts\InventoryServiceInterface;
use Illuminate\Support\Facades\DB;

class StandaloneInventoryService implements InventoryServiceInterface
{
    public function receive(
        string $productId,
        int $quantity,
        string $reason,
        string $actorUserId,
        string $sourceReference
    ): void {
        DB::transaction(function () use ($productId, $quantity) {
            $row = DB::table('inventory_balances')
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (!$row) {
                DB::table('inventory_balances')->insert([
                    'product_id' => $productId,
                    'on_hand' => $quantity,
                    'reserved' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('inventory_balances')
                    ->where('product_id', $productId)
                    ->update([
                        'on_hand' => $row->on_hand + $quantity,
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    public function getOnHand(string $productId): int
    {
        return (int) (DB::table('inventory_balances')
            ->where('product_id', $productId)
            ->value('on_hand') ?? 0);
    }
}