<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, string|null> $mapping
 * @property \Illuminate\Support\Carbon|null $opening_date
 */
class CardImportBatch extends PeriodModel
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'type',
        'source_disk',
        'source_path',
        'original_name',
        'file_hash',
        'mapping',
        'error_mode',
        'opening_date',
        'status',
        'total_rows',
        'success_rows',
        'error_rows',
        'created_by',
        'created_by_name',
        'started_at',
        'finished_at',
        'failure_message',
    ];

    protected function casts(): array
    {
        return [
            'mapping' => 'array',
            'opening_date' => 'date',
            'total_rows' => 'integer',
            'success_rows' => 'integer',
            'error_rows' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return HasMany<CardImportError, $this> */
    public function errors(): HasMany
    {
        return $this->hasMany(CardImportError::class, 'batch_id');
    }
}
