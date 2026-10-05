<?php

namespace App\Models\Period;

use App\Contracts\SearchIndexed;
use App\Enums\LocationKind;
use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use App\Support\Search\HasSearchIndex;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property LocationKind $kind
 */
class Location extends PeriodModel implements SearchIndexed
{
    use HasOptimisticLock;
    use HasSearchIndex;

    protected $fillable = [
        'code',
        'name',
        'kind',
        'plate',
        'subcontractor_contact_id',
        'address',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'kind' => LocationKind::class,
            'subcontractor_contact_id' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function subcontractorContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'subcontractor_contact_id');
    }

    public function searchableFields(): array
    {
        return ['code', 'name', 'plate'];
    }
}
