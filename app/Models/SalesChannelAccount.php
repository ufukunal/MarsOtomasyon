<?php

namespace App\Models;

use App\Enums\SalesChannelPlatform;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

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

    protected static function booted(): void
    {
        static::updating(function (self $account): void {
            if ($account->isDirty('company_id') || $account->isDirty('platform')) {
                throw new LogicException('Kanal hesabının şirket/platform kimliği değiştirilemez.');
            }
        });

        static::deleting(fn (): never => throw new LogicException(
            'Kanal hesabı fiziksel silinemez; pasife alınmalıdır.',
        ));
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
