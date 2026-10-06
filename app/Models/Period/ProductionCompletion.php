<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property int $id
 * @property int $production_order_id
 * @property \Illuminate\Support\Carbon $completion_date
 * @property string $completed_quantity
 * @property string $material_cost_total
 * @property string $subcontract_service_cost_total
 * @property string $production_cost_total
 * @property string $production_unit_cost
 * @property string $moving_average_before
 * @property string $moving_average_after
 * @property string $previous_production_cost
 */
class ProductionCompletion extends PeriodModel
{
    protected $fillable = [
        'production_order_id', 'completion_date', 'completed_quantity',
        'material_cost_total', 'subcontract_service_cost_total',
        'production_cost_total', 'production_unit_cost',
        'moving_average_before', 'moving_average_after', 'previous_production_cost',
        'reversal_of_id', 'notes', 'created_by', 'created_by_name',
    ];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Production geçmiş kaydı yerinde değiştirilemez.'));
        static::deleting(fn (): never => throw new LogicException('Production geçmiş kaydı fiziksel olarak silinemez.'));
    }

    protected function casts(): array
    {
        return [
            'production_order_id' => 'integer',
            'completion_date' => 'date',
            'completed_quantity' => 'decimal:3',
            'material_cost_total' => 'decimal:4',
            'subcontract_service_cost_total' => 'decimal:4',
            'production_cost_total' => 'decimal:4',
            'production_unit_cost' => 'decimal:4',
            'moving_average_before' => 'decimal:4',
            'moving_average_after' => 'decimal:4',
            'previous_production_cost' => 'decimal:4',
            'reversal_of_id' => 'integer',
            'created_by' => 'integer',
        ];
    }

    /** @return BelongsTo<ProductionOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    /** @return BelongsTo<self, $this> */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /** @return HasMany<self, $this> */
    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_id');
    }

    /** @return HasMany<ProductionConsumption, $this> */
    public function consumptions(): HasMany
    {
        return $this->hasMany(ProductionConsumption::class);
    }

    /** @return HasMany<ProductionOutput, $this> */
    public function outputs(): HasMany
    {
        return $this->hasMany(ProductionOutput::class);
    }

    /** @return HasMany<ProductionServiceAllocation, $this> */
    public function serviceAllocations(): HasMany
    {
        return $this->hasMany(ProductionServiceAllocation::class);
    }

    /** @return HasMany<InventoryCostAdjustment, $this> */
    public function costAdjustments(): HasMany
    {
        return $this->hasMany(InventoryCostAdjustment::class);
    }
}
