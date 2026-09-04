<?php

namespace Tests\Feature;

use App\Modules\Operations\BackupManager;
use App\Modules\Operations\RestoreDrillService;
use RuntimeException;
use Tests\TestCase;

final class RecoverySafetyTest extends TestCase
{
    public function test_restore_drill_rejects_an_unverified_or_missing_backup_before_restore(): void
    {
        $service = app(RestoreDrillService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Backup verification failed before restore drill.');

        $service->run('missing-backup-artifact', null, false);
    }

    public function test_production_backup_creation_rejects_unsafe_storage_boundary_before_dumping(): void
    {
        $this->app['env'] = 'production';
        config()->set('production.primary_file_disk', 'local');
        config()->set('production.backup.offsite_required', true);
        config()->set('m11.backup.disk', 'local');

        $manager = app(BackupManager::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsafe production backup configuration:');

        $manager->create();
    }
}
