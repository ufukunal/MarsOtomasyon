<?php

namespace App\Modules\SalesOrders\Models;

use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Currency;
use App\Modules\Dispatches\Models\Dispatch;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Quotes\Models\QuoteRevision;
use App\Modules\SalesInvoices\Models\SalesInvoice;
use App\Modules\SalesOrders\Enums\SalesOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class SalesOrder extends Model
{
    protected $fillable = [
        'company_id', 'account_id', 'number', 'series_code', 'sequence_value', 'status',
        'source_quote_id', 'source_quote_revision_id', 'order_date', 'currency_code',
        'document_discount_rate', 'base_net_total', 'line_discount_total',
        'document_discount_total', 'net_total', 'tax_total', 'gross_total', 'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => SalesOrderStatus::class,
            'sequence_value' => 'integer',
            'order_date' => 'immutable_date',
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

    public function statusEnum(): SalesOrderStatus
    {
        $raw = $this->getRawOriginal('status');
        if (! is_string($raw)) {
            throw new LogicException('Persisted sales order status must be a string.');
        }

        return SalesOrderStatus::tryFrom($raw)
            ?? throw new LogicException('Persisted sales order status is invalid.');
    }

    public function isDraft(): bool
    {
        return match ($this->statusEnum()) {
            SalesOrderStatus::Draft => true,
        };
    }

    public function isManual(): bool
    {
        return $this->getRawOriginal('source_quote_id') === null
            && $this->getRawOriginal('source_quote_revision_id') === null;
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

    /** @return BelongsTo<Quote, $this> */
    public function sourceQuote(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'source_quote_id');
    }

    /** @return BelongsTo<QuoteRevision, $this> */
    public function sourceRevision(): BelongsTo
    {
        return $this->belongsTo(QuoteRevision::class, 'source_quote_revision_id');
    }

    /** @return HasMany<SalesOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class)->orderBy('position');
    }

    /** @return HasMany<SalesOrderLineProgressEffect, $this> */
    public function progressEffects(): HasMany
    {
        return $this->hasMany(SalesOrderLineProgressEffect::class);
    }

    /** @return HasMany<Dispatch, $this> */
    public function dispatches(): HasMany
    {
        return $this->hasMany(Dispatch::class);
    }

    /** @return HasMany<SalesInvoice, $this> */
    public function salesInvoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class, 'source_sales_order_id');
    }
}
