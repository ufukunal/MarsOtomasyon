<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends PeriodModel
{
    use HasOptimisticLock;

    protected $fillable = ['code', 'name', 'is_base', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_base' => 'boolean',
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    /** @return HasMany<UnitConversion, $this> */
    public function conversionsFrom(): HasMany
    {
        return $this->hasMany(UnitConversion::class, 'from_unit_id');
    }

    /** @return HasMany<UnitConversion, $this> */
    public function conversionsTo(): HasMany
    {
        return $this->hasMany(UnitConversion::class, 'to_unit_id');
    }
}
