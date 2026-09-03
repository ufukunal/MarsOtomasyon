<?php

namespace Tests\Feature;

use App\Foundation\Clock\Clock;
use App\Foundation\Outbox\OutboxLeaseManager;
use App\Foundation\Outbox\OutboxRetryCapability;
use App\Foundation\Outbox\OutboxStatus;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Support\FrozenClock;
use Tests\TestCase;

final class OutboxLeaseManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(Clock::class, new FrozenClock('2026-09-03T12:30:00Z'));
        DB::table('outbox_messages')->delete();
    }

    protected function tearDown(): void
    {
        DB::table('outbox_messages')->delete();
        parent::tearDown();
    }

    public function test_pending_message_is_leased_with_owner_attempt_and_expiry(): void
    {
        $id = $this->insertMessage(OutboxRetryCapability::QueryBeforeRetry);

        self::assertSame([$id], $this->manager()->claim('worker-a', 10, 90));

        $row = DB::table('outbox_messages')->where('id', $id)->first();
        self::assertNotNull($row);
        self::assertSame(OutboxStatus::Leased->value, $row->status);
        self::assertSame('worker-a', $row->lease_owner);
        self::assertSame(1, $row->attempts);
        self::assertSame('2026-09-03T12:31:30+00:00', (new \DateTimeImmutable((string) $row->lease_expires_at))->format(DATE_ATOM));
    }

    public function test_expired_safe_retry_lease_can_be_reclaimed_but_ambiguous_contract_cannot(): void
    {
        $safe = $this->insertMessage(OutboxRetryCapability::SafeRetry, OutboxStatus::Leased, 1, 'worker-dead', '2026-09-03T12:29:00Z');
        $ambiguous = $this->insertMessage(OutboxRetryCapability::QueryBeforeRetry, OutboxStatus::Leased, 1, 'worker-dead', '2026-09-03T12:29:00Z');

        self::assertSame([$safe], $this->manager()->claim('worker-b'));

        self::assertSame(1, $this->manager()->quarantineExpiredAmbiguous());
        self::assertDatabaseHas('outbox_messages', [
            'id' => $ambiguous,
            'status' => OutboxStatus::Failed->value,
            'last_error_code' => 'AMBIGUOUS_OUTCOME_REVIEW_REQUIRED',
        ]);
    }

    public function test_failure_auto_retries_only_safe_or_idempotent_contracts(): void
    {
        $safe = $this->insertMessage(OutboxRetryCapability::IdempotentWithKey);
        $manual = $this->insertMessage(OutboxRetryCapability::NeverAutoRetry);
        $this->manager()->claim('worker-a', 10);

        $this->manager()->fail($safe, 'worker-a', 'TEMPORARY', 'retry me', 45);
        $this->manager()->fail($manual, 'worker-a', 'UNKNOWN', 'do not retry', 45);

        self::assertDatabaseHas('outbox_messages', ['id' => $safe, 'status' => OutboxStatus::Pending->value]);
        self::assertDatabaseHas('outbox_messages', ['id' => $manual, 'status' => OutboxStatus::Failed->value]);
    }

    public function test_wrong_owner_cannot_complete_a_lease(): void
    {
        $id = $this->insertMessage(OutboxRetryCapability::SafeRetry);
        $this->manager()->claim('worker-a');

        $this->expectException(LogicException::class);
        $this->manager()->complete($id, 'worker-b');
    }

    private function manager(): OutboxLeaseManager
    {
        return $this->app->make(OutboxLeaseManager::class);
    }

    private function insertMessage(
        OutboxRetryCapability $capability,
        OutboxStatus $status = OutboxStatus::Pending,
        int $attempts = 0,
        ?string $owner = null,
        ?string $leaseExpiresAt = null,
    ): int {
        $sequence = (int) DB::table('outbox_messages')->count() + 1;
        $eventId = str_pad((string) $sequence, 26, '0', STR_PAD_LEFT);
        $now = $this->app->make(Clock::class)->now();

        return (int) DB::table('outbox_messages')->insertGetId([
            'event_id' => $eventId,
            'effect_key' => 'm23.outbox:'.$sequence,
            'event_name' => 'system.smoke.v1',
            'schema_version' => 1,
            'semantic_class' => 'IMMUTABLE_EVENT_SNAPSHOT',
            'retry_capability' => $capability->value,
            'payload' => '{}',
            'effect_fingerprint' => hash('sha256', 'm23-'.$sequence),
            'correlation_id' => str_pad((string) $sequence, 26, '1', STR_PAD_LEFT),
            'status' => $status->value,
            'attempts' => $attempts,
            'available_at' => $now,
            'occurred_at' => $now,
            'leased_at' => $owner === null ? null : '2026-09-03 12:28:00+00',
            'lease_expires_at' => $leaseExpiresAt,
            'lease_owner' => $owner,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
