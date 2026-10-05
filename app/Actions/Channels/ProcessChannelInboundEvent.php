<?php

namespace App\Actions\Channels;

use App\DataObjects\Channels\ChannelInboundEvent;
use App\Models\Period\Document;
use App\Models\SalesChannelAccount;
use App\Support\Channels\ChannelExternalEventRegistryService;
use App\Support\Channels\ChannelPayloadHasher;
use App\Support\Channels\ChannelSyncRecorder;
use App\Support\Period\PeriodContext;
use DomainException;
use Throwable;

final class ProcessChannelInboundEvent
{
    public function __construct(
        private readonly ChannelExternalEventRegistryService $registry,
        private readonly ChannelPayloadHasher $hasher,
        private readonly ChannelSyncRecorder $sync,
        private readonly ImportChannelOrder $orders,
        private readonly ImportChannelCancellation $cancellations,
        private readonly ImportChannelReturn $returns,
    ) {}

    public function handle(
        SalesChannelAccount $account,
        ChannelInboundEvent $event,
    ): ?Document {
        if ((int) $account->company_id !== (int) PeriodContext::companyId()) {
            throw new DomainException('Inbound kanal hesabı aktif şirkete ait değil.');
        }

        $syncEvent = $this->sync->queue(
            channelAccountId: (int) $account->id,
            direction: 'inbound',
            entityType: 'channel_'.$event->eventType,
            action: 'import',
            externalId: $event->externalId,
            payloadHash: $this->hasher->hash($event->data),
        );
        $attempt = $this->sync->startAttempt($syncEvent);
        $reservation = null;

        try {
            $reservation = $this->registry->reserve(
                $account,
                $event->eventType,
                $event->externalId,
                $event->occurredAt,
            );

            if (! $reservation->reserved) {
                $this->sync->markSuccess($attempt, $event->externalId);

                if ($reservation->status === 'done'
                    && (int) $reservation->periodId === (int) PeriodContext::periodId()
                    && $reservation->periodDocumentId) {
                    return Document::query()->find((int) $reservation->periodDocumentId);
                }

                return null;
            }

            $document = match ($event->eventType) {
                'order' => $this->orders->handle($account, $event),
                'cancel' => $this->cancellations->handle($account, $event),
                'return' => $this->returns->handle($account, $event),
                default => throw new DomainException('Desteklenmeyen inbound kanal event tipi.'),
            };

            $this->registry->markDone(
                $reservation->registryId,
                (int) PeriodContext::periodId(),
                (int) $document->id,
            );
            $this->sync->markSuccess($attempt, $event->externalId);

            return $document;
        } catch (Throwable $exception) {
            if ($reservation?->reserved) {
                $this->registry->markFailed($reservation->registryId);
            }

            $this->sync->markFailure($attempt, $exception->getMessage());

            throw $exception;
        }
    }
}
