<?php

namespace App\Models;

class IntegrityReport extends PeriodModel
{
    protected $fillable = [
        'check_name',
        'run_at',
        'checked_count',
        'mismatch_count',
        'details',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'run_at' => 'datetime',
            'checked_count' => 'integer',
            'mismatch_count' => 'integer',
            'details' => 'array',
            'duration_ms' => 'integer',
        ];
    }
}
