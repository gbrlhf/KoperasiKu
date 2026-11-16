<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class InventoryBalance extends Model
{
    use HasUuids;

    protected $primaryKey = 'product_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    public function uniqueIds()
    {
        return ['product_id'];
    }
}