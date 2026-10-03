<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitConversion extends PeriodModel
{
    use HasOptimisticLock;

    protected $fillable = ['from_unit_id', 'to_unit_id', 'factor'];

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }

    /** @return BelongsTo<Unit, $this> */
    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'from_unit_id');
    }

    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'to_unit_id');
    }
}
