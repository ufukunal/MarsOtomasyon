<?php

namespace App\Models;

use App\Enums\SalesChannelPlatform;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesChannelAccount extends MasterModel
{
    use HasOptimisticLock;

    protected $fillable = [
        'company_id',
        'platform',
        'name',
        'external_store_id',
        'credentials_encrypted',
        'settings',
        'is_active',
        'version',
    ];

    protected $hidden = [
        'credentials_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'platform' => SalesChannelPlatform::class,
            'credentials_encrypted' => 'encrypted:array',
            'settings' => 'array',
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function externalEvents(): HasMany
    {
        return $this->hasMany(ChannelExternalEventRegistry::class, 'channel_account_id');
    }

    /** @return array<string, mixed> */
    public function credentials(): array
    {
        $value = $this->getAttribute('credentials_encrypted');

        return is_array($value) ? $value : [];
    }
}
