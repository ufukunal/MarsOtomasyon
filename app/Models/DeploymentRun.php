<?php

namespace App\Models;

class DeploymentRun extends MasterModel
{
    protected $fillable = [
        'release_id','commit_sha','initiated_by','initiated_by_name','status',
        'started_at','finished_at','previous_release_id','metadata','error_summary',
    ];

    protected function casts(): array
    {
        return [
            'initiated_by' => 'integer',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }
}
