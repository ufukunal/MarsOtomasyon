<?php

namespace App\Modules\Imports\Models;

use Illuminate\Database\Eloquent\Model;

final class ImportLandedCostAllocation extends Model
{
    protected $fillable = ['company_id', 'landed_cost_batch_id', 'import_receipt_link_id', 'goods_receipt_cost_adjustment_id', 'allocation_weight', 'allocated_amount'];

    protected function casts(): array
    {
        return ['allocation_weight' => 'decimal:6', 'allocated_amount' => 'decimal:6', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }
}
