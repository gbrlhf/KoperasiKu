<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PurchaseReceipt extends Model
{
    use HasUuids;

    protected $fillable = ['receipt_number', 'supplier_id', 'received_at', 'status', 'notes', 'created_by'];

    protected $casts = ['received_at' => 'datetime'];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(ReceiptItem::class, 'receipt_id');
    }
}