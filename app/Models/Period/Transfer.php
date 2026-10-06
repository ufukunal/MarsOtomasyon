<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $production_order_id
 * @property \Illuminate\Support\Carbon $transfer_date
 * @property string $status
 */
class Transfer extends PeriodModel
{
    protected $fillable = [
        'number',
        'from_location_id',
        'to_location_id',
        'production_order_id',
        'transfer_date',
        'status',
        'note',
        'created_by',
        'posted_by',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'production_order_id' => 'integer',
            'transfer_date' => 'date',
            'created_by' => 'integer',
            'posted_by' => 'integer',
            'posted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Location, $this> */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    /** @return BelongsTo<ProductionOrder, $this> */
    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    /** @return HasMany<TransferLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(TransferLine::class);
    }
}
