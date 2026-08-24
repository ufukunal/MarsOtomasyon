<?php

namespace Tests\Feature;

use App\Foundation\Idempotency\IdempotencyStore;
use App\Foundation\Idempotency\RequestFingerprint;
use LogicException;
use Tests\TestCase;

final class IdempotencyTransactionBoundaryTest extends TestCase
{
    public function test_claim_is_rejected_outside_business_transaction(): void
    {
        $this->expectException(LogicException::class);

        $this->app->make(IdempotencyStore::class)->claim(
            'api.test',
            'outside-transaction',
            RequestFingerprint::fromPayload(['operation' => 'invalid']),
        );
    }
}
