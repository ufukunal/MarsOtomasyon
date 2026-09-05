<?php

namespace Tests\Unit;

use App\Foundation\Operations\EnforceProductionSafety;
use App\Foundation\Operations\ProductionSafetyState;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class EnforceProductionSafetyTest extends TestCase
{
    public function test_recovery_mode_allows_reads_and_blocks_mutations(): void
    {
        $middleware = new EnforceProductionSafety(new ProductionSafetyState(
            recoveryMode: true,
            outboundProvidersEnabled: true,
            asyncWorkEnabled: true,
            schedulerWorkEnabled: true,
            retryAfterSeconds: 180,
            disabledProviders: [],
        ));

        $read = $middleware->handle(Request::create('/products', 'GET'), static fn (): Response => new Response('ok', 200));
        self::assertSame(200, $read->getStatusCode());

        $write = $middleware->handle(Request::create('/products', 'POST'), static fn (): Response => new Response('ok', 200));
        self::assertSame(503, $write->getStatusCode());
        self::assertSame('180', $write->headers->get('Retry-After'));
        self::assertStringContainsString('recovery_mode', (string) $write->getContent());
    }
}
