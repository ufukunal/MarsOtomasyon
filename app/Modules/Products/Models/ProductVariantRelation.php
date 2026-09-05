<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProductVariantRelation extends Model
{
    protected $fillable = ['company_id', 'product_family_id', 'product_id', 'variant_signature'];

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<ProductFamily, $this> */
    public function family(): BelongsTo
    {
        return $this->belongsTo(ProductFamily::class, 'product_family_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<ProductVariantValueAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(ProductVariantValueAssignment::class);
    }
}
