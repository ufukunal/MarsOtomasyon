<?php

namespace App\Modules\SalesInvoices\Models;

use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\TaxZeroReason;
use App\Modules\Dispatches\Models\Dispatch;
use App\Modules\Dispatches\Models\DispatchLine;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Models\Product;
use App\Modules\Quotes\Pricing\PriceBasis;
use App\Modules\SalesOrders\Models\SalesOrder;
use App\Modules\SalesOrders\Models\SalesOrderLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SalesInvoiceLine extends Model
{
    protected $fillable = [
        'company_id', 'sales_invoice_id', 'source_sales_order_id', 'source_sales_order_line_id',
        'source_dispatch_id', 'source_dispatch_line_id', 'position', 'product_id', 'warehouse_id',
        'location_id', 'product_code', 'product_name', 'description', 'quantity', 'price_basis', 'unit_price',
        'line_discount_rate', 'tax_id', 'tax_code', 'tax_rate', 'tax_is_zeroed', 'tax_zero_reason_id',
        'tax_zero_reason_code', 'base_net', 'line_discount_net', 'document_discount_net', 'net_total',
        'tax_total', 'gross_total',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'quantity' => 'decimal:6',
            'price_basis' => PriceBasis::class,
            'unit_price' => 'decimal:6',
            'line_discount_rate' => 'decimal:6',
            'tax_rate' => 'decimal:6',
            'tax_is_zeroed' => 'boolean',
            'base_net' => 'decimal:6',
            'line_discount_net' => 'decimal:6',
            'document_discount_net' => 'decimal:6',
            'net_total' => 'decimal:6',
            'tax_total' => 'decimal:6',
            'gross_total' => 'decimal:6',
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

    /** @return BelongsTo<Tax, $this> */
    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    /** @return BelongsTo<TaxZeroReason, $this> */
    public function taxZeroReason(): BelongsTo
    {
        return $this->belongsTo(TaxZeroReason::class);
    }
}
