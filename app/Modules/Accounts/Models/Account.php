<?php

namespace App\Modules\Accounts\Models;

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class Account extends Model
{
    protected $fillable = [
        'company_id',
        'code',
        'type',
        'status',
        'legal_name',
        'trade_name',
        'tax_identity_type',
        'tax_number',
        'tax_office',
        'book_currency_code',
        'due_days',
        'discount_rate',
        'risk_limit',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'status' => AccountStatus::class,
            'tax_identity_type' => TaxIdentityType::class,
            'due_days' => 'integer',
            'discount_rate' => 'decimal:6',
            'risk_limit' => 'decimal:6',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function typeEnum(): AccountType
    {
        $raw = $this->rawEnumValue('type');

        return AccountType::tryFrom($raw)
            ?? throw new LogicException('Persisted account type is invalid.');
    }

    public function statusEnum(): AccountStatus
    {
        $raw = $this->rawEnumValue('status');

        return AccountStatus::tryFrom($raw)
            ?? throw new LogicException('Persisted account status is invalid.');
    }

    public function taxIdentityTypeEnum(): TaxIdentityType
    {
        $raw = $this->rawEnumValue('tax_identity_type');

        return TaxIdentityType::tryFrom($raw)
            ?? throw new LogicException('Persisted account tax identity type is invalid.');
    }

    public function isActive(): bool
    {
        return $this->statusEnum() === AccountStatus::Active;
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Currency, $this> */
    public function bookCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'book_currency_code', 'code');
    }

    private function rawEnumValue(string $attribute): string
    {
        $raw = $this->getRawOriginal($attribute);
        if (! is_string($raw)) {
            throw new LogicException('Persisted account '.$attribute.' must be a string.');
        }

        return $raw;
    }
}
