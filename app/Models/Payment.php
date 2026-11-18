<?php

namespace App\Models;

use Carbon\CarbonImmutable;
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
        'verified_at',
        'external_reference',
        'idempotency_key',
        'pending_allocations',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'verified_at' => 'datetime',
            'pending_allocations' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            if ($payment->status === 'VERIFIED' && empty($payment->verified_at)) {
                $payment->verified_at = CarbonImmutable::now('UTC');
            }
        });

        static::updating(function (Payment $payment): void {
            // Waktu verifikasi yang sudah tercatat tidak boleh ditulis ulang (kontrak PIC D).
            if ($payment->getOriginal('status') === 'VERIFIED' && $payment->isDirty('verified_at')) {
                $payment->verified_at = $payment->getOriginal('verified_at');
            }

            if ($payment->isDirty('status') && $payment->status === 'VERIFIED' && empty($payment->verified_at)) {
                $payment->verified_at = CarbonImmutable::now('UTC');
            }
        });
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function allocations()
    {
        return $this->hasMany(ReceivablePaymentAllocation::class, 'payment_id');
    }
}
