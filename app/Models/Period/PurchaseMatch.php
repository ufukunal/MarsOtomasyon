<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $supplier_invoice_line_id
 * @property int $purchase_order_line_id
 * @property int $goods_receipt_line_id
 * @property int|null $product_id
 * @property string $matched_quantity
 * @property string $order_unit_price
 * @property string $invoice_unit_price
 * @property string $price_variance_rate
 * @property string|null $provisional_unit_cost_try
 * @property string|null $cost_unit_try
 * @property string|null $previous_moving_average
 * @property string|null $previous_last_purchase_price
 * @property \Illuminate\Support\Carbon|null $previous_last_purchase_at
 * @property string|null $cost_value_delta
 * @property string|null $new_moving_average
 */
class PurchaseMatch extends PeriodModel
{
    protected $fillable = [
        'supplier_invoice_line_id',
        'purchase_order_line_id',
        'goods_receipt_line_id',
        'product_id',
        'matched_quantity',
        'order_unit_price',
        'invoice_unit_price',
        'price_variance_rate',
        'provisional_unit_cost_try',
        'cost_unit_try',
        'previous_moving_average',
        'previous_last_purchase_price',
        'previous_last_purchase_at',
        'cost_value_delta',
        'new_moving_average',
        'created_by',
        'created_by_name',
    ];

    protected function casts(): array
    {
        return [
            'supplier_invoice_line_id' => 'integer',
            'purchase_order_line_id' => 'integer',
            'goods_receipt_line_id' => 'integer',
            'product_id' => 'integer',
            'matched_quantity' => 'decimal:3',
            'order_unit_price' => 'decimal:4',
            'invoice_unit_price' => 'decimal:4',
            'price_variance_rate' => 'decimal:4',
            'provisional_unit_cost_try' => 'decimal:4',
            'cost_unit_try' => 'decimal:4',
            'previous_moving_average' => 'decimal:4',
            'previous_last_purchase_price' => 'decimal:4',
            'previous_last_purchase_at' => 'datetime',
            'cost_value_delta' => 'decimal:4',
            'new_moving_average' => 'decimal:4',
            'created_by' => 'integer',
        ];
    }

    /** @return BelongsTo<DocumentLine, $this> */
    public function supplierInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(DocumentLine::class, 'supplier_invoice_line_id');
    }

    /** @return BelongsTo<DocumentLine, $this> */
    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(DocumentLine::class, 'purchase_order_line_id');
    }

    /** @return BelongsTo<DocumentLine, $this> */
    public function goodsReceiptLine(): BelongsTo
    {
        return $this->belongsTo(DocumentLine::class, 'goods_receipt_line_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
