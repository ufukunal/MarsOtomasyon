<?php

namespace App\Modules\Operations;

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class BackupManager
{
    public function create(?int $userId = null): string
    {
        $id = (string) Str::uuid();
        $disk = (string) config('m11.backup.disk', 'local');
        $directory = trim((string) config('m11.backup.directory', 'backups'), '/');
        $path = $directory.'/mars-'.$id.'.marsbak';
        DB::table('backup_artifacts')->insert([
            'id' => $id, 'status' => 'creating', 'disk' => $disk, 'path' => $path, 'encrypted' => true,
            'created_by_user_id' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            $database = $this->postgresConfiguration();
            $command = [
                (string) config('m11.backup.pg_dump', 'pg_dump'),
                '--format=plain',
                '--clean',
                '--if-exists',
                '--no-owner',
                '--no-privileges',
                '--host='.(string) ($database['host'] ?? '127.0.0.1'),
                '--port='.(string) ($database['port'] ?? 5432),
                '--username='.(string) ($database['username'] ?? ''),
                '--dbname='.(string) ($database['database'] ?? ''),
            ];
            $result = $this->postgresProcess($database)->timeout(300)->run($command);
            if (! $result->successful()) {
                throw new RuntimeException('pg_dump failed: '.mb_substr($result->errorOutput(), 0, 2000));
            }
            $wrapper = json_encode([
                'format' => 'marsbak-v1',
                'created_at' => now()->toIso8601String(),
                'ciphertext' => Crypt::encryptString($result->output()),
            ], JSON_THROW_ON_ERROR);
            if (! Storage::disk($disk)->put($path, $wrapper)) {
                throw new RuntimeException('Backup artifact could not be written.');
            }
            $sha = hash('sha256', $wrapper);
            DB::table('backup_artifacts')->where('id', $id)->update([
                'status' => 'ready', 'sha256' => $sha, 'size_bytes' => strlen($wrapper), 'verified_at' => now(), 'last_error' => null, 'updated_at' => now(),
            ]);

            return $id;
        } catch (\Throwable $exception) {
            DB::table('backup_artifacts')->where('id', $id)->update(['status' => 'failed', 'last_error' => mb_substr($exception->getMessage(), 0, 4000), 'updated_at' => now()]);
            throw $exception;
        }
    }

    public function verify(string $id): bool
    {
        $artifact = DB::table('backup_artifacts')->where('id', $id)->first();
        if ($artifact === null || $artifact->sha256 === null) {
            return false;
        }
        $contents = Storage::disk((string) $artifact->disk)->get((string) $artifact->path);
        if (! is_string($contents)) {
            return false;
        }
        $valid = hash_equals((string) $artifact->sha256, hash('sha256', $contents));
        if ($valid) {
            DB::table('backup_artifacts')->where('id', $id)->update(['verified_at' => now(), 'updated_at' => now()]);
        }

        return $valid;
    }

    public function restore(string $id, ?int $userId = null, bool $createSafetyBackup = true): void
    {
        $artifact = DB::table('backup_artifacts')->where('id', $id)->first();
        if ($artifact === null || (string) $artifact->status !== 'ready' || ! $this->verify($id)) {
            throw new RuntimeException('Backup artifact is not ready or checksum verification failed.');
        }

        $safetyArtifact = null;
        if ($createSafetyBackup) {
            $safetyId = $this->create($userId);
            $safetyArtifact = DB::table('backup_artifacts')->where('id', $safetyId)->first();
            if ($safetyArtifact === null) {
                throw new RuntimeException('Safety backup metadata could not be loaded.');
            }
        }

        $contents = Storage::disk((string) $artifact->disk)->get((string) $artifact->path);
        if (! is_string($contents)) {
            throw new RuntimeException('Backup artifact could not be read.');
        }
        $wrapper = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($wrapper) || ($wrapper['format'] ?? null) !== 'marsbak-v1' || ! is_string($wrapper['ciphertext'] ?? null)) {
            throw new RuntimeException('Backup artifact format is invalid.');
        }
        $sql = Crypt::decryptString($wrapper['ciphertext']);
        $database = $this->postgresConfiguration();
        $restoreStartedAt = now();
        DB::table('backup_artifacts')->where('id', $id)->update(['status' => 'restoring', 'restore_started_at' => $restoreStartedAt, 'updated_at' => now()]);
        Artisan::call('down', ['--retry' => 60]);
        try {
            $command = [
                (string) config('m11.backup.psql', 'psql'),
                '--set=ON_ERROR_STOP=1',
                '--single-transaction',
                '--host='.(string) ($database['host'] ?? '127.0.0.1'),
                '--port='.(string) ($database['port'] ?? 5432),
                '--username='.(string) ($database['username'] ?? ''),
                '--dbname='.(string) ($database['database'] ?? ''),
            ];
            $result = $this->postgresProcess($database)->input($sql)->timeout(600)->run($command);
            if (! $result->successful()) {
                throw new RuntimeException('psql restore failed: '.mb_substr($result->errorOutput(), 0, 2000));
            }

            $this->rehydrateArtifact(
                (string) $artifact->id,
                (string) $artifact->disk,
                (string) $artifact->path,
                $artifact->sha256 === null ? null : (string) $artifact->sha256,
                $artifact->size_bytes === null ? null : (int) $artifact->size_bytes,
                (bool) $artifact->encrypted,
                $artifact->created_by_user_id === null ? null : (int) $artifact->created_by_user_id,
                $artifact->verified_at,
                $artifact->created_at,
                'restored',
                $restoreStartedAt,
                now(),
            );
            if ($safetyArtifact !== null) {
                $this->rehydrateArtifact(
                    (string) $safetyArtifact->id,
                    (string) $safetyArtifact->disk,
                    (string) $safetyArtifact->path,
                    $safetyArtifact->sha256 === null ? null : (string) $safetyArtifact->sha256,
                    $safetyArtifact->size_bytes === null ? null : (int) $safetyArtifact->size_bytes,
                    (bool) $safetyArtifact->encrypted,
                    $safetyArtifact->created_by_user_id === null ? null : (int) $safetyArtifact->created_by_user_id,
                    $safetyArtifact->verified_at,
                    $safetyArtifact->created_at,
                    'ready',
                );
            }
        } catch (\Throwable $exception) {
            DB::table('backup_artifacts')->where('id', $id)->update([
                'status' => 'ready',
                'last_error' => mb_substr($exception->getMessage(), 0, 4000),
                'updated_at' => now(),
            ]);
            throw $exception;
        } finally {
            Artisan::call('up');
        }
    }

    /** @return array<string,mixed> */
    private function postgresConfiguration(): array
    {
        $database = config('database.connections.pgsql');
        if (! is_array($database)) {
            throw new RuntimeException('PostgreSQL connection configuration is missing.');
        }

        return $database;
    }

    /** @param array<string,mixed> $database */
    private function postgresProcess(array $database): PendingProcess
    {
        $process = Process::forever();
        $environmentMethod = 'env';

        return $process->{$environmentMethod}(['PGPASSWORD' => (string) ($database['password'] ?? '')]);
    }

    private function rehydrateArtifact(
        string $id,
        string $disk,
        string $path,
        ?string $sha256,
        ?int $sizeBytes,
        bool $encrypted,
        ?int $createdBy,
        mixed $verifiedAt,
        mixed $createdAt,
        string $status,
        mixed $restoreStartedAt = null,
        mixed $restoreFinishedAt = null,
    ): void {
        if ($createdBy !== null && ! DB::table('users')->where('id', $createdBy)->exists()) {
            $createdBy = null;
        }

        DB::table('backup_artifacts')->updateOrInsert(
            ['id' => $id],
            [
                'status' => $status,
                'disk' => $disk,
                'path' => $path,
                'sha256' => $sha256,
                'size_bytes' => $sizeBytes,
                'encrypted' => $encrypted,
                'created_by_user_id' => $createdBy,
                'verified_at' => $verifiedAt,
                'restore_started_at' => $restoreStartedAt,
                'restore_finished_at' => $restoreFinishedAt,
                'last_error' => null,
                'created_at' => $createdAt,
                'updated_at' => now(),
            ],
        );
    }
}
