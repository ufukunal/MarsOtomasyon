<?php

namespace App\Modules\Quotes\Models;

use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\User;
use App\Modules\Quotes\Enums\QuoteStatus;
use App\Modules\SalesOrders\Models\SalesOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

final class Quote extends Model
{
    protected $fillable = [
        'company_id', 'account_id', 'number', 'series_code', 'sequence_value', 'status',
        'selected_revision_id', 'decision_by_user_id', 'decision_at', 'decision_note', 'converted_at',
        'quote_date', 'valid_until', 'currency_code', 'document_discount_rate',
        'base_net_total', 'line_discount_total', 'document_discount_total',
        'net_total', 'tax_total', 'gross_total', 'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'sequence_value' => 'integer',
            'selected_revision_id' => 'integer',
            'decision_by_user_id' => 'integer',
            'decision_at' => 'immutable_datetime',
            'converted_at' => 'immutable_datetime',
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

    public function isApproved(): bool
    {
        return $this->statusEnum() === QuoteStatus::Approved;
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

    /** @return BelongsTo<QuoteRevision, $this> */
    public function selectedRevision(): BelongsTo
    {
        return $this->belongsTo(QuoteRevision::class, 'selected_revision_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decisionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decision_by_user_id');
    }

    /** @return HasMany<QuoteLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)->orderBy('position');
    }

    /** @return HasMany<QuoteRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(QuoteRevision::class)->orderByDesc('revision_number');
    }

    /** @return HasOne<SalesOrder, $this> */
    public function salesOrder(): HasOne
    {
        return $this->hasOne(SalesOrder::class, 'source_quote_id');
    }
}
