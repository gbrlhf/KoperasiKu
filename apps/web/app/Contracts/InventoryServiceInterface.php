<?php

namespace App\Contracts;

interface InventoryServiceInterface
{
    public function receive(
        string $productId,
        int $quantity,
        string $reason,
        string $actorUserId,
        string $sourceReference
    ): void;

    public function getOnHand(string $productId): int;
}