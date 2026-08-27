<?php

namespace App\Modules\GoodsReceipts\Models;

use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Models\Product;
use App\Modules\PurchaseOrders\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GoodsReceiptLine extends Model
{
    protected $fillable = [
        'company_id', 'goods_receipt_id', 'purchase_order_id', 'purchase_order_line_id',
        'position', 'product_id', 'warehouse_id', 'location_id', 'product_code',
        'product_name', 'received_quantity', 'accepted_quantity', 'pending_quantity',
        'rejected_quantity', 'provisional_unit_cost', 'note',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'received_quantity' => 'decimal:6',
            'accepted_quantity' => 'decimal:6',
            'pending_quantity' => 'decimal:6',
            'rejected_quantity' => 'decimal:6',
            'provisional_unit_cost' => 'decimal:6',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<GoodsReceipt, $this> */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    /** @return BelongsTo<PurchaseOrderLine, $this> */
    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
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
