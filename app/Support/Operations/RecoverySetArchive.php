<?php

namespace App\Support\Operations;

use App\Models\BackupRun;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

final class RecoverySetArchive
{
    public function verifyAndExtract(BackupRun $backup, string $directory): array
    {
        if (! in_array($backup->status, ['done', 'verified'], true)) {
            throw new RuntimeException('Restore için tamamlanmış backup_run gereklidir.');
        }

        $disk = (string) $backup->storage_disk;
        $path = (string) $backup->master_backup_path;
        if ($path === '' || ! Storage::disk($disk)->exists($path)) {
            throw new RuntimeException('Recovery set archive bulunamadı.');
        }

        $expected = $backup->checksum_manifest[$disk]['checksum'] ?? null;
        $actual = Storage::disk($disk)->checksum($path);
        if (! is_string($expected) || ! hash_equals($expected, $actual)) {
            throw new RuntimeException('Recovery set archive checksum doğrulaması başarısız.');
        }

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Restore temporary dizini oluşturulamadı.');
        }

        $localArchive = $directory.'/recovery-set.zip';
        $source = Storage::disk($disk)->readStream($path);
        $target = fopen($localArchive, 'wb');
        if (! is_resource($source) || ! is_resource($target)) {
            throw new RuntimeException('Recovery set archive local restore alanına alınamadı.');
        }
        stream_copy_to_stream($source, $target);
        fclose($source);
        fclose($target);

        $zip = new ZipArchive;
        if ($zip->open($localArchive) !== true) {
            throw new RuntimeException('Recovery set ZIP açılamadı.');
        }

        $password = (string) config('backup.backup.password', '');
        if ($password !== '') {
            $zip->setPassword($password);
        }

        if (! $zip->extractTo($directory.'/extracted')) {
            $zip->close();
            throw new RuntimeException('Recovery set ZIP extract başarısız.');
        }
        $zip->close();

        return [
            'directory' => $directory.'/extracted',
            'checksum' => $actual,
            'archive_path' => $path,
        ];
    }

    public function findSqlDump(string $directory, string $databaseName): string
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'sql') {
                continue;
            }

            if (strcasecmp($file->getBasename('.sql'), $databaseName) === 0) {
                return $file->getPathname();
            }
        }

        throw new RuntimeException("Recovery set içinde {$databaseName}.sql bulunamadı.");
    }
}
