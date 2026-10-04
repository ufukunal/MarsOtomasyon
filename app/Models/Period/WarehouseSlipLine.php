<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseSlipLine extends PeriodModel
{
    protected $fillable = [
        'warehouse_slip_id',
        'product_id',
        'quantity',
        'unit_cost',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<WarehouseSlip, $this> */
    public function slip(): BelongsTo
    {
        return $this->belongsTo(WarehouseSlip::class, 'warehouse_slip_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
