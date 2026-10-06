<?php

namespace App\Models;

class RestoreRun extends MasterModel
{
    protected $fillable = [
        'recovery_set_id', 'source_backup_run_id', 'target_type', 'status', 'started_at',
        'finished_at', 'verification_summary', 'error_summary',
    ];

    protected function casts(): array
    {
        return [
            'source_backup_run_id' => 'integer',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'verification_summary' => 'array',
        ];
    }
}
