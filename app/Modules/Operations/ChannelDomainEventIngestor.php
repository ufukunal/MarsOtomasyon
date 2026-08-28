<?php

namespace App\Modules\Operations;

use App\Foundation\Correlation\CorrelationContext;
use App\Foundation\Correlation\CorrelationIdFactory;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class ChannelDomainEventIngestor
{
    public function __construct(
        private ChannelDomainSync $domainSync,
        private CorrelationContext $correlation,
        private CorrelationIdFactory $correlationIds,
    ) {}

    /** @return array{entity_type:string,local_type:string,local_id:int,external_id:string}|null */
    public function process(int $eventId): ?array
    {
        $event = DB::table('integration_events')->where('id', $eventId)->first();
        if ($event === null || in_array((string) $event->status, ['processed', 'ignored'], true)) {
            return null;
        }
        /** @var object{status:mixed,company_id:mixed,connection_id:mixed,event_type:mixed,external_event_id:mixed,payload_sha256:mixed,payload:mixed} $event */
        $connection = DB::table('integration_connections')
            ->where('company_id', $event->company_id)
            ->where('id', $event->connection_id)
            ->where('status', 'active')
            ->first();
        if ($connection === null) {
            throw new RuntimeException('Integration connection is not active for domain ingestion.');
        }
        /** @var object{provider:mixed,credentials_ciphertext?:mixed} $connection */
        $payload = json_decode((string) $event->payload, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($payload)) {
            throw new RuntimeException('Integration event payload is invalid for domain ingestion.');
        }

        $previousCorrelationId = $this->correlation->id();
        if ($previousCorrelationId === null) {
            $this->correlation->set($this->correlationIds->resolve(null));
        }

        try {
            return $this->domainSync->ingest($connection, $event, $payload);
        } finally {
            if ($previousCorrelationId === null) {
                $this->correlation->clear();
            } else {
                $this->correlation->set($previousCorrelationId);
            }
        }
    }
}
