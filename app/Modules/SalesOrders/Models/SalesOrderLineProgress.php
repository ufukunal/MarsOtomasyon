<?php

namespace App\Modules\SalesOrders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SalesOrderLineProgress extends Model
{
    protected $table = 'sales_order_line_progress';

    protected $primaryKey = 'sales_order_line_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ordered_quantity' => 'decimal:6',
            'net_dispatched_quantity' => 'decimal:6',
            'net_invoiced_quantity' => 'decimal:6',
            'cancelled_quantity' => 'decimal:6',
            'dispatch_remaining_quantity' => 'decimal:6',
            'invoice_remaining_quantity' => 'decimal:6',
            'remaining_quantity' => 'decimal:6',
        ];
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
