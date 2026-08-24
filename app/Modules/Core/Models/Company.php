<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Enums\CompanyStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Company extends Model
{
    protected $fillable = [
        'code',
        'name',
        'status',
        'base_currency_code',
        'timezone',
    ];

    protected function casts(): array
    {
        return [
            'status' => CompanyStatus::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<Branch, $this> */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /** @return HasMany<CompanyMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyMembership::class);
    }

    /** @return HasMany<Role, $this> */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }
}
