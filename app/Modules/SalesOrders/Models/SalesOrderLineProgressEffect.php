<?php

namespace App\Modules\SalesOrders\Models;

use App\Modules\Core\Models\Company;
use App\Modules\SalesOrders\Enums\SalesOrderProgressType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SalesOrderLineProgressEffect extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'sales_order_id',
        'sales_order_line_id',
        'progress_type',
        'quantity_delta',
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
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
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
}
