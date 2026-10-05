<?php

namespace App\Models;

class BackupRun extends MasterModel
{
    protected $fillable = [
        'recovery_set_id','trigger_type','status','started_at','finished_at','storage_disk',
        'manifest_path','master_backup_path','files_backup_path','period_manifest',
        'checksum_manifest','verified_at','error_summary',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'period_manifest' => 'array',
            'checksum_manifest' => 'array',
            'verified_at' => 'immutable_datetime',
        ];
    }
}
