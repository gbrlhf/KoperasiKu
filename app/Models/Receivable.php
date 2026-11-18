<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Receivable extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'original_amount' => 'float',
            'outstanding_amount' => 'float',
            'due_date' => 'date:Y-m-d',
        ];
    }

    public function effectiveStatus(): string
    {
        if ((float) $this->outstanding_amount <= 0) {
            return 'PAID';
        }

        $todayJakarta = CarbonImmutable::now('Asia/Jakarta')->toDateString();
        if ($this->due_date && $this->due_date->toDateString() < $todayJakarta) {
            return 'OVERDUE';
        }

        return (float) $this->outstanding_amount < (float) $this->original_amount
            ? 'PARTIALLY_PAID'
            : 'OPEN';
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function allocations()
    {
        return $this->hasMany(ReceivablePaymentAllocation::class, 'receivable_id');
    }
}
