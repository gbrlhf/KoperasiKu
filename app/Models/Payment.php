<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'payments';

    protected $fillable = [
        'order_id',
        'member_id',
        'amount',
        'method',
        'status',
        'verified_by',
    ];

    public function member()
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function allocations()
    {
        return $this->hasMany(ReceivablePaymentAllocation::class, 'payment_id');
    }
}