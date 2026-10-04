<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportCostAllocation extends PeriodModel
{
    protected $fillable = [
        'import_cost_item_id', 'package_id', 'product_id', 'container_id',
        'basis_value', 'allocation_ratio', 'allocated_amount_try',
    ];

    protected function casts(): array
    {
        return [
            'import_cost_item_id' => 'integer',
            'package_id' => 'integer',
            'product_id' => 'integer',
            'container_id' => 'integer',
            'basis_value' => 'decimal:8',
            'allocation_ratio' => 'decimal:10',
            'allocated_amount_try' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<ImportCostItem, $this> */
    public function costItem(): BelongsTo
    {
        return $this->belongsTo(ImportCostItem::class, 'import_cost_item_id');
    }

    /** @return BelongsTo<ImportPackage, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(ImportPackage::class, 'package_id');
    }
}
