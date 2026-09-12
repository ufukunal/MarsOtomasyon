<?php

namespace App\Modules\UpdateCenter;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class UpdateRunStore
{
    private const GLOBAL_LOCK = 'mars-update-center-global';

    /** @return array<int, stdClass> */
    public function latest(int $limit = 20): array
    {
        return DB::table('update_runs')->latest('id')->limit(max(1, min(100, $limit)))->get()->all();
    }

    public function active(): ?stdClass
    {
        return DB::table('update_runs')
            ->whereNotIn('status', [
                UpdateRunState::Activated->value,
                UpdateRunState::RolledBack->value,
                UpdateRunState::Failed->value,
            ])
            ->latest('id')
            ->first();
    }

    public function find(int $runId): stdClass
    {
        $run = DB::table('update_runs')->where('id', $runId)->first();
        if ($run === null) {
            throw new DomainException('Update run not found.');
        }

        return $run;
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
            $this->lockGlobal();

            $existing = DB::table('update_runs')->where('request_key', $requestKey)->first();
            if ($existing !== null) {
                $this->assertReplayMatches($existing, $targetVersion, $channel, $manifestSha256, $packageSha256);

                return $existing;
            }

            $active = DB::table('update_runs')
                ->whereNotIn('status', [UpdateRunState::Activated->value, UpdateRunState::RolledBack->value, UpdateRunState::Failed->value])
                ->lockForUpdate()
                ->first();
            if ($active !== null) {
                throw new DomainException('Another update run is already active.');
            }

            $id = DB::table('update_runs')->insertGetId([
                'request_key' => $requestKey,
                'target_version' => $targetVersion,
                'channel' => $channel,
                'manifest_sha256' => strtolower($manifestSha256),
                'package_sha256' => strtolower($packageSha256),
                'status' => UpdateRunState::Requested->value,
                'requested_by_user_id' => $requestedByUserId,
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('update_runs')->where('id', $id)->firstOrFail();
        });
    }

    /** @param array<string, mixed> $metadata */
    public function transition(int $runId, UpdateRunState $next, array $metadata = []): stdClass
    {
        return DB::transaction(function () use ($runId, $next, $metadata): stdClass {
            $this->lockGlobal();

            return $this->transitionLocked($runId, $next, $metadata);
        });
    }

    /** @param array<string, mixed> $metadata */
    public function annotate(int $runId, array $metadata): stdClass
    {
        return DB::transaction(function () use ($runId, $metadata): stdClass {
            $this->lockGlobal();
            $run = DB::table('update_runs')->where('id', $runId)->lockForUpdate()->first();
            if ($run === null) {
                throw new DomainException('Update run not found.');
            }
            if (UpdateRunState::from((string) $run->status)->isTerminal()) {
                throw new DomainException('Terminal update runs cannot be modified.');
            }

            DB::table('update_runs')->where('id', $runId)->update([
                'metadata' => json_encode([...$this->metadata($run), ...$metadata], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);

            return DB::table('update_runs')->where('id', $runId)->firstOrFail();
        });
    }

    /** @param array<string, mixed> $metadata */
    public function fail(int $runId, string $code, string $message, array $metadata = []): stdClass
    {
        $code = trim($code);
        if ($code === '' || mb_strlen($code) > 96 || preg_match('/^[a-z0-9_.-]+$/i', $code) !== 1) {
            throw new DomainException('Update failure code is invalid.');
        }

        $message = trim(preg_replace('/[\r\n\t]+/', ' ', $message) ?? 'Update failed.');
        $message = mb_substr($message === '' ? 'Update failed.' : $message, 0, 500);

        return DB::transaction(function () use ($runId, $code, $message, $metadata): stdClass {
            $this->lockGlobal();
            $this->transitionLocked($runId, UpdateRunState::Failed, $metadata);

            DB::table('update_runs')->where('id', $runId)->update([
                'failure_code' => $code,
                'failure_message' => $message,
                'updated_at' => now(),
            ]);

            return DB::table('update_runs')->where('id', $runId)->firstOrFail();
        });
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
            'metadata' => json_encode([...$this->metadata($run), ...$metadata], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'finished_at' => $next->isTerminal() ? now() : null,
            'updated_at' => now(),
        ]);

        return DB::table('update_runs')->where('id', $runId)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function metadata(stdClass $run): array
    {
        $value = json_decode((string) ($run->metadata ?? '{}'), true, flags: JSON_THROW_ON_ERROR);

        return is_array($value) ? $value : [];
    }

    private function lockGlobal(): void
    {
        DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', [self::GLOBAL_LOCK]);
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
        if (preg_match('/^[a-f0-9]{64}$/i', $value) !== 1) {
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
