<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceivablePaymentAllocation extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'receivable_payment_allocations';

    protected $fillable = [
        'payment_id',
        'receivable_id',
        'allocated_amount',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function receivable()
    {
        return $this->belongsTo(Receivable::class, 'receivable_id');
    }
}