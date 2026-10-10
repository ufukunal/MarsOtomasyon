<?php

use App\Models\BackupRun;
use App\Support\Operations\BackupHealthService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\IsolatedPostgres;

it('distinguishes verified, corrupted and stale local backup archives without restoring anything', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $disk = 'mars_v4_backup_health';
        $oldDisks = config('operations.backup.disks');
        $oldAge = config('operations.backup.max_age_hours');
        Storage::fake($disk);

        try {
            config(['operations.backup.disks' => [$disk], 'operations.backup.max_age_hours' => 36]);
            Storage::disk($disk)->put('v4-backup.zip', 'disposable recovery bytes');

            $backup = BackupRun::query()->create([
                'recovery_set_id' => (string) Str::uuid(),
                'trigger_type' => 'manual',
                'status' => 'done',
                'storage_disk' => $disk,
                'master_backup_path' => 'v4-backup.zip',
                'started_at' => now()->subMinute(),
                'finished_at' => now(),
                'checksum_manifest' => [
                    $disk => [
                        'path' => 'v4-backup.zip',
                        'checksum' => Storage::disk($disk)->checksum('v4-backup.zip'),
                    ],
                ],
            ]);

            $health = app(BackupHealthService::class);
            $healthy = $health->check();
            expect($healthy['ok'])->toBeTrue()
                ->and($healthy['status'])->toBe('healthy')
                ->and($healthy['backup_run_id'])->toBe($backup->id);

            Storage::disk($disk)->put('v4-backup.zip', 'tampered bytes');
            $tampered = $health->check();
            expect($tampered['ok'])->toBeFalse()
                ->and($tampered['status'])->toBe('checksum_or_target_failed');

            $backup->finished_at = now()->subHours(48);
            $backup->save();
            $stale = $health->check();
            expect($stale['ok'])->toBeFalse()
                ->and($stale['status'])->toBe('stale');
        } finally {
            config(['operations.backup.disks' => $oldDisks, 'operations.backup.max_age_hours' => $oldAge]);
        }
    });
});
