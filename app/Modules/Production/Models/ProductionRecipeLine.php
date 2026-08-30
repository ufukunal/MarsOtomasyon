<?php

namespace App\Modules\Production\Models;

use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProductionRecipeLine extends Model
{
    protected $fillable = [
        'company_id',
        'recipe_id',
        'material_product_id',
        'quantity_per_batch',
    ];

    protected function casts(): array
    {
        return [
            'quantity_per_batch' => 'decimal:6',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<ProductionRecipe, $this> */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(ProductionRecipe::class, 'recipe_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function materialProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'material_product_id');
    }
}
