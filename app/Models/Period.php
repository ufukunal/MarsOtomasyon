<?php

namespace App\Models;

use Database\Factories\PeriodFactory;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 */
class Period extends MasterModel
{
    /** @use HasFactory<PeriodFactory> */
    use HasFactory;
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

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Period, $this> */
    public function carriedFromPeriod(): BelongsTo
    {
        return $this->belongsTo(self::class, 'carried_from_period_id');
    }

    public function databaseName(): string
    {
        return sprintf('%s_%d', $this->company->db_prefix, $this->year);
    }
}
