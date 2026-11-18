<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasUuids;

    public bool $wasIdempotentReplay = false;

    protected $guarded = [];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function receivable()
    {
        return $this->hasOne(Receivable::class);
    }

    public function creditHold()
    {
        return $this->hasOne(CreditHold::class);
    }
}