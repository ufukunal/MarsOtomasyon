<?php

namespace App\Modules\Imports\Migration;

use DomainException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

final class LegacyMigrationControl
{
    public function registerSource(int $companyId, string $sourceKey, string $label, string $sourceFingerprint): int
    {
        $sourceKey = strtolower(trim($sourceKey));
        $label = trim($label);
        $sourceFingerprint = strtolower(trim($sourceFingerprint));
        if ($companyId < 1 || $sourceKey === '' || $label === '' || preg_match('/^[a-f0-9]{64}$/', $sourceFingerprint) !== 1) {
            throw new DomainException('Migration source identity is invalid.');
        }

        return DB::transaction(function () use ($companyId, $sourceKey, $label, $sourceFingerprint): int {
            DB::table('migration_sources')->insertOrIgnore([
                'company_id' => $companyId,
                'source_key' => $sourceKey,
                'label' => mb_substr($label, 0, 160),
                'source_fingerprint' => $sourceFingerprint,
                'status' => 'inventory',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $source = DB::table('migration_sources')
                ->where('company_id', $companyId)
                ->where('source_key', $sourceKey)
                ->lockForUpdate()
                ->first();
            if ($source === null) {
                throw new RuntimeException('Migration source could not be persisted.');
            }
            if (! hash_equals((string) $source->source_fingerprint, $sourceFingerprint)) {
                throw new DomainException('Migration source fingerprint drift detected.');
            }

            return (int) $source->id;
        });
    }

    /** @param  array<string, mixed>  $payload */
    public function stageRecord(
        int $companyId,
        int $sourceId,
        string $entityType,
        string $sourceIdentity,
        array $payload,
        bool $dryRun = true,
    ): int {
        $entityType = strtolower(trim($entityType));
        $sourceIdentity = trim($sourceIdentity);
        if ($entityType === '' || $sourceIdentity === '') {
            throw new DomainException('Migration record source identity is required.');
        }
        $payloadHash = $this->payloadHash($payload);

        return DB::transaction(function () use ($companyId, $sourceId, $entityType, $sourceIdentity, $payloadHash, $dryRun): int {
            $this->assertSource($companyId, $sourceId);
            DB::table('migration_source_records')->insertOrIgnore([
                'company_id' => $companyId,
                'migration_source_id' => $sourceId,
                'entity_type' => $entityType,
                'source_identity' => mb_substr($sourceIdentity, 0, 191),
                'payload_sha256' => $payloadHash,
                'status' => 'staged',
                'dry_run' => $dryRun,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $record = DB::table('migration_source_records')
                ->where('company_id', $companyId)
                ->where('migration_source_id', $sourceId)
                ->where('entity_type', $entityType)
                ->where('source_identity', mb_substr($sourceIdentity, 0, 191))
                ->lockForUpdate()
                ->first();
            if ($record === null) {
                throw new RuntimeException('Migration source record could not be persisted.');
            }
            if (! hash_equals((string) $record->payload_sha256, $payloadHash)) {
                throw new DomainException('Migration source payload drift detected for stable source identity.');
            }
            if (! $dryRun && (bool) $record->dry_run && (string) $record->status !== 'imported') {
                DB::table('migration_source_records')->where('id', $record->id)->update([
                    'dry_run' => false,
                    'updated_at' => now(),
                ]);
            }

            return (int) $record->id;
        });
    }

    public function markImported(int $companyId, int $recordId, string $targetType, int $targetId): void
    {
        $targetType = strtolower(trim($targetType));
        if ($targetType === '' || $targetId < 1) {
            throw new DomainException('Migration target identity is invalid.');
        }

        DB::transaction(function () use ($companyId, $recordId, $targetType, $targetId): void {
            $record = DB::table('migration_source_records')
                ->where('company_id', $companyId)
                ->where('id', $recordId)
                ->lockForUpdate()
                ->first();
            if ($record === null) {
                throw new DomainException('Migration source record was not found.');
            }
            if ((bool) $record->dry_run) {
                throw new DomainException('Dry-run migration record cannot be linked to a live domain target.');
            }
            if ((string) $record->status === 'imported') {
                if ((string) $record->target_type !== $targetType || (int) $record->target_id !== $targetId) {
                    throw new DomainException('Migration source identity is already linked to another target.');
                }

                return;
            }

            DB::table('migration_source_records')->where('id', $recordId)->update([
                'status' => 'imported',
                'target_type' => mb_substr($targetType, 0, 80),
                'target_id' => $targetId,
                'last_error' => null,
                'imported_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /** @param  array<string, mixed>  $details */
    public function recordReconciliation(
        int $companyId,
        int $sourceId,
        string $checkpointKey,
        string $scope,
        ?string $expectedValue,
        ?string $actualValue,
        bool $passed,
        array $details = [],
    ): void {
        $checkpointKey = strtolower(trim($checkpointKey));
        $scope = strtolower(trim($scope));
        if ($checkpointKey === '' || $scope === '') {
            throw new DomainException('Migration reconciliation identity is required.');
        }
        $this->assertSource($companyId, $sourceId);

        DB::table('migration_reconciliation_checks')->updateOrInsert([
            'company_id' => $companyId,
            'migration_source_id' => $sourceId,
            'checkpoint_key' => mb_substr($checkpointKey, 0, 120),
        ], [
            'scope' => mb_substr($scope, 0, 80),
            'expected_value' => $expectedValue === null ? null : mb_substr($expectedValue, 0, 191),
            'actual_value' => $actualValue === null ? null : mb_substr($actualValue, 0, 191),
            'passed' => $passed,
            'details' => $details === [] ? null : json_encode($details, JSON_THROW_ON_ERROR),
            'checked_at' => now(),
            'updated_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function recordChannelCheckpoint(
        int $companyId,
        int $sourceId,
        string $provider,
        string $channelIdentity,
        bool $enabled,
        bool $paused,
        ?string $cursor = null,
        ?string $watermark = null,
        ?string $inboxMarker = null,
    ): void {
        $provider = strtolower(trim($provider));
        $channelIdentity = trim($channelIdentity);
        if ($provider === '' || $channelIdentity === '') {
            throw new DomainException('Migration channel identity is required.');
        }
        $this->assertSource($companyId, $sourceId);

        DB::table('migration_channel_checkpoints')->updateOrInsert([
            'company_id' => $companyId,
            'migration_source_id' => $sourceId,
            'provider' => mb_substr($provider, 0, 80),
            'channel_identity' => mb_substr($channelIdentity, 0, 191),
        ], [
            'is_enabled' => $enabled,
            'is_paused' => $paused,
            'cursor' => $cursor,
            'watermark' => $watermark,
            'inbox_marker' => $inboxMarker === null ? null : mb_substr($inboxMarker, 0, 191),
            'checked_at' => now(),
            'updated_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function markRehearsed(int $companyId, int $sourceId): void
    {
        if (! $this->reconciliationPassed($companyId, $sourceId)) {
            throw new DomainException('Migration rehearsal reconciliation has not passed.');
        }

        DB::table('migration_sources')
            ->where('company_id', $companyId)
            ->where('id', $sourceId)
            ->update(['status' => 'ready', 'last_rehearsed_at' => now(), 'updated_at' => now()]);
    }

    public function readyForCutover(int $companyId, int $sourceId): bool
    {
        $source = $this->assertSource($companyId, $sourceId);
        if ((string) $source->status !== 'ready' || ! $this->reconciliationPassed($companyId, $sourceId)) {
            return false;
        }

        return ! DB::table('migration_channel_checkpoints')
            ->where('company_id', $companyId)
            ->where('migration_source_id', $sourceId)
            ->where('is_enabled', true)
            ->where('is_paused', false)
            ->exists();
    }

    public function beginCutover(int $companyId, int $sourceId): void
    {
        if (! $this->readyForCutover($companyId, $sourceId)) {
            throw new DomainException('Migration cutover gate is not satisfied.');
        }

        DB::table('migration_sources')
            ->where('company_id', $companyId)
            ->where('id', $sourceId)
            ->where('status', 'ready')
            ->update(['status' => 'cutover', 'cutover_started_at' => now(), 'updated_at' => now()]);
    }

    public function completeCutover(int $companyId, int $sourceId): void
    {
        $updated = DB::table('migration_sources')
            ->where('company_id', $companyId)
            ->where('id', $sourceId)
            ->where('status', 'cutover')
            ->update(['status' => 'completed', 'completed_at' => now(), 'updated_at' => now()]);
        if ($updated !== 1) {
            throw new DomainException('Migration source is not in cutover state.');
        }
    }

    private function reconciliationPassed(int $companyId, int $sourceId): bool
    {
        $checks = DB::table('migration_reconciliation_checks')
            ->where('company_id', $companyId)
            ->where('migration_source_id', $sourceId);

        return (clone $checks)->exists() && ! (clone $checks)->where('passed', false)->exists();
    }

    private function assertSource(int $companyId, int $sourceId): stdClass
    {
        $source = DB::table('migration_sources')
            ->where('company_id', $companyId)
            ->where('id', $sourceId)
            ->first();
        if ($source === null) {
            throw new DomainException('Migration source was not found for company.');
        }

        return $source;
    }

    /** @param  array<string, mixed>  $payload */
    private function payloadHash(array $payload): string
    {
        return hash('sha256', json_encode(
            $this->canonicalize($payload),
            JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
