<?php

namespace App\Modules\GoodsReceipts\Models;

use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Models\Company;
use App\Modules\GoodsReceipts\Enums\GoodsReceiptStatus;
use App\Modules\PurchaseOrders\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class GoodsReceipt extends Model
{
    protected $fillable = [
        'company_id', 'purchase_order_id', 'account_id', 'number', 'series_code',
        'sequence_value', 'status', 'receipt_date', 'note', 'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => GoodsReceiptStatus::class,
            'sequence_value' => 'integer',
            'receipt_date' => 'immutable_date',
            'finalized_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function statusEnum(): GoodsReceiptStatus
    {
        $raw = $this->getRawOriginal('status');
        if (! is_string($raw)) {
            throw new LogicException('Persisted goods receipt status must be a string.');
        }

        return GoodsReceiptStatus::tryFrom($raw)
            ?? throw new LogicException('Persisted goods receipt status is invalid.');
    }

    public function isDraft(): bool
    {
        return $this->statusEnum() === GoodsReceiptStatus::Draft;
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

    /** @return HasMany<GoodsReceiptLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class)->orderBy('position');
    }
}
