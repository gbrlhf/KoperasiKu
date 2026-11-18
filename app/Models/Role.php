<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_code', 'user_id', 'code', 'id')
            ->withTimestamps();
    }
}
