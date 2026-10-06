<?php

namespace App\Models;

use Carbon\CarbonImmutable;

/**
 * @property int $id
 * @property string $recovery_set_id
 * @property string $trigger_type
 * @property string $status
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $finished_at
 * @property string $storage_disk
 * @property array<int, array<string, mixed>>|null $period_manifest
 * @property array<string, array<string, mixed>>|null $checksum_manifest
 * @property CarbonImmutable|null $verified_at
 */
class BackupRun extends MasterModel
{
    protected $fillable = [
        'recovery_set_id', 'trigger_type', 'status', 'started_at', 'finished_at', 'storage_disk',
        'manifest_path', 'master_backup_path', 'files_backup_path', 'period_manifest',
        'checksum_manifest', 'verified_at', 'error_summary',
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
