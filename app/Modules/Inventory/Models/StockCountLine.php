<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StockCountLine extends Model
{
    protected $fillable = [
        'company_id',
        'stock_count_id',
        'product_id',
        'expected_quantity',
        'expected_unit_cost',
        'expected_value',
        'counted_quantity',
        'valuation_unit_cost',
        'adjustment_movement_id',
    ];

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:6',
            'expected_unit_cost' => 'decimal:6',
            'expected_value' => 'decimal:6',
            'counted_quantity' => 'decimal:6',
            'valuation_unit_cost' => 'decimal:6',
            'variance_quantity' => 'decimal:6',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function adjustmentMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'adjustment_movement_id');
    }
}
