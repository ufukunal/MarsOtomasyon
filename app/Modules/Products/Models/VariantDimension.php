<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class VariantDimension extends Model
{
    protected $fillable = ['company_id', 'product_family_id', 'code', 'name', 'position'];

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

    /** @return HasMany<VariantValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(VariantValue::class)->orderBy('position')->orderBy('id');
    }
}
