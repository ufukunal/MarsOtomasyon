<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VariantValue extends Model
{
    protected $fillable = ['company_id', 'product_family_id', 'variant_dimension_id', 'code', 'label', 'position'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<ProductFamily, $this> */
    public function family(): BelongsTo
    {
        return $this->belongsTo(ProductFamily::class, 'product_family_id');
    }

    /** @return BelongsTo<VariantDimension, $this> */
    public function dimension(): BelongsTo
    {
        return $this->belongsTo(VariantDimension::class, 'variant_dimension_id');
    }
}
