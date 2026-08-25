<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Core\Models\Company;
use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StockBalance extends Model
{
    protected $fillable = [
        'company_id',
        'product_id',
        'warehouse_id',
        'location_id',
        'quantity',
        'average_unit_cost',
        'inventory_value',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'average_unit_cost' => 'decimal:6',
            'inventory_value' => 'decimal:6',
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

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<WarehouseLocation, $this, 'location_id'> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }
}
