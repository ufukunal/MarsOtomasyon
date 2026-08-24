<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Enums\CompanyStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

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

    public function statusEnum(): CompanyStatus
    {
        $raw = $this->getRawOriginal('status');
        if (! is_string($raw)) {
            throw new LogicException('Persisted company status must be a string.');
        }

        return CompanyStatus::tryFrom($raw)
            ?? throw new LogicException('Persisted company status is invalid.');
    }

    public function isActive(): bool
    {
        return $this->statusEnum() === CompanyStatus::Active;
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

    /** @return HasMany<DocumentSequence, $this> */
    public function documentSequences(): HasMany
    {
        return $this->hasMany(DocumentSequence::class);
    }

    /** @return HasMany<Tax, $this> */
    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class);
    }

    /** @return HasMany<TaxZeroReason, $this> */
    public function taxZeroReasons(): HasMany
    {
        return $this->hasMany(TaxZeroReason::class);
    }

    /** @return HasMany<ExchangeRate, $this> */
    public function exchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class);
    }

    /** @return HasMany<PostingPeriod, $this> */
    public function postingPeriods(): HasMany
    {
        return $this->hasMany(PostingPeriod::class);
    }

    /** @return HasMany<AuditEntry, $this> */
    public function auditEntries(): HasMany
    {
        return $this->hasMany(AuditEntry::class);
    }
}
