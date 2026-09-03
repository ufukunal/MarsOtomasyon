<?php

namespace Tests\Unit;

use App\Foundation\Operations\ProductionCandidateGate;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;

final class ProductionCandidateGateTest extends TestCase
{
    public function test_locked_m23_decisions_require_separate_offsite_backup_boundary(): void
    {
        $app = $this->createMock(Application::class);
        $app->method('environment')->with('production')->willReturn(false);
        $gate = new ProductionCandidateGate($app);

        config()->set('production.deployment_model', 'docker-compose');
        config()->set('production.primary_file_disk', 'local');
        config()->set('m11.backup.disk', 'offsite-backup');
        config()->set('production.backup.offsite_required', true);
        config()->set('production.backup.offsite_target', 's3://mars-backup-prod');
        config()->set('production.backup.recovery_key_reference', 'vault://mars/backup/recovery/v1');
        config()->set('production.backup.rpo_hours', 24);
        config()->set('production.backup.rto_hours', 4);
        config()->set('production.backup.retention.daily', 14);
        config()->set('production.backup.retention.weekly', 8);
        config()->set('production.backup.retention.monthly', 12);

        self::assertSame([], $gate->issues());
        self::assertTrue($gate->satisfied());

        config()->set('m11.backup.disk', 'local');
        self::assertContains('backup-storage-boundary', $gate->issues());
    }
}
