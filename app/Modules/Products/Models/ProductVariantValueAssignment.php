<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProductVariantValueAssignment extends Model
{
    protected $fillable = [
        'company_id',
        'product_family_id',
        'product_variant_relation_id',
        'variant_dimension_id',
        'variant_value_id',
    ];

    /** @return BelongsTo<ProductVariantRelation, $this> */
    public function relation(): BelongsTo
    {
        return $this->belongsTo(ProductVariantRelation::class, 'product_variant_relation_id');
    }

    /** @return BelongsTo<VariantDimension, $this> */
    public function dimension(): BelongsTo
    {
        return $this->belongsTo(VariantDimension::class, 'variant_dimension_id');
    }

    /** @return BelongsTo<VariantValue, $this> */
    public function value(): BelongsTo
    {
        return $this->belongsTo(VariantValue::class, 'variant_value_id');
    }
}
