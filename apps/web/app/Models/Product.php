<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasUuids;

    protected $fillable = [
        'category_id', 'supplier_id', 'sku', 'barcode', 'name',
        'unit', 'selling_price', 'current_cost', 'reorder_point', 'image_path', 'is_active',
    ];

    protected $hidden = ['current_cost'];

    protected $casts = [
        'selling_price' => 'decimal:2',
        'current_cost' => 'decimal:2',
        'reorder_point' => 'integer',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function priceHistories()
    {
        return $this->hasMany(ProductPriceHistory::class);
    }
}