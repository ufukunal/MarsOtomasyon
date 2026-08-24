<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Enums\UserStatus;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

final class User extends Authenticatable
{
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'last_login_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyMembership::class);
    }
}
