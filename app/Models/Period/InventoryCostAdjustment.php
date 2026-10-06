<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InventoryCostAdjustment extends PeriodModel
{
    protected $fillable = [
        'product_id', 'import_file_id', 'import_file_line_id',
        'production_completion_id', 'production_service_allocation_id',
        'adjustment_of_id', 'adjustment_date', 'quantity_basis',
        'amount_base', 'unit_adjustment_base', 'moving_average_before',
        'moving_average_after', 'reason', 'created_by', 'created_by_name',
    ];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Production geçmiş kaydı yerinde değiştirilemez.'));
        static::deleting(fn (): never => throw new LogicException('Production geçmiş kaydı fiziksel olarak silinemez.'));
    }

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'import_file_id' => 'integer',
            'import_file_line_id' => 'integer',
            'production_completion_id' => 'integer',
            'production_service_allocation_id' => 'integer',
            'adjustment_of_id' => 'integer',
            'adjustment_date' => 'date',
            'quantity_basis' => 'decimal:3',
            'amount_base' => 'decimal:4',
            'unit_adjustment_base' => 'decimal:4',
            'moving_average_before' => 'decimal:4',
            'moving_average_after' => 'decimal:4',
            'created_by' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function completion(): BelongsTo
    {
        return $this->belongsTo(ProductionCompletion::class, 'production_completion_id');
    }

    public function serviceAllocation(): BelongsTo
    {
        return $this->belongsTo(ProductionServiceAllocation::class, 'production_service_allocation_id');
    }

    public function adjustmentOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'adjustment_of_id');
    }
}
