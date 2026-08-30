<?php

namespace App\Modules\Operations;

use App\Modules\Operations\Jobs\ProcessIntegrationEvent;
use DomainException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ChannelEventStore
{
    /**
     * @param  object{id:mixed,company_id:mixed}  $connection
     * @param  array<array-key,mixed>  $payload
     */
    public function persist(object $connection, string $externalEventId, string $eventType, array $payload): int
    {
        $eventType = $this->canonicalEventType($eventType);
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        if (strlen($encoded) > (int) config('m11.integrations.max_payload_bytes', 1048576)) {
            throw new DomainException('Integration payload exceeds configured limit.');
        }

        $externalEventId = trim($externalEventId);
        if ($externalEventId === '') {
            $externalEventId = hash('sha256', $eventType.'|'.$encoded);
        }
        if (strlen($externalEventId) > 160) {
            throw new DomainException('Integration external event id is too long.');
        }
        $hash = hash('sha256', $encoded);

        return DB::transaction(function () use ($connection, $externalEventId, $eventType, $payload, $hash): int {
            $inserted = DB::table('integration_events')->insertOrIgnore([
                'company_id' => $connection->company_id,
                'connection_id' => $connection->id,
                'external_event_id' => $externalEventId,
                'event_type' => $eventType,
                'payload_sha256' => $hash,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'status' => 'received',
                'attempts' => 0,
                'available_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $event = DB::table('integration_events')
                ->where('company_id', $connection->company_id)
                ->where('connection_id', $connection->id)
                ->where('external_event_id', $externalEventId)
                ->lockForUpdate()
                ->first();
            if ($event === null) {
                throw new RuntimeException('Integration event could not be persisted.');
            }
            /** @var object{id:mixed,payload_sha256:mixed,event_type:mixed} $event */
            if ((string) $event->payload_sha256 !== $hash || (string) $event->event_type !== $eventType) {
                throw new DomainException('Integration event replay payload drift detected.');
            }

            if ($inserted > 0) {
                ProcessIntegrationEvent::dispatch((int) $event->id)->afterCommit();
            }

            return (int) $event->id;
        });
    }

    private function canonicalEventType(string $eventType): string
    {
        $eventType = strtolower(trim($eventType));
        $eventType = str_replace(['/', ':', ' '], '.', $eventType);
        $eventType = preg_replace('/[^a-z0-9._-]+/', '', $eventType) ?? '';
        if ($eventType === '' || strlen($eventType) > 96) {
            throw new DomainException('Invalid integration event type.');
        }

        return $eventType;
    }
}
