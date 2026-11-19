<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProductPriceHistory extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'old_cost',
        'new_cost',
        'old_sell_price',
        'new_sell_price',
        'changed_by',
        'reason',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'old_cost' => 'decimal:2',
            'new_cost' => 'decimal:2',
            'old_sell_price' => 'decimal:2',
            'new_sell_price' => 'decimal:2',
            'changed_at' => 'datetime',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
