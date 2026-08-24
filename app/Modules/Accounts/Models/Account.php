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
        return $this->enumFromRaw('type', AccountType::class);
    }

    public function statusEnum(): AccountStatus
    {
        return $this->enumFromRaw('status', AccountStatus::class);
    }

    public function taxIdentityTypeEnum(): TaxIdentityType
    {
        return $this->enumFromRaw('tax_identity_type', TaxIdentityType::class);
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

    /**
     * @template TEnum of \BackedEnum
     * @param class-string<TEnum> $enumClass
     * @return TEnum
     */
    private function enumFromRaw(string $attribute, string $enumClass): \BackedEnum
    {
        $raw = $this->getRawOriginal($attribute);
        if (! is_string($raw)) {
            throw new LogicException('Persisted account '.$attribute.' must be a string.');
        }

        return $enumClass::tryFrom($raw)
            ?? throw new LogicException('Persisted account '.$attribute.' is invalid.');
    }
}
