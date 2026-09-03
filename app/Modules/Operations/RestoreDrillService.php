<?php

namespace App\Modules\Operations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final readonly class RestoreDrillService
{
    public function __construct(private BackupManager $backups) {}

    public function run(string $backupId, ?int $userId = null, bool $createSafetyBackup = true): string
    {
        $runId = (string) Str::uuid();
        $this->persist($runId, $backupId, $userId, $createSafetyBackup, 'running', null, null);

        try {
            if (! $this->backups->verify($backupId)) {
                throw new RuntimeException('Backup verification failed before restore drill.');
            }

            $this->backups->restore($backupId, $userId, $createSafetyBackup);
            $this->persist($runId, $backupId, $userId, $createSafetyBackup, 'succeeded', [
                'artifact_verified' => true,
                'database_restore' => true,
                'file_restore' => true,
                'checksum_verification' => true,
            ], null);

            return $runId;
        } catch (Throwable $exception) {
            $this->persist(
                $runId,
                $backupId,
                $userId,
                $createSafetyBackup,
                'failed',
                null,
                mb_substr($exception->getMessage(), 0, 4000),
            );

            throw $exception;
        }
    }

    /** @param array<string, bool>|null $checks */
    private function persist(
        string $runId,
        string $backupId,
        ?int $userId,
        bool $createSafetyBackup,
        string $status,
        ?array $checks,
        ?string $lastError,
    ): void {
        if (! Schema::hasTable('restore_runs')) {
            return;
        }
        if (! DB::table('backup_artifacts')->where('id', $backupId)->exists()) {
            return;
        }
        if ($userId !== null && ! DB::table('users')->where('id', $userId)->exists()) {
            $userId = null;
        }

        $existing = DB::table('restore_runs')->where('id', $runId)->first();
        $payload = [
            'backup_artifact_id' => $backupId,
            'started_by_user_id' => $userId,
            'status' => $status,
            'safety_backup_requested' => $createSafetyBackup,
            'checks' => $checks === null ? null : json_encode($checks, JSON_THROW_ON_ERROR),
            'finished_at' => $status === 'running' ? null : now(),
            'last_error' => $lastError,
            'updated_at' => now(),
        ];

        if ($existing === null) {
            DB::table('restore_runs')->insert($payload + [
                'id' => $runId,
                'started_at' => now(),
                'created_at' => now(),
            ]);

            return;
        }

        DB::table('restore_runs')->where('id', $runId)->update($payload);
    }
}
