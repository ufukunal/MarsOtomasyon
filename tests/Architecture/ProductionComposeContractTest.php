<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ProductionComposeContractTest extends TestCase
{
    public function test_production_compose_keeps_web_worker_and_scheduler_as_separate_processes(): void
    {
        $compose = file_get_contents(dirname(__DIR__, 2).'/docker-compose.production.yml');
        self::assertIsString($compose);

        foreach (['postgres:', 'valkey:', 'app:', 'worker:', 'scheduler:', 'web:'] as $service) {
            self::assertStringContainsString("  {$service}", $compose);
        }

        self::assertStringContainsString('["php-fpm", "-F"]', $compose);
        self::assertStringContainsString('["php", "artisan", "queue:work"', $compose);
        self::assertStringContainsString('["php", "artisan", "schedule:work"]', $compose);
        self::assertStringNotContainsString('supervisord', strtolower($compose));
    }

    public function test_production_image_uses_postgresql_18_backup_client_and_does_not_embed_secrets(): void
    {
        $dockerfile = file_get_contents(dirname(__DIR__, 2).'/Dockerfile.production');
        self::assertIsString($dockerfile);
        self::assertStringContainsString('postgresql-client-18', $dockerfile);
        self::assertStringContainsString('USER www-data', $dockerfile);
        self::assertStringNotContainsString('APP_KEY=', $dockerfile);
        self::assertStringNotContainsString('DB_PASSWORD=', $dockerfile);
    }
}
