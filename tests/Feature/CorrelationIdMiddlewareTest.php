<?php

namespace Tests\Feature;

use Symfony\Component\Uid\Ulid;
use Tests\TestCase;

final class CorrelationIdMiddlewareTest extends TestCase
{
    public function test_missing_correlation_id_is_generated_and_returned(): void
    {
        $response = $this->get('/up');
        $id = (string) $response->headers->get('X-Correlation-ID');

        $response->assertOk();
        self::assertTrue(Ulid::isValid($id));
    }

    public function test_valid_inbound_correlation_id_is_preserved(): void
    {
        $this->withHeader('X-Correlation-ID', 'partner-01:request.42')
            ->get('/up')
            ->assertOk()
            ->assertHeader('X-Correlation-ID', 'partner-01:request.42');
    }

    public function test_invalid_or_overlong_correlation_id_is_replaced(): void
    {
        foreach (['contains space', str_repeat('a', 65)] as $invalid) {
            $response = $this->withHeader('X-Correlation-ID', $invalid)->get('/up');
            $id = (string) $response->headers->get('X-Correlation-ID');

            $response->assertOk();
            self::assertNotSame($invalid, $id);
            self::assertTrue(Ulid::isValid($id));
        }
    }
}
