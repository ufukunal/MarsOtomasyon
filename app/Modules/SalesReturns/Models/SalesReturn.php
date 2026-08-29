<?php

namespace App\Modules\SalesReturns\Models;

use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Currency;
use App\Modules\SalesInvoices\Models\SalesInvoice;
use App\Modules\SalesReturns\Enums\SalesReturnStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class SalesReturn extends Model
{
    protected $fillable = [
        'company_id', 'sales_invoice_id', 'account_id', 'number', 'series_code', 'sequence_value',
        'status', 'return_date', 'currency_code', 'requested_net_total', 'requested_tax_total',
        'requested_gross_total', 'credited_net_total', 'credited_tax_total', 'credited_gross_total',
        'authorized_at', 'received_at', 'completed_at', 'cancelled_at', 'note',
    ];

    protected function casts(): array
    {
        return [
            'sequence_value' => 'integer',
            'status' => SalesReturnStatus::class,
            'return_date' => 'immutable_date',
            'requested_net_total' => 'decimal:6',
            'requested_tax_total' => 'decimal:6',
            'requested_gross_total' => 'decimal:6',
            'credited_net_total' => 'decimal:6',
            'credited_tax_total' => 'decimal:6',
            'credited_gross_total' => 'decimal:6',
            'authorized_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function statusEnum(): SalesReturnStatus
    {
        $raw = $this->getRawOriginal('status');
        if (! is_string($raw)) {
            throw new LogicException('Persisted sales return status must be a string.');
        }

        return SalesReturnStatus::tryFrom($raw)
            ?? throw new LogicException('Persisted sales return status is invalid.');
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<SalesInvoice, $this> */
    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
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

    /** @return HasMany<SalesReturnLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SalesReturnLine::class)->orderBy('position');
    }
}
