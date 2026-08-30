<?php

namespace App\Modules\Production\Models;

use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProductionOrderMaterial extends Model
{
    protected $fillable = [
        'company_id',
        'production_order_id',
        'product_id',
        'warehouse_id',
        'location_id',
        'required_quantity',
        'issued_quantity',
        'issued_value',
        'stock_movement_id',
    ];

    protected function casts(): array
    {
        return [
            'required_quantity' => 'decimal:6',
            'issued_quantity' => 'decimal:6',
            'issued_value' => 'decimal:6',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<ProductionOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<StockMovement, $this> */
    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }
}
