<?php

namespace App\Support\Channels;

use App\DataObjects\Channels\ChannelExternalEventReservation;
use App\Models\ChannelExternalEventRegistry;
use App\Models\SalesChannelAccount;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ChannelExternalEventRegistryService
{
    public function reserve(
        SalesChannelAccount $account,
        string $eventType,
        string $externalId,
        ?CarbonImmutable $occurredAt = null,
    ): ChannelExternalEventReservation {
        if (! in_array($eventType, ['order', 'cancel', 'return'], true)) {
            throw new DomainException('Geçersiz kanal external event tipi.');
        }

        $externalId = trim($externalId);

        if ($externalId === '') {
            throw new DomainException('External event id boş olamaz.');
        }

        return DB::connection('master')->transaction(function () use (
            $account,
            $eventType,
            $externalId,
            $occurredAt,
        ): ChannelExternalEventReservation {
            $this->lockIdentity($account->id, $eventType, $externalId);

            $event = ChannelExternalEventRegistry::query()->firstOrCreate(
                [
                    'channel_account_id' => $account->id,
                    'event_type' => $eventType,
                    'external_id' => $externalId,
                ],
                [
                    'external_occurred_at' => $occurredAt,
                    'status' => 'processing',
                ],
            );

            if ($event->wasRecentlyCreated) {
                return new ChannelExternalEventReservation(
                    registryId: (int) $event->id,
                    reserved: true,
                    status: 'processing',
                );
            }

            $locked = ChannelExternalEventRegistry::query()
                ->lockForUpdate()
                ->findOrFail($event->id);

            $staleProcessing = $locked->status === 'processing'
                && $locked->updated_at !== null
                && CarbonImmutable::parse($locked->updated_at)->lessThanOrEqualTo(now()->subMinutes(15));

            if ($locked->status === 'failed' || $staleProcessing) {
                $locked->status = 'processing';
                $locked->external_occurred_at ??= $occurredAt;
                $locked->period_id = null;
                $locked->period_document_id = null;
                $locked->save();

                return new ChannelExternalEventReservation(
                    registryId: (int) $locked->id,
                    reserved: true,
                    status: 'processing',
                    periodId: $locked->period_id,
                    periodDocumentId: $locked->period_document_id,
                );
            }

            return new ChannelExternalEventReservation(
                registryId: (int) $locked->id,
                reserved: false,
                status: (string) $locked->status,
                periodId: $locked->period_id,
                periodDocumentId: $locked->period_document_id,
            );
        }, attempts: 3);
    }

    private function lockIdentity(int $accountId, string $eventType, string $externalId): void
    {
        DB::connection('master')->select(
            'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
            [implode('|', ['channel-registry', $accountId, $eventType, $externalId])],
        );
    }

    public function markDone(int $registryId, int $periodId, int $periodDocumentId): void
    {
        DB::connection('master')->transaction(function () use ($registryId, $periodId, $periodDocumentId): void {
            $event = ChannelExternalEventRegistry::query()->lockForUpdate()->findOrFail($registryId);

            if ($event->status === 'done') {
                if ((int) $event->period_id !== $periodId
                    || (int) $event->period_document_id !== $periodDocumentId) {
                    throw new DomainException('External event registry provenance çakışması.');
                }

                return;
            }

            $event->status = 'done';
            $event->period_id = $periodId;
            $event->period_document_id = $periodDocumentId;
            $event->save();
        }, attempts: 3);
    }

    public function rebindCarriedOrder(
        SalesChannelAccount $account,
        string $externalId,
        int $sourcePeriodId,
        int $sourceDocumentId,
        int $targetPeriodId,
        int $targetDocumentId,
    ): void {
        if ($sourcePeriodId <= 0
            || $sourceDocumentId <= 0
            || $targetPeriodId <= 0
            || $targetDocumentId <= 0
            || $sourcePeriodId === $targetPeriodId) {
            throw new DomainException('Channel registry carry provenance id değerleri geçersiz.');
        }

        DB::connection('master')->transaction(function () use (
            $account,
            $externalId,
            $sourcePeriodId,
            $sourceDocumentId,
            $targetPeriodId,
            $targetDocumentId,
        ): void {
            $event = ChannelExternalEventRegistry::query()
                ->where('channel_account_id', $account->id)
                ->where('event_type', 'order')
                ->where('external_id', trim($externalId))
                ->lockForUpdate()
                ->first();

            if (! $event || $event->status !== 'done') {
                throw new DomainException('Carried channel order için tamamlanmış Master registry kaydı bulunamadı.');
            }

            if ((int) $event->period_id === $targetPeriodId
                && (int) $event->period_document_id === $targetDocumentId) {
                return;
            }

            if ((int) $event->period_id !== $sourcePeriodId
                || (int) $event->period_document_id !== $sourceDocumentId) {
                throw new DomainException('Channel registry source carry provenance çakışması.');
            }

            $event->period_id = $targetPeriodId;
            $event->period_document_id = $targetDocumentId;
            $event->save();
        }, attempts: 3);
    }

    public function markFailed(int $registryId): void
    {
        DB::connection('master')->transaction(function () use ($registryId): void {
            $event = ChannelExternalEventRegistry::query()->lockForUpdate()->findOrFail($registryId);

            if ($event->status !== 'done') {
                $event->status = 'failed';
                $event->save();
            }
        }, attempts: 3);
    }
}
