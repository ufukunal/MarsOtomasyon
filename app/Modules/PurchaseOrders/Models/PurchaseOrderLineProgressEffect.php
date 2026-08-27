<?php

namespace App\Modules\PurchaseOrders\Models;

use App\Modules\PurchaseOrders\Enums\PurchaseOrderProgressType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PurchaseOrderLineProgressEffect extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id', 'purchase_order_id', 'purchase_order_line_id', 'progress_type',
        'quantity_delta', 'operation_key', 'request_fingerprint', 'source_type', 'source_id',
        'effect_type', 'occurred_at', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'progress_type' => PurchaseOrderProgressType::class,
            'quantity_delta' => 'decimal:6',
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<PurchaseOrderLine, $this> */
    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }
}
