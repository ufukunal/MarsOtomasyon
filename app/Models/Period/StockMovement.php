<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class StockMovement extends PeriodModel
{
    protected $fillable = [
        'product_id',
        'location_id',
        'product_code',
        'movement_date',
        'direction',
        'reason',
        'quantity',
        'unit_cost',
        'total_cost',
        'balance_after',
        'avg_cost_after',
        'document_type',
        'document_id',
        'document_no',
        'note',
        'created_by',
        'created_by_name',
    ];

    protected function casts(): array
    {
        return [
            'movement_date' => 'date',
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
            'balance_after' => 'decimal:3',
            'avg_cost_after' => 'decimal:4',
            'document_id' => 'integer',
            'created_by' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Stok hareketi değiştirilemez.'));
        static::deleting(fn (): never => throw new LogicException('Stok hareketi silinemez.'));
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
