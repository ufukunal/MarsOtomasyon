<?php

namespace App\Modules\SalesInvoices\Models;

use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountAddress;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Currency;
use App\Modules\Dispatches\Models\Dispatch;
use App\Modules\SalesInvoices\Enums\SalesInvoiceMode;
use App\Modules\SalesInvoices\Enums\SalesInvoiceStatus;
use App\Modules\SalesOrders\Models\SalesOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class SalesInvoice extends Model
{
    protected $fillable = [
        'company_id', 'account_id', 'source_billing_address_id', 'source_sales_order_id', 'source_dispatch_id',
        'number', 'series_code', 'sequence_value', 'mode', 'status', 'invoice_date', 'currency_code',
        'document_discount_rate', 'base_net_total', 'line_discount_total', 'document_discount_total',
        'net_total', 'tax_total', 'gross_total', 'customer_legal_name', 'customer_trade_name',
        'customer_tax_identity_type', 'customer_tax_number', 'customer_tax_office', 'recipient_name',
        'address_line1', 'address_line2', 'district', 'city', 'postal_code', 'country_code', 'note',
    ];

    protected function casts(): array
    {
        return [
            'sequence_value' => 'integer',
            'mode' => SalesInvoiceMode::class,
            'status' => SalesInvoiceStatus::class,
            'invoice_date' => 'immutable_date',
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

    public function modeEnum(): SalesInvoiceMode
    {
        $raw = $this->getRawOriginal('mode');
        if (! is_string($raw)) {
            throw new LogicException('Persisted sales invoice mode must be a string.');
        }

        return SalesInvoiceMode::tryFrom($raw)
            ?? throw new LogicException('Persisted sales invoice mode is invalid.');
    }

    public function statusEnum(): SalesInvoiceStatus
    {
        $raw = $this->getRawOriginal('status');
        if (! is_string($raw)) {
            throw new LogicException('Persisted sales invoice status must be a string.');
        }

        return SalesInvoiceStatus::tryFrom($raw)
            ?? throw new LogicException('Persisted sales invoice status is invalid.');
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

    /** @return BelongsTo<AccountAddress, $this> */
    public function sourceBillingAddress(): BelongsTo
    {
        return $this->belongsTo(AccountAddress::class, 'source_billing_address_id');
    }

    /** @return BelongsTo<SalesOrder, $this> */
    public function sourceSalesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'source_sales_order_id');
    }

    /** @return BelongsTo<Dispatch, $this> */
    public function sourceDispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class, 'source_dispatch_id');
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /** @return HasMany<SalesInvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SalesInvoiceLine::class)->orderBy('position');
    }
}
