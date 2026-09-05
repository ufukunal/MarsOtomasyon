<?php

namespace App\Modules\Operations;

use App\Foundation\Operations\BackupRecoveryCipher;
use App\Foundation\Operations\ProductionCandidateGate;
use App\Foundation\Operations\ProductionSafetyState;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class BackupManager
{
    public function __construct(
        private readonly BackupRecoveryCipher $recoveryCipher,
        private readonly ProductionSafetyState $safety,
    ) {}

    public function create(?int $userId = null): string
    {
        $this->assertProductionBackupConfiguration();

        $id = (string) Str::uuid();
        $disk = (string) config('m11.backup.disk', 'local');
        $directory = trim((string) config('m11.backup.directory', 'backups'), '/');
        $path = $directory.'/mars-'.$id.'.marsbak';
        DB::table('backup_artifacts')->insert([
            'id' => $id,
            'status' => 'creating',
            'disk' => $disk,
            'path' => $path,
            'encrypted' => true,
            'created_by_user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
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

            $payload = json_encode([
                'version' => 3,
                'sql' => $result->output(),
                'file_assets' => $this->captureFileAssets(),
            ], JSON_THROW_ON_ERROR);
            $wrapper = json_encode([
                'format' => 'marsbak-v3',
                'created_at' => now()->toIso8601String(),
                'key_reference' => (string) config('production.backup.recovery_key_reference', ''),
                'ciphertext' => $this->recoveryCipher->encryptString($payload),
            ], JSON_THROW_ON_ERROR);

            if (! Storage::disk($disk)->put($path, $wrapper)) {
                throw new RuntimeException('Backup artifact could not be written.');
            }
            $sha = hash('sha256', $wrapper);
            DB::table('backup_artifacts')->where('id', $id)->update([
                'status' => 'ready',
                'sha256' => $sha,
                'size_bytes' => strlen($wrapper),
                'verified_at' => now(),
                'last_error' => null,
                'updated_at' => now(),
            ]);

            return $id;
        } catch (\Throwable $exception) {
            DB::table('backup_artifacts')->where('id', $id)->update([
                'status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 4000),
                'updated_at' => now(),
            ]);
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
        if (! is_string($contents) || ! hash_equals((string) $artifact->sha256, hash('sha256', $contents))) {
            return false;
        }

        try {
            $this->decodeBackup($contents);
        } catch (\Throwable) {
            return false;
        }

        DB::table('backup_artifacts')->where('id', $id)->update([
            'verified_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    public function restore(string $id, ?int $userId = null, bool $createSafetyBackup = true): void
    {
        $this->assertProductionBackupConfiguration();

        $artifact = DB::table('backup_artifacts')->where('id', $id)->first();
        if ($artifact === null || (string) $artifact->status !== 'ready' || ! $this->verify($id)) {
            throw new RuntimeException('Backup artifact is not ready or checksum verification failed.');
        }

        $this->safety->enterRecoveryMode();
        $maintenanceEnabled = false;
        $restoreSucceeded = false;

        try {
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
            $decoded = $this->decodeBackup($contents);
            $database = $this->postgresConfiguration();
            $restoreStartedAt = now();
            DB::table('backup_artifacts')->where('id', $id)->update([
                'status' => 'restoring',
                'restore_started_at' => $restoreStartedAt,
                'updated_at' => now(),
            ]);
            Artisan::call('down', ['--retry' => $this->safety->retryAfterSeconds()]);
            $maintenanceEnabled = true;

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
                $result = $this->postgresProcess($database)->input($decoded['sql'])->timeout(600)->run($command);
                if (! $result->successful()) {
                    throw new RuntimeException('psql restore failed: '.mb_substr($result->errorOutput(), 0, 2000));
                }

                $this->restoreFileAssets($decoded['file_assets']);

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

                $restoreSucceeded = true;
            } catch (\Throwable $exception) {
                if (Schema::hasTable('backup_artifacts')) {
                    DB::table('backup_artifacts')->where('id', $id)->update([
                        'status' => 'ready',
                        'last_error' => mb_substr($exception->getMessage(), 0, 4000),
                        'updated_at' => now(),
                    ]);
                }
                throw $exception;
            }
        } finally {
            if ($maintenanceEnabled) {
                Artisan::call('up');
            }

            // A failed or ambiguous restore intentionally leaves Recovery Mode active.
            if ($restoreSucceeded) {
                $this->safety->leaveRecoveryMode();
            }
        }
    }

    /** @return list<array{disk:string,key:string,sha256:string,size_bytes:int,contents:string}> */
    private function captureFileAssets(): array
    {
        if (! (bool) config('m11.backup.include_file_assets', true) || ! Schema::hasTable('file_assets')) {
            return [];
        }

        $limit = max(0, (int) config('m11.backup.max_file_assets_bytes', 1073741824));
        $total = 0;
        $files = [];

        foreach (DB::table('file_assets')->orderBy('id')->get(['storage_disk', 'storage_key', 'sha256', 'size_bytes']) as $asset) {
            $disk = (string) $asset->storage_disk;
            $key = (string) $asset->storage_key;
            if (! Storage::disk($disk)->exists($key)) {
                throw new RuntimeException('Referenced file asset is missing: '.$disk.':'.$key);
            }
            $contents = Storage::disk($disk)->get($key);
            if (! is_string($contents)) {
                throw new RuntimeException('Referenced file asset could not be read: '.$disk.':'.$key);
            }
            $sha = hash('sha256', $contents);
            if (! hash_equals((string) $asset->sha256, $sha)) {
                throw new RuntimeException('Referenced file asset checksum mismatch: '.$disk.':'.$key);
            }
            $total += strlen($contents);
            if ($limit > 0 && $total > $limit) {
                throw new RuntimeException('Backup file assets exceed configured maximum size.');
            }
            $files[] = [
                'disk' => $disk,
                'key' => $key,
                'sha256' => $sha,
                'size_bytes' => strlen($contents),
                'contents' => base64_encode($contents),
            ];
        }

        return $files;
    }

    /** @return array{sql:string,file_assets:list<array{disk:string,key:string,sha256:string,size_bytes:int,contents:string}>} */
    private function decodeBackup(string $contents): array
    {
        $wrapper = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($wrapper) || ! is_string($wrapper['format'] ?? null) || ! is_string($wrapper['ciphertext'] ?? null)) {
            throw new RuntimeException('Backup artifact format is invalid.');
        }

        if (in_array($wrapper['format'], ['marsbak-v1', 'marsbak-v2'], true)) {
            if (! (bool) config('production.backup.allow_legacy_app_key_decryption', true)) {
                throw new RuntimeException('Legacy APP_KEY-encrypted backup artifacts are disabled.');
            }

            if ($wrapper['format'] === 'marsbak-v1') {
                return ['sql' => Crypt::decryptString($wrapper['ciphertext']), 'file_assets' => []];
            }

            $payload = json_decode(Crypt::decryptString($wrapper['ciphertext']), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($payload) || ($payload['version'] ?? null) !== 2 || ! is_string($payload['sql'] ?? null) || ! is_array($payload['file_assets'] ?? null)) {
                throw new RuntimeException('Backup payload is invalid.');
            }

            return ['sql' => $payload['sql'], 'file_assets' => $this->verifiedFiles($payload['file_assets'])];
        }

        if ($wrapper['format'] !== 'marsbak-v3') {
            throw new RuntimeException('Unsupported backup artifact format.');
        }

        $payload = json_decode($this->recoveryCipher->decryptString($wrapper['ciphertext']), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($payload) || ($payload['version'] ?? null) !== 3 || ! is_string($payload['sql'] ?? null) || ! is_array($payload['file_assets'] ?? null)) {
            throw new RuntimeException('Backup payload is invalid.');
        }

        return ['sql' => $payload['sql'], 'file_assets' => $this->verifiedFiles($payload['file_assets'])];
    }

    /**
     * @param  array<mixed>  $manifest
     * @return list<array{disk:string,key:string,sha256:string,size_bytes:int,contents:string}>
     */
    private function verifiedFiles(array $manifest): array
    {
        $files = [];
        foreach ($manifest as $file) {
            if (! is_array($file)
                || ! is_string($file['disk'] ?? null)
                || ! is_string($file['key'] ?? null)
                || ! is_string($file['sha256'] ?? null)
                || ! is_int($file['size_bytes'] ?? null)
                || ! is_string($file['contents'] ?? null)) {
                throw new RuntimeException('Backup file manifest is invalid.');
            }
            $decoded = base64_decode($file['contents'], true);
            if (! is_string($decoded)
                || strlen($decoded) !== $file['size_bytes']
                || ! hash_equals($file['sha256'], hash('sha256', $decoded))) {
                throw new RuntimeException('Backup file payload checksum verification failed.');
            }
            $files[] = [
                'disk' => $file['disk'],
                'key' => $file['key'],
                'sha256' => $file['sha256'],
                'size_bytes' => $file['size_bytes'],
                'contents' => $file['contents'],
            ];
        }

        return $files;
    }

    /** @param list<array{disk:string,key:string,sha256:string,size_bytes:int,contents:string}> $files */
    private function restoreFileAssets(array $files): void
    {
        foreach ($files as $file) {
            $contents = base64_decode($file['contents'], true);
            if (! is_string($contents)) {
                throw new RuntimeException('Backup file payload could not be decoded.');
            }
            if (! Storage::disk($file['disk'])->put($file['key'], $contents)) {
                throw new RuntimeException('Backup file could not be restored: '.$file['disk'].':'.$file['key']);
            }
            $restored = Storage::disk($file['disk'])->get($file['key']);
            if (! is_string($restored) || ! hash_equals($file['sha256'], hash('sha256', $restored))) {
                throw new RuntimeException('Restored file checksum verification failed: '.$file['disk'].':'.$file['key']);
            }
        }
    }

    private function assertProductionBackupConfiguration(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        $issues = array_values(array_filter(
            app(ProductionCandidateGate::class)->issues(),
            static fn (string $issue): bool => str_starts_with($issue, 'backup-'),
        ));
        if ($issues !== []) {
            throw new RuntimeException('Unsafe production backup configuration: '.implode(', ', $issues));
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
