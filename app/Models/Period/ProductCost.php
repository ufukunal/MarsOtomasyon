<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $product_id
 * @property string $last_purchase_price
 * @property string $moving_average
 * @property string $import_cost
 * @property string $production_cost
 * @property \Illuminate\Support\Carbon|null $last_purchase_at
 */
class ProductCost extends PeriodModel
{
    protected $fillable = [
        'product_id',
        'last_purchase_price',
        'moving_average',
        'import_cost',
        'production_cost',
        'last_purchase_at',
    ];

    protected function casts(): array
    {
        return [
            'last_purchase_price' => 'decimal:4',
            'moving_average' => 'decimal:4',
            'import_cost' => 'decimal:4',
            'production_cost' => 'decimal:4',
            'last_purchase_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
