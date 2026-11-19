<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'name',
        'phone',
        'address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function purchaseReceipts()
    {
        return $this->hasMany(PurchaseReceipt::class);
    }
}
