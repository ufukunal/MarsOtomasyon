<?php

namespace Tests\Feature;

use App\Foundation\Health\ReadinessCheck;
use App\Foundation\Health\ReadinessResult;
use Tests\TestCase;

final class HealthReadinessTest extends TestCase
{
    public function test_liveness_is_available_with_correlation_header(): void
    {
        $response = $this->withHeader('X-Correlation-ID', 'health-live-001')->get('/up');

        $response->assertOk();
        $response->assertHeader('X-Correlation-ID', 'health-live-001');
    }

    public function test_readiness_is_ok_when_postgresql_and_valkey_are_available(): void
    {
        $response = $this->withHeader('X-Correlation-ID', 'health-ready-001')->getJson('/health/ready');

        $response->assertOk()->assertExactJson([
            'status' => 'ok',
            'correlation_id' => 'health-ready-001',
        ]);
        $response->assertHeader('X-Correlation-ID', 'health-ready-001');
    }

    public function test_readiness_returns_minimal_503_without_dependency_details(): void
    {
        $this->app->instance(ReadinessCheck::class, new class implements ReadinessCheck
        {
            public function check(): ReadinessResult
            {
                return new ReadinessResult(false, ['postgresql', 'valkey']);
            }
        });

        $response = $this->withHeader('X-Correlation-ID', 'health-failed-001')->getJson('/health/ready');

        $response->assertStatus(503)->assertExactJson([
            'status' => 'unavailable',
            'correlation_id' => 'health-failed-001',
        ]);

        $body = (string) $response->getContent();
        self::assertStringNotContainsString('postgresql', $body);
        self::assertStringNotContainsString('valkey', $body);
        self::assertStringNotContainsString('exception', strtolower($body));
    }

    public function test_readiness_route_does_not_use_web_session_middleware(): void
    {
        $route = $this->app['router']->getRoutes()->getByName('health.ready');

        self::assertNotNull($route);
        self::assertNotContains('web', $route->gatherMiddleware());
    }
}
