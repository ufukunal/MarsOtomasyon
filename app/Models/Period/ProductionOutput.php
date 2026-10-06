<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ProductionOutput extends PeriodModel
{
    protected $fillable = [
        'production_completion_id', 'location_id', 'quantity', 'stock_movement_id',
    ];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Production geçmiş kaydı yerinde değiştirilemez.'));
        static::deleting(fn (): never => throw new LogicException('Production geçmiş kaydı fiziksel olarak silinemez.'));
    }

    protected function casts(): array
    {
        return [
            'production_completion_id' => 'integer',
            'location_id' => 'integer',
            'quantity' => 'decimal:3',
            'stock_movement_id' => 'integer',
        ];
    }

    public function completion(): BelongsTo
    {
        return $this->belongsTo(ProductionCompletion::class, 'production_completion_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }
}
