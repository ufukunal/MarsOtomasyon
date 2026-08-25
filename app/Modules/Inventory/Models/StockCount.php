<?php

namespace App\Modules\Inventory\Models;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property DateTimeImmutable $started_at
 * @property DateTimeImmutable|null $posted_at
 */
final class StockCount extends Model
{
    protected $fillable = [
        'company_id',
        'warehouse_id',
        'location_id',
        'operation_key',
        'status',
        'started_at',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'posted_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<WarehouseLocation, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }

    /** @return HasMany<StockCountLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class)->orderBy('id');
    }
}
