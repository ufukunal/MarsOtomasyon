<?php

namespace Tests\Feature;

use App\Foundation\Clock\Clock;
use App\Foundation\Outbox\OutboxConflict;
use App\Foundation\Outbox\OutboxEventCatalog;
use App\Foundation\Outbox\OutboxMessageDraft;
use App\Foundation\Outbox\OutboxRetryCapability;
use App\Foundation\Outbox\OutboxSemantic;
use App\Foundation\Outbox\OutboxStatus;
use App\Foundation\Outbox\OutboxStore;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Tests\Support\FrozenClock;
use Tests\TestCase;

final class OutboxStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(Clock::class, new FrozenClock('2026-08-24T12:30:00Z'));
        DB::table('outbox_messages')->delete();
        DB::table('idempotency_records')->delete();
    }

    protected function tearDown(): void
    {
        DB::table('outbox_messages')->delete();
        DB::table('idempotency_records')->delete();

        parent::tearDown();
    }

    public function test_source_and_outbox_commit_atomically(): void
    {
        $result = DB::transaction(function () {
            $this->insertSource('commit-source');

            return $this->store()->append($this->draft('commit-source', 1));
        });

        self::assertTrue($result->isNew);
        self::assertDatabaseHas('idempotency_records', ['idempotency_key' => 'commit-source']);
        self::assertDatabaseCount('outbox_messages', 1);

        $row = DB::table('outbox_messages')->where('effect_key', 'system.smoke:commit-source')->first();
        self::assertNotNull($row);
        self::assertSame(OutboxEventCatalog::SYSTEM_SMOKE_V1, $row->event_name);
        self::assertSame(1, $row->schema_version);
        self::assertSame(OutboxSemantic::ImmutableEventSnapshot->value, $row->semantic_class);
        self::assertSame(OutboxRetryCapability::SafeRetry->value, $row->retry_capability);
        self::assertSame(OutboxStatus::Pending->value, $row->status);
        self::assertSame('2026-08-24T12:30:00+00:00', (new \DateTimeImmutable((string) $row->occurred_at))->format(DATE_ATOM));
    }

    public function test_source_and_outbox_roll_back_atomically(): void
    {
        try {
            DB::transaction(function (): void {
                $this->insertSource('rollback-source');
                $this->store()->append($this->draft('rollback-source', 2));

                throw new RuntimeException('simulate business rollback');
            });
        } catch (RuntimeException) {
            // Expected.
        }

        self::assertDatabaseMissing('idempotency_records', ['idempotency_key' => 'rollback-source']);
        self::assertDatabaseMissing('outbox_messages', ['effect_key' => 'system.smoke:rollback-source']);
    }

    public function test_duplicate_effect_replays_original_message_without_second_row(): void
    {
        $first = DB::transaction(fn () => $this->store()->append($this->draft('duplicate-source', 3)));
        $second = DB::transaction(fn () => $this->store()->append($this->draft('duplicate-source', 3)));

        self::assertTrue($first->isNew);
        self::assertFalse($second->isNew);
        self::assertTrue($second->isReplay());
        self::assertSame($first->eventId, $second->eventId);
        self::assertDatabaseCount('outbox_messages', 1);
    }

    public function test_duplicate_effect_with_changed_content_is_conflict(): void
    {
        DB::transaction(fn () => $this->store()->append($this->draft('conflict-source', 4)));

        $this->expectException(OutboxConflict::class);

        DB::transaction(fn () => $this->store()->append($this->draft('conflict-source', 5)));
    }

    public function test_sensitive_payload_field_is_rejected_without_persisting_secret(): void
    {
        try {
            DB::transaction(fn () => $this->store()->append(new OutboxMessageDraft(
                effectKey: 'system.smoke:secret-source',
                eventName: OutboxEventCatalog::SYSTEM_SMOKE_V1,
                payload: ['source' => 'm0', 'sequence' => 6, 'client_secret' => 'do-not-store'],
                correlationId: '01J60Y0J8G4E2V7Z9Q1N3M5K7P',
                sourceType: 'foundation.smoke',
                sourceId: 'secret-source',
                sourceVersion: 1,
            )));

            self::fail('Sensitive outbox payload should have been rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringNotContainsString('do-not-store', $exception->getMessage());
        }

        self::assertDatabaseMissing('outbox_messages', ['effect_key' => 'system.smoke:secret-source']);
    }

    public function test_append_is_rejected_outside_business_transaction(): void
    {
        $this->expectException(LogicException::class);

        $this->store()->append($this->draft('outside-transaction', 7));
    }

    private function store(): OutboxStore
    {
        return $this->app->make(OutboxStore::class);
    }

    private function draft(string $sourceId, int $sequence): OutboxMessageDraft
    {
        return new OutboxMessageDraft(
            effectKey: 'system.smoke:'.$sourceId,
            eventName: OutboxEventCatalog::SYSTEM_SMOKE_V1,
            payload: ['source' => 'm0', 'sequence' => $sequence],
            correlationId: '01J60Y0J8G4E2V7Z9Q1N3M5K7P',
            sourceType: 'foundation.smoke',
            sourceId: $sourceId,
            sourceVersion: 1,
        );
    }

    private function insertSource(string $key): void
    {
        $now = $this->app->make(Clock::class)->now();

        DB::table('idempotency_records')->insert([
            'scope' => 'outbox.source',
            'idempotency_key' => $key,
            'fingerprint' => hash('sha256', $key),
            'status' => 'in_progress',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
