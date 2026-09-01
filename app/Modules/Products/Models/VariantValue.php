<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VariantValue extends Model
{
    protected $fillable = ['company_id', 'variant_dimension_id', 'code', 'label', 'position'];

    /** @return BelongsTo<VariantDimension, $this> */
    public function dimension(): BelongsTo
    {
        return $this->belongsTo(VariantDimension::class, 'variant_dimension_id');
    }
}
