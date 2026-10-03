<?php

namespace App\Models;

class NumberSeries extends PeriodModel
{
    protected $table = 'number_series';

    protected $fillable = [
        'document_type',
        'prefix',
        'year',
        'last_number',
        'padding',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_number' => 'integer',
            'padding' => 'integer',
        ];
    }
}
