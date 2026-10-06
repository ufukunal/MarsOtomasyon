<?php

namespace App\Support\Channels;

use App\Models\Period\ChannelSyncError;
use App\Models\Period\ChannelSyncEvent;
use App\Models\SalesChannelAccount;
use App\Support\Audit\AuditContext;
use App\Support\Period\PeriodContext;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;

final class ChannelSyncRecorder
{
    public function __construct(private readonly ChannelSensitiveDataRedactor $redactor) {}

    /** @param array<string,mixed>|null $safeMetadata */
    public function queue(
        int $channelAccountId,
        string $direction,
        string $entityType,
        string $action,
        ?int $entityId = null,
        ?string $externalId = null,
        ?string $payloadHash = null,
        ?string $correlationId = null,
        ?array $safeMetadata = null,
    ): ChannelSyncEvent {
        PeriodContext::ensureWritable();

        $account = SalesChannelAccount::query()->findOrFail($channelAccountId);

        if ((int) $account->company_id !== (int) PeriodContext::companyId()) {
            throw new DomainException('Kanal sync hesabı aktif şirkete ait değil.');
        }

        if (! in_array($direction, ['outbound', 'inbound'], true)) {
            throw new DomainException('Geçersiz kanal sync yönü.');
        }

        $entityType = trim($entityType);
        $action = trim($action);

        if ($entityType === '' || $action === '') {
            throw new DomainException('Kanal sync entity/action boş olamaz.');
        }

        if ($payloadHash !== null && ! preg_match('/^[a-f0-9]{64}$/D', $payloadHash)) {
            throw new DomainException('Kanal sync payload hash SHA-256 biçiminde olmalıdır.');
        }

        $this->assertSafeMetadata($safeMetadata);

        return DB::connection('period')->transaction(function () use (
            $channelAccountId,
            $direction,
            $entityType,
            $action,
            $entityId,
            $externalId,
            $payloadHash,
            $correlationId,
            $safeMetadata,
        ): ChannelSyncEvent {
            $this->lockCoalesceKey(
                $channelAccountId,
                $direction,
                $entityType,
                $action,
                $entityId,
                $externalId,
            );

            $query = ChannelSyncEvent::query()
                ->where('channel_account_id', $channelAccountId)
                ->where('direction', $direction)
                ->where('entity_type', $entityType)
                ->where('action', $action)
                ->whereIn('status', ['queued', 'processing'])
                ->when(
                    $entityId === null,
                    fn ($builder) => $builder->whereNull('entity_id'),
                    fn ($builder) => $builder->where('entity_id', $entityId),
                )
                ->when(
                    $externalId === null,
                    fn ($builder) => $builder->whereNull('external_id'),
                    fn ($builder) => $builder->where('external_id', $externalId),
                )
                ->orderByDesc('id')
                ->lockForUpdate();

            $pending = $query->first();

            if ($pending) {
                if ($pending->status === 'queued' && $payloadHash !== null) {
                    $pending->payload_hash = $payloadHash;
                    $pending->safe_metadata = $safeMetadata ?? $pending->safe_metadata;
                    $pending->save();
                }

                return $pending->refresh();
            }

            return ChannelSyncEvent::query()->create([
                'channel_account_id' => $channelAccountId,
                'direction' => $direction,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'external_id' => $externalId,
                'action' => $action,
                'status' => 'queued',
                'attempts' => 0,
                'correlation_id' => $correlationId ?: (string) Str::uuid(),
                'payload_hash' => $payloadHash,
                'safe_metadata' => $safeMetadata,
            ]);
        }, attempts: 3);
    }

    public function startAttempt(ChannelSyncEvent $event): ChannelSyncEvent
    {
        return DB::connection('period')->transaction(function () use ($event): ChannelSyncEvent {
            $locked = ChannelSyncEvent::query()->lockForUpdate()->findOrFail($event->id);

            if ($locked->status === 'success') {
                return $locked;
            }

            $staleProcessing = $locked->status === 'processing'
                && $locked->last_attempt_at !== null
                && $locked->last_attempt_at->lessThanOrEqualTo(now()->subMinutes(15));

            if ($locked->status === 'processing' && ! $staleProcessing) {
                throw new DomainException('Kanal sync event başka bir worker tarafından işleniyor.');
            }

            if (! in_array($locked->status, ['queued', 'failed', 'processing'], true)) {
                throw new DomainException('Kanal sync event attempt için uygun durumda değil.');
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

    /** @param array<string,mixed>|null $metadata */
    private function assertSafeMetadata(?array $metadata, string $path = 'safe_metadata'): void
    {
        if ($metadata === null) {
            return;
        }

        foreach ($metadata as $key => $value) {
            $name = strtolower((string) $key);

            if (preg_match(
                '/(^|[_-])(authorization|password|secret|token|credential|api[_-]?key|access[_-]?key|consumer[_-]?secret|buyer|customer|recipient|email|phone|address)([_-]|$)/',
                $name,
            )) {
                throw new DomainException($path.'.'.$key.' hassas veri içeremez.');
            }

            if (is_array($value)) {
                $this->assertSafeMetadata($value, $path.'.'.$key);
            }
        }

        try {
            $encoded = json_encode(
                $metadata,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException $exception) {
            throw new DomainException(
                'Kanal sync safe_metadata JSON olarak saklanabilir olmalıdır.',
                previous: $exception,
            );
        }

        if (strlen($encoded) > 65535) {
            throw new DomainException('Kanal sync safe_metadata güvenli boyut sınırını aşıyor.');
        }
    }

    private function lockCoalesceKey(
        int $channelAccountId,
        string $direction,
        string $entityType,
        string $action,
        ?int $entityId,
        ?string $externalId,
    ): void {
        $scope = implode('|', [
            'channel-sync',
            $channelAccountId,
            $direction,
            $entityType,
            $action,
            $entityId === null ? 'null' : (string) $entityId,
            $externalId ?? 'null',
        ]);

        DB::connection('period')->select(
            'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
            [$scope],
        );
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

            AuditContext::period(
                'Kanal sync hatası resolved işaretlendi.',
                [
                    'channel_sync_error_id' => $locked->id,
                    'channel_sync_event_id' => $locked->channel_sync_event_id,
                ],
                $locked,
                'channel_sync_error_resolved',
            );

            return $locked->refresh();
        }, attempts: 3);
    }
}
