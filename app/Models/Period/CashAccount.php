<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashAccount extends PeriodModel
{
    use HasOptimisticLock;

    protected $fillable = ['code', 'name', 'currency', 'is_active', 'version'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'version' => 'integer'];
    }

    /** @return HasMany<CashMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }
}
