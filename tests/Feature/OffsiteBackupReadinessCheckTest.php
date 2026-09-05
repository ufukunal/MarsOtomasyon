<?php

namespace Tests\Feature;

use App\Foundation\Health\OffsiteBackupReadinessCheck;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class OffsiteBackupReadinessCheckTest extends TestCase
{
    public function test_probe_writes_reads_and_cleans_up_backup_storage(): void
    {
        Storage::fake('mars_backup');
        config()->set('m11.backup.disk', 'mars_backup');

        $check = new OffsiteBackupReadinessCheck(app(FilesystemManager::class));

        self::assertTrue($check->check());
        self::assertSame([], Storage::disk('mars_backup')->allFiles());
    }

    public function test_probe_fails_closed_when_backup_disk_cannot_be_resolved(): void
    {
        config()->set('m11.backup.disk', 'missing-backup-disk');

        $check = new OffsiteBackupReadinessCheck(app(FilesystemManager::class));

        self::assertFalse($check->check());
    }
}
