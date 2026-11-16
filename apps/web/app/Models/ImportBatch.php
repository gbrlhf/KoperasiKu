<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'uploaded_by', 'file_name', 'file_path', 'checksum', 'import_type', 'status',
        'cutoff_at', 'total_rows', 'valid_rows', 'error_rows', 'duplicate_rows',
        'column_mapping', 'quality_metadata', 'committed_at',
    ];

    protected $casts = [
        'cutoff_at' => 'datetime',
        'committed_at' => 'datetime',
        'column_mapping' => 'array',
        'quality_metadata' => 'array',
    ];

    public function rows()
    {
        return $this->hasMany(ImportRow::class, 'batch_id');
    }
}