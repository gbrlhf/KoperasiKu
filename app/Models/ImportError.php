<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ImportError extends Model
{
    use HasUuids;

    protected $fillable = [
        'import_row_id',
        'field_name',
        'error_code',
        'error_message',
    ];

    public function row()
    {
        return $this->belongsTo(ImportRow::class, 'import_row_id');
    }
}
