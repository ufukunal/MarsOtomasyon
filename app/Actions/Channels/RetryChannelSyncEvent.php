<?php

namespace App\Actions\Channels;

use App\Models\Period\ChannelProductListing;
use App\Models\Period\ChannelSyncEvent;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use DomainException;

final class RetryChannelSyncEvent
{
    public function __construct(private readonly SyncChannelListing $listings) {}

    public function handle(ChannelSyncEvent $event): void
    {
        MutationAuthorizer::authorize('channel_sync.update');

        if ($event->status !== 'failed' || $event->entity_type !== 'listing' || $event->entity_id === null) {
            throw new DomainException('Bu sync event manuel retry için uygun değil.');
        }

        $listing = ChannelProductListing::query()->findOrFail($event->entity_id);

        match ($event->action) {
            'publish' => $this->listings->publish($listing),
            'content' => $this->listings->content($listing),
            'stock' => $this->listings->stock($listing),
            'price' => $this->listings->price($listing),
            default => throw new DomainException('Desteklenmeyen listing sync action.'),
        };

        AuditContext::period(
            'Kanal sync manuel retry tetiklendi.',
            ['source_sync_event_id' => $event->id],
            $event,
            'channel_sync_manual_retry',
        );
    }
}
