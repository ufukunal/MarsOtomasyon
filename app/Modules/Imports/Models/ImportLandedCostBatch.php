<?php

namespace App\Modules\Imports\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ImportLandedCostBatch extends Model
{
    protected $fillable = ['company_id', 'import_file_id', 'operation_key', 'allocation_basis', 'expense_total', 'currency_code', 'posted_at'];

    protected function casts(): array
    {
        return ['expense_total' => 'decimal:6', 'posted_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return HasMany<ImportLandedCostAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(ImportLandedCostAllocation::class, 'landed_cost_batch_id');
    }
}
