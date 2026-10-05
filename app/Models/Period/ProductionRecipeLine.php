<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ProductionRecipeLine extends PeriodModel
{
    protected $fillable = [
        'production_recipe_id', 'component_product_id', 'unit_id',
        'quantity', 'base_quantity', 'conversion_factor', 'version',
    ];

    protected function casts(): array
    {
        return [
            'production_recipe_id' => 'integer',
            'component_product_id' => 'integer',
            'unit_id' => 'integer',
            'quantity' => 'decimal:3',
            'base_quantity' => 'decimal:3',
            'conversion_factor' => 'decimal:6',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Reçete satırı immutable.'));
        static::deleting(fn (): never => throw new LogicException('Reçete satırı fiziksel olarak silinemez.'));
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(ProductionRecipe::class, 'production_recipe_id');
    }

    public function componentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
