<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderComponent extends PeriodModel
{
    protected $fillable = [
        'production_order_id', 'component_product_id', 'unit_id',
        'planned_quantity', 'planned_base_quantity', 'conversion_factor',
    ];

    protected function casts(): array
    {
        return [
            'production_order_id' => 'integer',
            'component_product_id' => 'integer',
            'unit_id' => 'integer',
            'planned_quantity' => 'decimal:3',
            'planned_base_quantity' => 'decimal:3',
            'conversion_factor' => 'decimal:6',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function componentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
