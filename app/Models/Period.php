<?php

namespace App\Models;

use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Period extends MasterModel
{
    use HasOptimisticLock;

    protected $fillable = [
        'company_id',
        'year',
        'database_name',
        'starts_on',
        'ends_on',
        'status',
        'carried_from_period_id',
        'carried_at',
        'closed_at',
        'schema_version',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'carried_at' => 'datetime',
            'closed_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function carriedFromPeriod(): BelongsTo
    {
        return $this->belongsTo(self::class, 'carried_from_period_id');
    }

    public function databaseName(): string
    {
        return sprintf('%s_%d', $this->company->db_prefix, $this->year);
    }
}
