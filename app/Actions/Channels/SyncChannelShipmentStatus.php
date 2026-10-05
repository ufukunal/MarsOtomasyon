<?php

namespace App\Actions\Channels;

use App\Models\Period\ChannelOrderSnapshot;
use App\Models\Period\Document;
use App\Models\SalesChannelAccount;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Channels\ChannelAdapterResolver;
use App\Support\Channels\ChannelPayloadHasher;
use App\Support\Channels\ChannelSyncRecorder;
use App\Support\Period\PeriodContext;
use DomainException;
use Throwable;

final class SyncChannelShipmentStatus
{
    public function __construct(
        private readonly ChannelAdapterResolver $adapters,
        private readonly ChannelPayloadHasher $hasher,
        private readonly ChannelSyncRecorder $sync,
    ) {}

    /** @param list<array<string,int|string>> $lines */
    public function handle(
        Document $salesOrder,
        string $status,
        array $lines = [],
        ?string $invoiceNumber = null,
    ): void {
        MutationAuthorizer::authorize('channel_sync.update');
        PeriodContext::ensureWritable();

        $snapshot = ChannelOrderSnapshot::query()
            ->where('sales_order_id', $salesOrder->id)
            ->firstOrFail();
        $account = SalesChannelAccount::query()->findOrFail($snapshot->channel_account_id);
        $packageId = (int) ($snapshot->external_package_id ?? 0);

        if ($packageId <= 0) {
            throw new DomainException('Kanal sipariş snapshotında shipment package id bulunamadı.');
        }

        $payload = [
            'package_id' => $packageId,
            'status' => $status,
            'lines' => $lines,
            'invoice_number' => $invoiceNumber,
        ];
        $event = $this->sync->queue(
            channelAccountId: (int) $account->id,
            direction: 'outbound',
            entityType: 'shipment',
            action: strtolower($status),
            entityId: (int) $salesOrder->id,
            externalId: (string) $packageId,
            payloadHash: $this->hasher->hash($payload),
            safeMetadata: [
                'status' => $status,
                'lines' => $lines,
                'invoice_number' => $invoiceNumber,
            ],
        );
        $attempt = $this->sync->startAttempt($event);

        try {
            $result = $this->adapters->resolve($account)->pushShipmentStatus($account, $payload);

            if (! $result->success) {
                throw new DomainException($result->message ?: 'Kanal shipment status sync başarısız.');
            }

            $this->sync->markSuccess($attempt, $result->externalId);
        } catch (Throwable $exception) {
            $this->sync->markFailure($attempt, $exception->getMessage());

            throw $exception;
        }
    }
}
