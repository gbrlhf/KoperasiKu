<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OperatingExpense extends Model
{
    use HasUuids;

    protected $fillable = [
        'created_by',
        'expense_date',
        'amount',
        'category',
        'description',
        'proof_path',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
