<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ReceiptItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'receipt_id',
        'product_id',
        'quantity',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function receipt()
    {
        return $this->belongsTo(PurchaseReceipt::class, 'receipt_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
