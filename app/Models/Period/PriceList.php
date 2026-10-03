<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceList extends PeriodModel
{
    use HasOptimisticLock;

    protected $fillable = ['name', 'currency', 'vat_included', 'is_default', 'is_active'];

    protected function casts(): array
    {
        return [
            'vat_included' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }
}
