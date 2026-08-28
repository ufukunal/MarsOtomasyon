<?php

namespace App\Modules\Operations;

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
            $database = config('database.connections.pgsql');
            if (! is_array($database)) {
                throw new RuntimeException('PostgreSQL connection configuration is missing.');
            }
            $command = [
                (string) config('m11.backup.pg_dump', 'pg_dump'),
                '--format=plain', '--no-owner', '--no-privileges',
                '--host='.(string) ($database['host'] ?? '127.0.0.1'),
                '--port='.(string) ($database['port'] ?? 5432),
                '--username='.(string) ($database['username'] ?? ''),
                '--dbname='.(string) ($database['database'] ?? ''),
            ];
            $result = Process::env(['PGPASSWORD' => (string) ($database['password'] ?? '')])->timeout(300)->run($command);
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
        if ($createSafetyBackup) {
            $this->create($userId);
        }
        $contents = Storage::disk((string) $artifact->disk)->get((string) $artifact->path);
        $wrapper = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($wrapper) || ($wrapper['format'] ?? null) !== 'marsbak-v1' || ! is_string($wrapper['ciphertext'] ?? null)) {
            throw new RuntimeException('Backup artifact format is invalid.');
        }
        $sql = Crypt::decryptString($wrapper['ciphertext']);
        $database = config('database.connections.pgsql');
        if (! is_array($database)) {
            throw new RuntimeException('PostgreSQL connection configuration is missing.');
        }
        DB::table('backup_artifacts')->where('id', $id)->update(['status' => 'restoring', 'restore_started_at' => now(), 'updated_at' => now()]);
        Artisan::call('down', ['--retry' => 60]);
        try {
            $command = [
                (string) config('m11.backup.psql', 'psql'),
                '--set=ON_ERROR_STOP=1', '--single-transaction',
                '--host='.(string) ($database['host'] ?? '127.0.0.1'),
                '--port='.(string) ($database['port'] ?? 5432),
                '--username='.(string) ($database['username'] ?? ''),
                '--dbname='.(string) ($database['database'] ?? ''),
            ];
            $result = Process::env(['PGPASSWORD' => (string) ($database['password'] ?? '')])->input($sql)->timeout(600)->run($command);
            if (! $result->successful()) {
                throw new RuntimeException('psql restore failed: '.mb_substr($result->errorOutput(), 0, 2000));
            }
            DB::table('backup_artifacts')->where('id', $id)->update(['status' => 'restored', 'restore_finished_at' => now(), 'last_error' => null, 'updated_at' => now()]);
        } catch (\Throwable $exception) {
            DB::table('backup_artifacts')->where('id', $id)->update(['status' => 'ready', 'last_error' => mb_substr($exception->getMessage(), 0, 4000), 'updated_at' => now()]);
            throw $exception;
        } finally {
            Artisan::call('up');
        }
    }
}
