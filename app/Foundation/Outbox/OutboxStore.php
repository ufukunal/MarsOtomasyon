<?php

namespace App\Foundation\Outbox;

use App\Foundation\Clock\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final readonly class OutboxStore
{
    public function __construct(
        private Clock $clock,
        private OutboxEventCatalog $catalog,
    ) {
    }

    public function append(OutboxMessageDraft $draft): OutboxAppendResult
    {
        $this->assertInsideTransaction();

        $definition = $this->catalog->definition($draft->eventName);
        OutboxPayload::validate($draft->payload, $definition);

        $payloadJson = OutboxPayload::canonicalJson($draft->payload);
        $effectFingerprint = $this->effectFingerprint($draft, $payloadJson);
        $now = $this->clock->now();
        $eventId = (string) Str::ulid();

        $inserted = DB::table('outbox_messages')->insertOrIgnore([
            'event_id' => $eventId,
            'effect_key' => $draft->effectKey,
            'event_name' => $definition->name,
            'schema_version' => $definition->schemaVersion,
            'semantic_class' => $definition->semantic->value,
            'retry_capability' => $definition->retryCapability->value,
            'payload' => $payloadJson,
            'effect_fingerprint' => $effectFingerprint,
            'correlation_id' => $draft->correlationId,
            'company_id' => $draft->companyId,
            'source_type' => $draft->sourceType,
            'source_id' => $draft->sourceId,
            'source_version' => $draft->sourceVersion,
            'status' => OutboxStatus::Pending->value,
            'attempts' => 0,
            'available_at' => $now,
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $record = DB::table('outbox_messages')
            ->where('effect_key', $draft->effectKey)
            ->lockForUpdate()
            ->first();

        if ($record === null) {
            throw new LogicException('Outbox message could not be appended.');
        }

        if (! hash_equals((string) $record->effect_fingerprint, $effectFingerprint)) {
            throw new OutboxConflict('Outbox effect key was already used with different event content.');
        }

        return new OutboxAppendResult(
            recordId: (int) $record->id,
            eventId: (string) $record->event_id,
            isNew: $inserted === 1,
        );
    }

    private function assertInsideTransaction(): void
    {
        if (DB::connection()->transactionLevel() < 1) {
            throw new LogicException('Outbox messages must be appended inside the business database transaction.');
        }
    }

    private function effectFingerprint(OutboxMessageDraft $draft, string $payloadJson): string
    {
        return hash('sha256', implode('|', [
            $draft->eventName,
            $payloadJson,
            (string) ($draft->companyId ?? ''),
            (string) ($draft->sourceType ?? ''),
            (string) ($draft->sourceId ?? ''),
            (string) ($draft->sourceVersion ?? ''),
        ]));
    }
}
