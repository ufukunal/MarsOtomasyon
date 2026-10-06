<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string,mixed> $filters
 * @property list<int> $periods
 * @property array{cost_view_required?:bool} $permission_scope
 */
class ReportExportJob extends MasterModel
{
    protected $fillable = [
        'company_id',
        'user_id',
        'report_key',
        'format',
        'filters',
        'periods',
        'permission_scope',
        'parameters_hash',
        'status',
        'progress',
        'storage_disk',
        'storage_path',
        'error_summary',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'user_id' => 'integer',
            'filters' => 'array',
            'periods' => 'array',
            'permission_scope' => 'array',
            'progress' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
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
