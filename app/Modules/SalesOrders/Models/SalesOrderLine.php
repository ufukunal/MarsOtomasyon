<?php

namespace App\Modules\SalesOrders\Models;

use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\TaxZeroReason;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Models\Product;
use App\Modules\Quotes\Models\QuoteRevisionLine;
use App\Modules\Quotes\Pricing\PriceBasis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class SalesOrderLine extends Model
{
    protected $fillable = [
        'company_id', 'sales_order_id', 'source_quote_revision_line_id', 'logical_line_key', 'position',
        'product_id', 'warehouse_id', 'location_id', 'product_code', 'product_name', 'description', 'quantity',
        'price_basis', 'unit_price', 'line_discount_rate', 'tax_id', 'tax_code',
        'tax_rate', 'tax_zero_reason_id', 'tax_zero_reason_code', 'base_net',
        'line_discount_net', 'document_discount_net', 'net_total', 'tax_total', 'gross_total',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'price_basis' => PriceBasis::class,
            'quantity' => 'decimal:6',
            'unit_price' => 'decimal:6',
            'line_discount_rate' => 'decimal:6',
            'tax_rate' => 'decimal:6',
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

    /** @return BelongsTo<SalesOrder, $this> */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /** @return BelongsTo<QuoteRevisionLine, $this> */
    public function sourceRevisionLine(): BelongsTo
    {
        return $this->belongsTo(QuoteRevisionLine::class, 'source_quote_revision_line_id');
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

    /** @return HasOne<SalesOrderLineProgress, $this> */
    public function progress(): HasOne
    {
        return $this->hasOne(SalesOrderLineProgress::class, 'sales_order_line_id');
    }

    /** @return HasMany<SalesOrderLineProgressEffect, $this> */
    public function progressEffects(): HasMany
    {
        return $this->hasMany(SalesOrderLineProgressEffect::class, 'sales_order_line_id');
    }
}
