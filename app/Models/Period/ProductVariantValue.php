<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariantValue extends PeriodModel
{
    use HasOptimisticLock;

    protected $fillable = ['product_id', 'variant_attribute_id', 'value'];

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(VariantAttribute::class, 'variant_attribute_id');
    }
}
