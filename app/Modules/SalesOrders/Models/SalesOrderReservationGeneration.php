<?php

namespace App\Modules\SalesOrders\Models;

use App\Modules\Inventory\Models\StockReservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SalesOrderReservationGeneration extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id', 'sales_order_id', 'logical_line_key', 'generation', 'product_id',
        'warehouse_id', 'location_id', 'quantity', 'stock_reservation_id', 'released_at',
    ];

    protected function casts(): array
    {
        return [
            'generation' => 'integer',
            'quantity' => 'decimal:6',
            'released_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<SalesOrder, $this> */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /** @return BelongsTo<StockReservation, $this> */
    public function stockReservation(): BelongsTo
    {
        return $this->belongsTo(StockReservation::class);
    }
}
