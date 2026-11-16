<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MemberCreditAccount extends Model
{
    use HasUuids;

    protected $primaryKey = 'member_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    public function uniqueIds()
    {
        return ['member_id'];
    }
}