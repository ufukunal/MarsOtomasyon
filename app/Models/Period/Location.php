<?php

namespace App\Models\Period;

use App\Enums\LocationKind;
use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use App\Support\Search\HasSearchIndex;

class Location extends PeriodModel
{
    use HasOptimisticLock;
    use HasSearchIndex;

    protected $fillable = [
        'code',
        'name',
        'kind',
        'plate',
        'address',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'kind' => LocationKind::class,
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function searchableFields(): array
    {
        return ['code', 'name', 'plate'];
    }
}
