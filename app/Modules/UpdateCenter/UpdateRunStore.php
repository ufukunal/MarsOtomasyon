<?php

namespace App\Modules\UpdateCenter;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class UpdateRunStore
{
    /** @return array<int, stdClass> */
    public function latest(int $limit = 20): array
    {
        return DB::table('update_runs')
            ->latest('id')
            ->limit(max(1, min(100, $limit)))
            ->get()
            ->all();
    }

    public function find(int $runId): stdClass
    {
        return DB::table('update_runs')->where('id', $runId)->first()
            ?? throw new DomainException('Update run not found.');
    }

    public function artifact(int $runId): stdClass
    {
        return DB::table('update_release_artifacts')->where('update_run_id', $runId)->first()
            ?? throw new DomainException('Verified update artifact not found.');
    }

    /** @param array<string, mixed> $metadata */
    public function request(
        string $targetVersion,
        string $channel,
        string $manifestSha256,
        string $packageSha256,
        ?int $requestedByUserId = null,
        ?string $requestKey = null,
        array $metadata = [],
    ): stdClass {
        $requestKey ??= (string) Str::uuid();
        $this->assertRequestKey($requestKey);
        SemanticVersion::assertValid($targetVersion, 'update target version');
        $this->assertChannel($channel);
        $this->assertSha256($manifestSha256, 'manifest');
        $this->assertSha256($packageSha256, 'package');

        return DB::transaction(function () use ($targetVersion, $channel, $manifestSha256, $packageSha256, $requestedByUserId, $requestKey, $metadata): stdClass {
            DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', [$requestKey]);

            $existing = DB::table('update_runs')->where('request_key', $requestKey)->first();
            if ($existing !== null) {
                $this->assertReplayMatches($existing, $targetVersion, $channel, $manifestSha256, $packageSha256);

                return $existing;
            }

            $id = DB::table('update_runs')->insertGetId([
                'request_key' => $requestKey,
                'target_version' => $targetVersion,
                'channel' => $channel,
                'manifest_sha256' => strtolower($manifestSha256),
                'package_sha256' => strtolower($packageSha256),
                'status' => UpdateRunState::Requested->value,
                'requested_by_user_id' => $requestedByUserId,
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->find((int) $id);
        });
    }

    /** @param array<string, mixed> $metadata */
    public function transition(int $runId, UpdateRunState $next, array $metadata = []): stdClass
    {
        return DB::transaction(fn (): stdClass => $this->transitionLocked($runId, $next, $metadata));
    }

    /** @param array<string, mixed> $metadata */
    public function fail(int $runId, string $code, string $message, array $metadata = []): stdClass
    {
        $code = trim($code);
        if ($code === '' || mb_strlen($code) > 96) {
            throw new DomainException('Update failure code is invalid.');
        }

        return DB::transaction(function () use ($runId, $code, $message, $metadata): stdClass {
            $run = DB::table('update_runs')->where('id', $runId)->lockForUpdate()->first();
            if ($run === null) {
                throw new DomainException('Update run not found.');
            }
            $current = UpdateRunState::from((string) $run->status);
            if ($current !== UpdateRunState::Failed) {
                $current->assertCanTransitionTo(UpdateRunState::Failed);
            }

            DB::table('update_runs')->where('id', $runId)->update([
                'status' => UpdateRunState::Failed->value,
                'metadata' => json_encode([...$this->decodeMetadata($run), ...$metadata], JSON_THROW_ON_ERROR),
                'failure_code' => $code,
                'failure_message' => mb_substr($message, 0, 8000),
                'finished_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->find($runId);
        });
    }

    /**
     * Atomically claims one queued apply request for the host-side deploy agent.
     */
    public function claimNextApply(): ?stdClass
    {
        return DB::transaction(function (): ?stdClass {
            $run = DB::table('update_runs')
                ->where('status', UpdateRunState::ApplyRequested->value)
                ->orderBy('id')
                ->lock('for update skip locked')
                ->first();

            if ($run === null) {
                return null;
            }

            return $this->transitionLocked((int) $run->id, UpdateRunState::BackupCreating, [
                'agent_claimed_at' => now()->toIso8601String(),
            ]);
        });
    }

    /** @param array<string, mixed> $metadata */
    public function recordArtifact(
        int $runId,
        string $packagePath,
        string $releasePath,
        string $packageSha256,
        int $packageSizeBytes,
        int $fileCount,
        int $extractedSizeBytes,
        array $metadata = [],
    ): stdClass {
        $this->assertSha256($packageSha256, 'package');
        if ($packageSizeBytes < 1 || $fileCount < 1 || $extractedSizeBytes < 1) {
            throw new DomainException('Update artifact metrics are invalid.');
        }

        DB::transaction(function () use ($runId, $packagePath, $releasePath, $packageSha256, $packageSizeBytes, $fileCount, $extractedSizeBytes, $metadata): void {
            $run = DB::table('update_runs')->where('id', $runId)->lockForUpdate()->first();
            if ($run === null || (string) $run->status !== UpdateRunState::Staging->value) {
                throw new DomainException('Update artifact can only be recorded while staging.');
            }

            DB::table('update_release_artifacts')->updateOrInsert(
                ['update_run_id' => $runId],
                [
                    'package_path' => $packagePath,
                    'release_path' => $releasePath,
                    'package_sha256' => strtolower($packageSha256),
                    'package_size_bytes' => $packageSizeBytes,
                    'file_count' => $fileCount,
                    'extracted_size_bytes' => $extractedSizeBytes,
                    'verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $this->transitionLocked($runId, UpdateRunState::Staged, $metadata);
        });

        return $this->artifact($runId);
    }

    /** @return array<string, mixed> */
    public function metadata(stdClass $run): array
    {
        return $this->decodeMetadata($run);
    }

    /** @param array<string, mixed> $metadata */
    private function transitionLocked(int $runId, UpdateRunState $next, array $metadata): stdClass
    {
        $run = DB::table('update_runs')->where('id', $runId)->lockForUpdate()->first();
        if ($run === null) {
            throw new DomainException('Update run not found.');
        }

        $current = UpdateRunState::from((string) $run->status);
        $current->assertCanTransitionTo($next);

        DB::table('update_runs')->where('id', $runId)->update([
            'status' => $next->value,
            'metadata' => json_encode([...$this->decodeMetadata($run), ...$metadata], JSON_THROW_ON_ERROR),
            'failure_code' => null,
            'failure_message' => null,
            'finished_at' => $next->isFinished() ? now() : null,
            'updated_at' => now(),
        ]);

        return $this->find($runId);
    }

    /** @return array<string, mixed> */
    private function decodeMetadata(stdClass $run): array
    {
        $decoded = json_decode((string) ($run->metadata ?? '{}'), true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }

    private function assertRequestKey(string $requestKey): void
    {
        if (! Str::isUuid($requestKey)) {
            throw new DomainException('Update request key must be a UUID.');
        }
    }

    private function assertChannel(string $channel): void
    {
        if (! in_array($channel, ['stable', 'beta', 'development'], true)) {
            throw new DomainException('Update channel is invalid.');
        }
    }

    private function assertSha256(string $value, string $field): void
    {
        if (preg_match('/^[a-f0-9]{64}$/iD', $value) !== 1) {
            throw new DomainException("Update {$field} SHA-256 is invalid.");
        }
    }

    private function assertReplayMatches(stdClass $run, string $targetVersion, string $channel, string $manifestSha256, string $packageSha256): void
    {
        $matches = hash_equals((string) $run->target_version, $targetVersion)
            && hash_equals((string) $run->channel, $channel)
            && hash_equals((string) $run->manifest_sha256, strtolower($manifestSha256))
            && hash_equals((string) $run->package_sha256, strtolower($packageSha256));

        if (! $matches) {
            throw new DomainException('Update request key replay payload drift detected.');
        }
    }
}
