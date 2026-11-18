<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    protected $fillable = [
        'id',
        'email',
        'password_hash',
        'status',
        'email_verified_at',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    public function getAuthPassword(): string
    {
        return (string) $this->password_hash;
    }

    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password_hash'] = $value;
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_code', 'id', 'code')
            ->withTimestamps();
    }

    public function roleCodes(): array
    {
        return $this->roles()->pluck('roles.code')->map(fn ($c) => strtoupper((string) $c))->all();
    }

    public function hasRole(string $code): bool
    {
        return $this->roles()->where('roles.code', strtoupper($code))->exists();
    }

    public function hasAnyRole(array $codes): bool
    {
        $normalized = array_map(fn ($c) => strtoupper((string) $c), $codes);
        return $this->roles()->whereIn('roles.code', $normalized)->exists();
    }

    public function isActive(): bool
    {
        return strtoupper((string) ($this->status ?? 'active')) === 'ACTIVE';
    }

    public function member()
    {
        return $this->hasOne(Member::class, 'user_id', 'id');
    }
}
