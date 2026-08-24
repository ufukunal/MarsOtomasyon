<?php

namespace Tests\Feature;

use App\Foundation\Clock\Clock;
use App\Foundation\Idempotency\IdempotencyConflict;
use App\Foundation\Idempotency\IdempotencyStatus;
use App\Foundation\Idempotency\IdempotencyStore;
use App\Foundation\Idempotency\RequestFingerprint;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\FrozenClock;
use Tests\TestCase;

final class IdempotencyStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(Clock::class, new FrozenClock('2026-08-24T12:00:00Z'));
    }

    public function test_same_key_and_fingerprint_is_retry_safe(): void
    {
        $fingerprint = RequestFingerprint::fromPayload(['document' => 42, 'amount' => '10.00']);
        $store = $this->app->make(IdempotencyStore::class);

        [$first, $second] = DB::transaction(function () use ($store, $fingerprint): array {
            return [
                $store->claim('api.test', 'retry-001', $fingerprint),
                $store->claim('api.test', 'retry-001', $fingerprint),
            ];
        });

        self::assertTrue($first->isNew);
        self::assertFalse($second->isNew);
        self::assertTrue($second->isReplay());
        self::assertDatabaseCount('idempotency_records', 1);
    }

    public function test_same_key_with_different_fingerprint_is_conflict(): void
    {
        $store = $this->app->make(IdempotencyStore::class);

        DB::transaction(fn () => $store->claim(
            'api.test',
            'conflict-001',
            RequestFingerprint::fromPayload(['amount' => '10.00']),
        ));

        $this->expectException(IdempotencyConflict::class);

        DB::transaction(fn () => $store->claim(
            'api.test',
            'conflict-001',
            RequestFingerprint::fromPayload(['amount' => '11.00']),
        ));
    }

    public function test_claim_rolls_back_with_business_transaction(): void
    {
        $store = $this->app->make(IdempotencyStore::class);

        try {
            DB::transaction(function () use ($store): void {
                $store->claim(
                    'api.test',
                    'rollback-001',
                    RequestFingerprint::fromPayload(['operation' => 'rollback']),
                );

                throw new RuntimeException('simulate business rollback');
            });
        } catch (RuntimeException) {
            // Expected.
        }

        self::assertDatabaseMissing('idempotency_records', [
            'scope' => 'api.test',
            'idempotency_key' => 'rollback-001',
        ]);
    }

    public function test_completed_claim_records_frozen_clock_time(): void
    {
        $store = $this->app->make(IdempotencyStore::class);

        DB::transaction(function () use ($store): void {
            $claim = $store->claim(
                'api.test',
                'complete-001',
                RequestFingerprint::fromPayload(['operation' => 'complete']),
            );

            $store->complete($claim);
        });

        $row = DB::table('idempotency_records')->where('idempotency_key', 'complete-001')->first();

        self::assertNotNull($row);
        self::assertSame(IdempotencyStatus::Completed->value, $row->status);
        self::assertSame(
            '2026-08-24T12:00:00+00:00',
            (new DateTimeImmutable((string) $row->completed_at))->format(DATE_ATOM),
        );
    }
}
