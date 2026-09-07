<?php

namespace App\Modules\UpdateCenter;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class UpdateRunStore
{
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
        $this->assertSha256($manifestSha256, 'manifest');
        $this->assertSha256($packageSha256, 'package');

        return DB::transaction(function () use ($targetVersion, $channel, $manifestSha256, $packageSha256, $requestedByUserId, $requestKey, $metadata): stdClass {
            $existing = DB::table('update_runs')->where('request_key', $requestKey)->lockForUpdate()->first();
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

            return DB::table('update_runs')->where('id', $id)->firstOrFail();
        });
    }

    /** @param array<string, mixed> $metadata */
    public function transition(int $runId, UpdateRunState $next, array $metadata = []): stdClass
    {
        return DB::transaction(function () use ($runId, $next, $metadata): stdClass {
            $run = DB::table('update_runs')->where('id', $runId)->lockForUpdate()->first();
            if ($run === null) {
                throw new DomainException('Update run not found.');
            }

            $current = UpdateRunState::from((string) $run->status);
            $current->assertCanTransitionTo($next);

            $existingMetadata = json_decode((string) $run->metadata, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($existingMetadata)) {
                $existingMetadata = [];
            }

            DB::table('update_runs')->where('id', $runId)->update([
                'status' => $next->value,
                'metadata' => json_encode([...$existingMetadata, ...$metadata], JSON_THROW_ON_ERROR),
                'finished_at' => $next->isTerminal() ? now() : null,
                'updated_at' => now(),
            ]);

            return DB::table('update_runs')->where('id', $runId)->firstOrFail();
        });
    }

    /** @param array<string, mixed> $metadata */
    public function fail(int $runId, string $code, string $message, array $metadata = []): stdClass
    {
        $run = $this->transition($runId, UpdateRunState::Failed, $metadata);

        DB::table('update_runs')->where('id', $runId)->update([
            'failure_code' => $code,
            'failure_message' => $message,
            'updated_at' => now(),
        ]);

        return DB::table('update_runs')->where('id', $runId)->firstOrFail();
    }

    private function assertRequestKey(string $requestKey): void
    {
        if (! Str::isUuid($requestKey)) {
            throw new DomainException('Update request key must be a UUID.');
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
