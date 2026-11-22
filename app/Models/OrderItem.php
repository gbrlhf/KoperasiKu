<?php

namespace App\Models;

use App\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(function (OrderItem $item): void {
            if ($item->isDirty('hpp_snapshot')) {
                $parentStatus = Order::where('id', $item->order_id)->value('status');
                if ($parentStatus === 'COMPLETED') {
                    throw new BusinessRuleException(
                        'HPP snapshot pada transaksi yang sudah COMPLETED bersifat final dan tidak boleh diubah.',
                        409,
                        'IMMUTABLE_HPP_SNAPSHOT'
                    );
                }
            }
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}