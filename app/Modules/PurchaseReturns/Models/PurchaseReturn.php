<?php

namespace App\Modules\PurchaseReturns\Models;

use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Currency;
use App\Modules\PurchaseOrders\Models\PurchaseOrder;
use App\Modules\PurchaseReturns\Enums\PurchaseReturnStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class PurchaseReturn extends Model
{
    protected $fillable = [
        'company_id', 'purchase_order_id', 'account_id', 'number', 'series_code', 'sequence_value',
        'status', 'finalized_at', 'return_date', 'currency_code', 'document_discount_rate',
        'base_net_total', 'line_discount_total', 'document_discount_total', 'net_total',
        'tax_total', 'gross_total', 'note',
    ];

    protected function casts(): array
    {
        return [
            'sequence_value' => 'integer',
            'status' => PurchaseReturnStatus::class,
            'finalized_at' => 'immutable_datetime',
            'return_date' => 'immutable_date',
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

    public function statusEnum(): PurchaseReturnStatus
    {
        $raw = $this->getRawOriginal('status');
        if (! is_string($raw)) {
            throw new LogicException('Persisted purchase return status must be a string.');
        }

        return PurchaseReturnStatus::tryFrom($raw)
            ?? throw new LogicException('Persisted purchase return status is invalid.');
    }

    public function isDraft(): bool
    {
        return $this->statusEnum() === PurchaseReturnStatus::Draft;
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
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

    /** @return HasMany<PurchaseReturnLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseReturnLine::class)->orderBy('position');
    }
}
