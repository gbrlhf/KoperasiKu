<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasUuids;

    protected $fillable = ['code', 'name', 'phone', 'address', 'lead_time_days', 'is_active'];

    protected $casts = [
        'lead_time_days' => 'integer',
        'is_active' => 'boolean',
    ];
}