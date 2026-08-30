<?php

namespace App\Modules\Subcontract\Models;

use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SubcontractReceipt extends Model
{
    protected $fillable = ['company_id', 'subcontract_order_id', 'operation_key', 'output_quantity', 'carrying_value', 'consumption_payload', 'stock_movement_id', 'occurred_at'];

    protected function casts(): array
    {
        return ['output_quantity' => 'decimal:6', 'carrying_value' => 'decimal:6', 'consumption_payload' => 'array', 'occurred_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<SubcontractOrder, $this> */
    public function order(): BelongsTo { return $this->belongsTo(SubcontractOrder::class, 'subcontract_order_id'); }

    /** @return BelongsTo<StockMovement, $this> */
    public function stockMovement(): BelongsTo { return $this->belongsTo(StockMovement::class); }
}
