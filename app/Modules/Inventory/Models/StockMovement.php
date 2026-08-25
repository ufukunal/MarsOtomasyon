<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Core\Models\Company;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StockMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'operation_key',
        'request_fingerprint',
        'source_type',
        'source_id',
        'effect_type',
        'product_id',
        'warehouse_id',
        'location_id',
        'movement_type',
        'quantity_delta',
        'unit_cost',
        'value_delta',
        'balance_quantity_after',
        'average_unit_cost_after',
        'inventory_value_after',
        'note',
        'occurred_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'movement_type' => StockMovementType::class,
            'quantity_delta' => 'decimal:6',
            'unit_cost' => 'decimal:6',
            'value_delta' => 'decimal:6',
            'balance_quantity_after' => 'decimal:6',
            'average_unit_cost_after' => 'decimal:6',
            'inventory_value_after' => 'decimal:6',
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
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
}
