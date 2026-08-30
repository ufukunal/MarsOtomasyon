<?php

namespace App\Modules\Subcontract\Models;

use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SubcontractOrderMaterial extends Model
{
    protected $fillable = [
        'company_id', 'subcontract_order_id', 'product_id', 'planned_quantity', 'sent_quantity', 'sent_value',
        'consumed_quantity', 'consumed_value', 'loss_quantity', 'loss_value', 'send_stock_movement_id',
    ];

    protected function casts(): array
    {
        return [
            'planned_quantity' => 'decimal:6', 'sent_quantity' => 'decimal:6', 'sent_value' => 'decimal:6',
            'consumed_quantity' => 'decimal:6', 'consumed_value' => 'decimal:6', 'loss_quantity' => 'decimal:6', 'loss_value' => 'decimal:6',
            'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<SubcontractOrder, $this> */
    public function order(): BelongsTo { return $this->belongsTo(SubcontractOrder::class, 'subcontract_order_id'); }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }

    /** @return BelongsTo<StockMovement, $this> */
    public function sendStockMovement(): BelongsTo { return $this->belongsTo(StockMovement::class, 'send_stock_movement_id'); }
}
