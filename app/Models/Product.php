<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'selling_price' => 'float',
        'original_price' => 'float',
        'current_cost' => 'float',
        'rating' => 'float',
        'is_active' => 'boolean',
    ];

    public function inventory()
    {
        return $this->hasOne(InventoryBalance::class, 'product_id', 'id');
    }

    public function categoryRelation()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function priceHistories()
    {
        return $this->hasMany(ProductPriceHistory::class, 'product_id');
    }
}
