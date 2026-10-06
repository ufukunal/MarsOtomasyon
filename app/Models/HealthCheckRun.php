<?php

namespace App\Models;

class HealthCheckRun extends MasterModel
{
    protected $fillable = ['checked_at', 'overall_status', 'checks', 'correlation_id'];

    protected function casts(): array
    {
        return [
            'checked_at' => 'immutable_datetime',
            'checks' => 'array',
        ];
    }
}
