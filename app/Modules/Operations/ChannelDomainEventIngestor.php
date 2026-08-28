<?php

namespace App\Modules\Operations;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class ChannelDomainEventIngestor
{
    public function __construct(private ChannelDomainSync $domainSync) {}

    /** @return array{entity_type:string,local_type:string,local_id:int,external_id:string}|null */
    public function process(int $eventId): ?array
    {
        $event = DB::table('integration_events')->where('id', $eventId)->first();
        if ($event === null || in_array((string) $event->status, ['processed', 'ignored'], true)) {
            return null;
        }
        $connection = DB::table('integration_connections')
            ->where('company_id', $event->company_id)
            ->where('id', $event->connection_id)
            ->where('status', 'active')
            ->first();
        if ($connection === null) {
            throw new RuntimeException('Integration connection is not active for domain ingestion.');
        }
        $payload = json_decode((string) $event->payload, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($payload)) {
            throw new RuntimeException('Integration event payload is invalid for domain ingestion.');
        }

        return $this->domainSync->ingest($connection, $event, $payload);
    }
}
