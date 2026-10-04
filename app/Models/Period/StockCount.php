<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCount extends PeriodModel
{
    protected $fillable = ['number', 'location_id', 'count_date', 'status', 'note', 'created_by', 'posted_by', 'posted_at'];

    protected function casts(): array
    {
        return ['count_date' => 'date', 'created_by' => 'integer', 'posted_by' => 'integer', 'posted_at' => 'datetime'];
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return HasMany<StockCountLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class);
    }
}
