<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $quantity_basis
 * @property string $allocated_amount_base
 * @property string $applied_amount_base
 */
class ProductionServiceAllocation extends PeriodModel
{
    protected $fillable = [
        'production_order_id', 'purchase_invoice_id', 'purchase_invoice_line_id',
        'production_completion_id', 'quantity_basis', 'allocated_amount_base',
        'applied_amount_base',
    ];

    protected function casts(): array
    {
        return [
            'production_order_id' => 'integer',
            'purchase_invoice_id' => 'integer',
            'purchase_invoice_line_id' => 'integer',
            'production_completion_id' => 'integer',
            'quantity_basis' => 'decimal:3',
            'allocated_amount_base' => 'decimal:4',
            'applied_amount_base' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<ProductionOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    /** @return BelongsTo<Document, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'purchase_invoice_id');
    }

    /** @return BelongsTo<DocumentLine, $this> */
    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(DocumentLine::class, 'purchase_invoice_line_id');
    }

    /** @return BelongsTo<ProductionCompletion, $this> */
    public function completion(): BelongsTo
    {
        return $this->belongsTo(ProductionCompletion::class, 'production_completion_id');
    }
}
