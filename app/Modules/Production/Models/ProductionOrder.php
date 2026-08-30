<?php

namespace App\Modules\Production\Models;

use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProductionOrder extends Model
{
    protected $fillable = [
        'company_id',
        'recipe_id',
        'product_id',
        'warehouse_id',
        'location_id',
        'order_no',
        'status',
        'planned_quantity',
        'material_cost',
        'loss_cost',
        'output_quantity',
        'output_unit_cost',
        'output_value',
        'output_stock_movement_id',
        'material_issued_at',
        'received_at',
        'completed_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'planned_quantity' => 'decimal:6',
            'material_cost' => 'decimal:6',
            'loss_cost' => 'decimal:6',
            'output_quantity' => 'decimal:6',
            'output_unit_cost' => 'decimal:6',
            'output_value' => 'decimal:6',
            'material_issued_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
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
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<WarehouseLocation, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }

    /** @return BelongsTo<StockMovement, $this> */
    public function outputStockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'output_stock_movement_id');
    }

    /** @return HasMany<ProductionOrderMaterial, $this> */
    public function materials(): HasMany
    {
        return $this->hasMany(ProductionOrderMaterial::class, 'production_order_id');
    }

    /** @return HasMany<ProductionLoss, $this> */
    public function losses(): HasMany
    {
        return $this->hasMany(ProductionLoss::class, 'production_order_id');
    }

    /** @return HasMany<ProductionEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ProductionEvent::class, 'production_order_id');
    }
}
