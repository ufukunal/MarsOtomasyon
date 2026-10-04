<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountLine extends PeriodModel
{
    protected $fillable = ['stock_count_id', 'product_id', 'system_quantity', 'counted_quantity', 'difference', 'is_approved', 'note'];

    protected function casts(): array
    {
        return ['system_quantity' => 'decimal:3', 'counted_quantity' => 'decimal:3', 'difference' => 'decimal:3', 'is_approved' => 'boolean'];
    }

    /** @return BelongsTo<StockCount, $this> */
    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
