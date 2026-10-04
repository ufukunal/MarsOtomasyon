<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSet extends PeriodModel
{
    use HasOptimisticLock;

    protected $fillable = ['set_product_id', 'component_product_id', 'quantity'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function setProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'set_product_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function componentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }
}
