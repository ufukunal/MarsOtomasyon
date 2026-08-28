<?php

namespace App\Modules\SalesReturns\Models;

use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Models\Product;
use App\Modules\SalesInvoices\Models\SalesInvoice;
use App\Modules\SalesInvoices\Models\SalesInvoiceLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SalesReturnLine extends Model
{
    protected $fillable = [
        'company_id', 'sales_return_id', 'sales_invoice_id', 'sales_invoice_line_id', 'position',
        'product_id', 'warehouse_id', 'location_id', 'product_code', 'product_name', 'reason_code',
        'condition_notes', 'quantity', 'accepted_quantity', 'rejected_quantity', 'restock_quantity',
        'requested_net', 'requested_tax', 'requested_gross', 'credited_net', 'credited_tax',
        'credited_gross', 'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'quantity' => 'decimal:6',
            'accepted_quantity' => 'decimal:6',
            'rejected_quantity' => 'decimal:6',
            'restock_quantity' => 'decimal:6',
            'requested_net' => 'decimal:6',
            'requested_tax' => 'decimal:6',
            'requested_gross' => 'decimal:6',
            'credited_net' => 'decimal:6',
            'credited_tax' => 'decimal:6',
            'credited_gross' => 'decimal:6',
            'unit_cost' => 'decimal:6',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<SalesReturn, $this> */
    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    /** @return BelongsTo<SalesInvoice, $this> */
    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    /** @return BelongsTo<SalesInvoiceLine, $this> */
    public function salesInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(SalesInvoiceLine::class);
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
