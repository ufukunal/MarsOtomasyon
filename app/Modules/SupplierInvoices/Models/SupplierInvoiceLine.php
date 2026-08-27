<?php

namespace App\Modules\SupplierInvoices\Models;

use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\TaxZeroReason;
use App\Modules\Products\Models\Product;
use App\Modules\PurchaseOrders\Models\PurchaseOrderLine;
use App\Modules\Quotes\Pricing\PriceBasis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SupplierInvoiceLine extends Model
{
    protected $fillable = [
        'company_id', 'supplier_invoice_id', 'purchase_order_id', 'purchase_order_line_id',
        'position', 'product_id', 'product_code', 'product_name', 'description', 'quantity',
        'price_basis', 'unit_price', 'line_discount_rate', 'tax_id', 'tax_code', 'tax_rate',
        'tax_is_zeroed', 'tax_zero_reason_id', 'tax_zero_reason_code', 'base_net',
        'line_discount_net', 'document_discount_net', 'net_total', 'tax_total', 'gross_total',
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

    /** @return BelongsTo<SupplierInvoice, $this> */
    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    /** @return BelongsTo<PurchaseOrderLine, $this> */
    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
