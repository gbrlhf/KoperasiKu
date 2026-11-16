<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'employee_number', 'member_number', 'full_name', 'department', 'status'];
}