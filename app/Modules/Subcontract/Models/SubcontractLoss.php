<?php

namespace App\Modules\Subcontract\Models;

use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SubcontractLoss extends Model
{
    protected $fillable = ['company_id', 'subcontract_order_id', 'product_id', 'operation_key', 'loss_type', 'quantity', 'carrying_value', 'note', 'occurred_at'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:6', 'carrying_value' => 'decimal:6', 'occurred_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<SubcontractOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(SubcontractOrder::class, 'subcontract_order_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
