<?php

namespace App\Modules\Quotes\Models;

use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Currency;
use App\Modules\Quotes\Enums\QuoteStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class Quote extends Model
{
    protected $fillable = [
        'company_id', 'account_id', 'number', 'series_code', 'sequence_value', 'status',
        'quote_date', 'valid_until', 'currency_code', 'document_discount_rate',
        'base_net_total', 'line_discount_total', 'document_discount_total',
        'net_total', 'tax_total', 'gross_total', 'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'sequence_value' => 'integer',
            'quote_date' => 'immutable_date',
            'valid_until' => 'immutable_date',
            'document_discount_rate' => 'decimal:6',
            'base_net_total' => 'decimal:6',
            'line_discount_total' => 'decimal:6',
            'document_discount_total' => 'decimal:6',
            'net_total' => 'decimal:6',
            'tax_total' => 'decimal:6',
            'gross_total' => 'decimal:6',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function statusEnum(): QuoteStatus
    {
        $raw = $this->getRawOriginal('status');
        if (! is_string($raw)) {
            throw new LogicException('Persisted quote status must be a string.');
        }

        return QuoteStatus::tryFrom($raw)
            ?? throw new LogicException('Persisted quote status is invalid.');
    }

    public function isDraft(): bool
    {
        return $this->statusEnum() === QuoteStatus::Draft;
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /** @return HasMany<QuoteLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)->orderBy('position');
    }
}
