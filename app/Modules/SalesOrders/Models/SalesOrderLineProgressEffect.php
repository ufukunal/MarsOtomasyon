<?php

namespace App\Modules\SalesOrders\Models;

use App\Modules\Core\Models\Company;
use App\Modules\SalesOrders\Enums\SalesOrderProgressType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

final class SalesOrderLineProgressEffect extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'sales_order_id',
        'sales_order_line_id',
        'progress_type',
        'quantity_delta',
        'reversal_of_progress_effect_id',
        'operation_key',
        'request_fingerprint',
        'source_type',
        'source_id',
        'effect_type',
        'occurred_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'progress_type' => SalesOrderProgressType::class,
            'quantity_delta' => 'decimal:6',
            'reversal_of_progress_effect_id' => 'integer',
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function progressTypeEnum(): SalesOrderProgressType
    {
        $progressType = $this->getAttribute('progress_type');
        if (! $progressType instanceof SalesOrderProgressType) {
            throw new LogicException('Persisted sales order progress type is invalid.');
        }

        return $progressType;
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<SalesOrder, $this> */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /** @return BelongsTo<SalesOrderLine, $this> */
    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
    }

    /** @return BelongsTo<SalesOrderLineProgressEffect, $this> */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_progress_effect_id');
    }

    /** @return HasOne<SalesOrderLineProgressEffect, $this> */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_progress_effect_id');
    }
}
