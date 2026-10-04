<?php

namespace App\Models;

class PostingPeriod extends PeriodModel
{
    protected $fillable = [
        'year',
        'month',
        'status',
        'closed_by',
        'closed_by_name',
        'closed_at',
        'reopened_by',
        'reopened_by_name',
        'reopened_at',
        'reopen_reason',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }
}
