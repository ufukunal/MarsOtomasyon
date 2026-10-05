<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOutput extends PeriodModel
{
    protected $fillable = [
        'production_completion_id', 'location_id', 'quantity', 'stock_movement_id',
    ];

    protected function casts(): array
    {
        return [
            'production_completion_id' => 'integer',
            'location_id' => 'integer',
            'quantity' => 'decimal:3',
            'stock_movement_id' => 'integer',
        ];
    }

    public function completion(): BelongsTo
    {
        return $this->belongsTo(ProductionCompletion::class, 'production_completion_id');
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
