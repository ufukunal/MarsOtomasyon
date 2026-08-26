<?php

namespace App\Modules\SalesInvoices\Models;

use App\Modules\Dispatches\Models\Dispatch;
use App\Modules\Dispatches\Models\DispatchLine;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Models\Product;
use App\Modules\SalesOrders\Models\SalesOrder;
use App\Modules\SalesOrders\Models\SalesOrderLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SalesInvoiceLine extends Model
{
    protected $fillable = [
        'company_id', 'sales_invoice_id', 'source_sales_order_id', 'source_sales_order_line_id',
        'source_dispatch_id', 'source_dispatch_line_id', 'position', 'product_id', 'warehouse_id',
        'location_id', 'product_code', 'product_name', 'description', 'quantity',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'quantity' => 'decimal:6',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<SalesInvoice, $this> */
    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    /** @return BelongsTo<SalesOrder, $this> */
    public function sourceSalesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'source_sales_order_id');
    }

    /** @return BelongsTo<SalesOrderLine, $this> */
    public function sourceSalesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class, 'source_sales_order_line_id');
    }

    /** @return BelongsTo<Dispatch, $this> */
    public function sourceDispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class, 'source_dispatch_id');
    }

    /** @return BelongsTo<DispatchLine, $this> */
    public function sourceDispatchLine(): BelongsTo
    {
        return $this->belongsTo(DispatchLine::class, 'source_dispatch_line_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<WarehouseLocation, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }
}
