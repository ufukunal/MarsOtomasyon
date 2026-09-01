<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProductFamily extends Model
{
    protected $fillable = ['company_id', 'code', 'name', 'shared_content'];

    protected function casts(): array
    {
        return ['shared_content' => 'array'];
    }

    /** @return HasMany<VariantDimension, $this> */
    public function dimensions(): HasMany
    {
        return $this->hasMany(VariantDimension::class);
    }

    /** @return HasMany<ProductVariantRelation, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariantRelation::class);
    }
}
