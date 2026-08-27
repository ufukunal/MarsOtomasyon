<?php

namespace App\Modules\GoodsReceipts\Models;

use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GoodsReceiptCostAdjustment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'goods_receipt_id',
        'goods_receipt_line_id',
        'product_id',
        'warehouse_id',
        'location_id',
        'reference',
        'total_value_delta',
        'eligible_quantity',
        'on_hand_quantity_basis',
        'consumed_quantity_basis',
        'inventory_value_delta',
        'consumed_cost_delta',
        'balance_quantity_after',
        'average_unit_cost_after',
        'inventory_value_after',
        'note',
        'created_by_user_id',
        'occurred_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'total_value_delta' => 'decimal:6',
            'eligible_quantity' => 'decimal:6',
            'on_hand_quantity_basis' => 'decimal:6',
            'consumed_quantity_basis' => 'decimal:6',
            'inventory_value_delta' => 'decimal:6',
            'consumed_cost_delta' => 'decimal:6',
            'balance_quantity_after' => 'decimal:6',
            'average_unit_cost_after' => 'decimal:6',
            'inventory_value_after' => 'decimal:6',
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<GoodsReceipt, $this> */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    /** @return BelongsTo<GoodsReceiptLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptLine::class, 'goods_receipt_line_id');
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
