<?php

namespace App\Modules\Production\Models;

use App\Modules\Core\Models\Company;
use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProductionRecipe extends Model
{
    protected $fillable = [
        'company_id',
        'product_id',
        'code',
        'name',
        'output_quantity',
        'is_active',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'output_quantity' => 'decimal:6',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<ProductionRecipeLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ProductionRecipeLine::class, 'recipe_id');
    }

    /** @return HasMany<ProductionOrder, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class, 'recipe_id');
    }
}
