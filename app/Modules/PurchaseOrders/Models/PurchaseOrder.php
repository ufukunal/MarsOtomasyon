<?php

namespace App\Modules\PurchaseOrders\Models;

use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Currency;
use App\Modules\PurchaseOrders\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class PurchaseOrder extends Model
{
    protected $fillable = [
        'company_id', 'account_id', 'number', 'series_code', 'sequence_value', 'status',
        'order_date', 'currency_code', 'document_discount_rate', 'base_net_total',
        'line_discount_total', 'document_discount_total', 'net_total', 'tax_total',
        'gross_total', 'note', 'opened_at', 'opened_by_user_id', 'closed_at', 'closed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'sequence_value' => 'integer',
            'order_date' => 'immutable_date',
            'document_discount_rate' => 'decimal:6',
            'base_net_total' => 'decimal:6',
            'line_discount_total' => 'decimal:6',
            'document_discount_total' => 'decimal:6',
            'net_total' => 'decimal:6',
            'tax_total' => 'decimal:6',
            'gross_total' => 'decimal:6',
            'opened_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function statusEnum(): PurchaseOrderStatus
    {
        $raw = $this->getRawOriginal('status');
        if (! is_string($raw)) {
            throw new LogicException('Persisted purchase order status must be a string.');
        }

        return PurchaseOrderStatus::tryFrom($raw)
            ?? throw new LogicException('Persisted purchase order status is invalid.');
    }

    public function isDraft(): bool
    {
        return $this->statusEnum() === PurchaseOrderStatus::Draft;
    }

    public function isOpen(): bool
    {
        return $this->statusEnum() === PurchaseOrderStatus::Open;
    }

    public function isClosed(): bool
    {
        return $this->statusEnum() === PurchaseOrderStatus::Closed;
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

    /** @return HasMany<PurchaseOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('position');
    }

    /** @return HasMany<PurchaseOrderLineProgressEffect, $this> */
    public function progressEffects(): HasMany
    {
        return $this->hasMany(PurchaseOrderLineProgressEffect::class);
    }
}
