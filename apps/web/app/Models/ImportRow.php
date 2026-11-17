<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ImportRow extends Model
{
    use HasUuids;

    protected $fillable = ['batch_id', 'row_number', 'row_status', 'source_key', 'raw_data', 'normalized_data'];

    protected $casts = [
        'raw_data' => 'array',
        'normalized_data' => 'array',
    ];

    public function errors()
    {
        return $this->hasMany(ImportError::class, 'import_row_id');
    }
}