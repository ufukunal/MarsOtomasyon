<?php

namespace App\Models;

use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string,mixed>|null $filters
 * @property list<string>|null $columns
 * @property list<array{key:string,direction:string}>|null $sort
 */
class ReportFilterPreset extends MasterModel
{
    use HasOptimisticLock;

    protected $fillable = [
        'company_id',
        'user_id',
        'report_key',
        'name',
        'filters',
        'columns',
        'sort',
        'is_shared',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'user_id' => 'integer',
            'filters' => 'array',
            'columns' => 'array',
            'sort' => 'array',
            'is_shared' => 'boolean',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
