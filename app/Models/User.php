<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
    use Notifiable;

    protected $connection = 'master';

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'last_company_id',
        'last_period_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_user')->withTimestamps();
    }

    public function accessiblePeriods(): BelongsToMany
    {
        return $this->belongsToMany(Period::class, 'period_user_access')
            ->withPivot(['is_active', 'permission_overrides'])
            ->withTimestamps();
    }

    public function lastCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'last_company_id');
    }

    public function lastPeriod(): BelongsTo
    {
        return $this->belongsTo(Period::class, 'last_period_id');
    }
}
