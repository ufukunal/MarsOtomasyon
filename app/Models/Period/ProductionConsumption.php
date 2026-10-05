<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionConsumption extends PeriodModel
{
    protected $fillable = [
        'production_completion_id', 'component_product_id', 'location_id',
        'consumed_quantity', 'fire_quantity', 'unit_cost', 'total_cost', 'stock_movement_id',
    ];

    protected function casts(): array
    {
        return [
            'production_completion_id' => 'integer',
            'component_product_id' => 'integer',
            'location_id' => 'integer',
            'consumed_quantity' => 'decimal:3',
            'fire_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
            'stock_movement_id' => 'integer',
        ];
    }

    public function completion(): BelongsTo
    {
        return $this->belongsTo(ProductionCompletion::class, 'production_completion_id');
    }

    public function componentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }
}
