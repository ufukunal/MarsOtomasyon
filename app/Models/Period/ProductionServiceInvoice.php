<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionServiceInvoice extends PeriodModel
{
    protected $fillable = ['production_order_id', 'purchase_invoice_id'];

    protected function casts(): array
    {
        return [
            'production_order_id' => 'integer',
            'purchase_invoice_id' => 'integer',
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

    /** @return HasMany<ProductionServiceAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(
            ProductionServiceAllocation::class,
            'purchase_invoice_id',
            'purchase_invoice_id',
        )->where('production_order_id', $this->production_order_id);
    }
}
