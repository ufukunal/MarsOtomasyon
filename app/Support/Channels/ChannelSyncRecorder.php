<?php

namespace App\Support\Channels;

use App\Models\Period\ChannelSyncError;
use App\Models\Period\ChannelSyncEvent;
use App\Models\SalesChannelAccount;
use App\Support\Period\PeriodContext;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ChannelSyncRecorder
{
    public function __construct(private readonly ChannelSensitiveDataRedactor $redactor) {}

    public function queue(
        int $channelAccountId,
        string $direction,
        string $entityType,
        string $action,
        ?int $entityId = null,
        ?string $externalId = null,
        ?string $payloadHash = null,
        ?string $correlationId = null,
    ): ChannelSyncEvent {
        PeriodContext::ensureWritable();

        if (! in_array($direction, ['outbound', 'inbound'], true)) {
            throw new DomainException('Geçersiz kanal sync yönü.');
        }

        return ChannelSyncEvent::query()->create([
            'channel_account_id' => $channelAccountId,
            'direction' => $direction,
            'entity_type' => trim($entityType),
            'entity_id' => $entityId,
            'external_id' => $externalId,
            'action' => trim($action),
            'status' => 'queued',
            'attempts' => 0,
            'correlation_id' => $correlationId ?: (string) Str::uuid(),
            'payload_hash' => $payloadHash,
        ]);
    }

    public function startAttempt(ChannelSyncEvent $event): ChannelSyncEvent
    {
        return DB::connection('period')->transaction(function () use ($event): ChannelSyncEvent {
            $locked = ChannelSyncEvent::query()->lockForUpdate()->findOrFail($event->id);

            if ($locked->status === 'success') {
                return $locked;
            }

            $locked->status = 'processing';
            $locked->attempts = (int) $locked->attempts + 1;
            $locked->last_attempt_at = now();
            $locked->error_summary = null;
            $locked->save();

            return $locked->refresh();
        }, attempts: 3);
    }

    public function markSuccess(ChannelSyncEvent $event, ?string $externalId = null): ChannelSyncEvent
    {
        return DB::connection('period')->transaction(function () use ($event, $externalId): ChannelSyncEvent {
            $locked = ChannelSyncEvent::query()->lockForUpdate()->findOrFail($event->id);
            $locked->status = 'success';
            $locked->external_id = $externalId ?? $locked->external_id;
            $locked->error_summary = null;
            $locked->save();

            return $locked->refresh();
        }, attempts: 3);
    }

    /**
     * @return int|null Retry delay in seconds. Null means persistent error.
     */
    public function markFailure(ChannelSyncEvent $event, string $errorSummary): ?int
    {
        return DB::connection('period')->transaction(function () use ($event, $errorSummary): ?int {
            $locked = ChannelSyncEvent::query()->lockForUpdate()->findOrFail($event->id);
            $account = SalesChannelAccount::query()->findOrFail((int) $locked->channel_account_id);
            $safeSummary = $this->redactor->redact($errorSummary, $account);
            $locked->status = 'failed';
            $locked->error_summary = $safeSummary;
            $locked->save();

            $delays = array_values(array_map('intval', config('channels.retry_delays', [30, 60, 120])));
            $attempts = (int) $locked->attempts;

            if ($attempts >= 1 && $attempts <= count($delays)) {
                return $delays[$attempts - 1];
            }

            ChannelSyncError::query()->firstOrCreate(
                ['channel_sync_event_id' => $locked->id],
                ['error_summary' => $safeSummary],
            );

            return null;
        }, attempts: 3);
    }

    public function resolveError(ChannelSyncError $error): ChannelSyncError
    {
        return DB::connection('period')->transaction(function () use ($error): ChannelSyncError {
            $locked = ChannelSyncError::query()->lockForUpdate()->findOrFail($error->id);

            if ($locked->resolved_at !== null) {
                return $locked;
            }

            $actor = auth()->user();
            $locked->resolved_at = now();
            $locked->resolved_by = $actor?->id;
            $locked->resolved_by_name = $actor?->name;
            $locked->save();

            return $locked->refresh();
        }, attempts: 3);
    }
}
