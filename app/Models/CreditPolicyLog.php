<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CreditPolicyLog extends Model
{
    use HasUuids;

    protected $table = 'credit_policy_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'old_limit_amount' => 'float',
            'new_limit_amount' => 'float',
            'old_active' => 'boolean',
            'new_active' => 'boolean',
            'changed_at' => 'datetime',
        ];
    }
}
