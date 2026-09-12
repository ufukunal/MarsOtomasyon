<?php

namespace Tests\Feature;

use App\Foundation\Operations\ProductionCandidateGate;
use Tests\TestCase;

final class ProductionCandidateBackupStorageTest extends TestCase
{
    public function test_external_mounted_local_disk_is_not_rejected_for_not_being_s3(): void
    {
        config()->set([
            'production.deployment_model' => 'docker-compose',
            'production.primary_file_disk' => 'local',
            'production.backup.offsite_required' => true,
            'production.backup.offsite_target' => 'mount:///mnt/mars-backup',
            'production.backup.recovery_key_reference' => 'vault://mars/backup/recovery/v1',
            'production.backup.rpo_hours' => 24,
            'production.backup.rto_hours' => 4,
            'production.backup.retention.daily' => 14,
            'production.backup.retention.weekly' => 8,
            'production.backup.retention.monthly' => 12,
            'm11.backup.disk' => 'mars_backup_local',
            'filesystems.disks.mars_backup_local.driver' => 'local',
            'filesystems.disks.mars_backup_local.root' => '/mnt/mars-backup',
        ]);

        $issues = app(ProductionCandidateGate::class)->issues();

        self::assertNotContains('backup-storage-driver', $issues);
        self::assertNotContains('backup-storage-boundary', $issues);
        self::assertNotContains('backup-offsite-target', $issues);
    }

    public function test_local_backup_path_inside_application_tree_is_rejected(): void
    {
        config()->set([
            'production.backup.offsite_required' => true,
            'production.backup.offsite_target' => 'mount://unsafe',
            'm11.backup.disk' => 'mars_backup_local',
            'filesystems.disks.mars_backup_local.driver' => 'local',
            'filesystems.disks.mars_backup_local.root' => storage_path('backups'),
        ]);

        $issues = app(ProductionCandidateGate::class)->issues();

        self::assertContains('backup-storage-boundary', $issues);
    }

    public function test_configured_remote_driver_is_provider_agnostic(): void
    {
        config()->set([
            'production.deployment_model' => 'docker-compose',
            'production.primary_file_disk' => 'local',
            'production.backup.offsite_required' => true,
            'production.backup.offsite_target' => 'remote://backup.example.test/mars',
            'production.backup.recovery_key_reference' => 'vault://mars/backup/recovery/v1',
            'production.backup.rpo_hours' => 24,
            'production.backup.rto_hours' => 4,
            'production.backup.retention.daily' => 14,
            'production.backup.retention.weekly' => 8,
            'production.backup.retention.monthly' => 12,
            'm11.backup.disk' => 'custom_remote_backup',
            'filesystems.disks.custom_remote_backup.driver' => 'custom-remote',
        ]);

        $issues = app(ProductionCandidateGate::class)->issues();

        self::assertNotContains('backup-storage-driver', $issues);
        self::assertNotContains('backup-storage-boundary', $issues);
        self::assertNotContains('backup-offsite-target', $issues);
    }
}
