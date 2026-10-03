<?php

namespace App\Models;

class Attachment extends PeriodModel
{
    protected $fillable = [
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
        'collection',
        'sort_order',
        'uploaded_by',
        'uploaded_by_name',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
