<?php

namespace App\Modules\Communication;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ScannerAgentService
{
    /** @return array{token:string,key_id:string} */
    public function issueEnrollmentToken(int $companyId): array
    {
        $keyId = (string) Str::ulid();
        $secret = Str::random(64);
        DB::table('scanner_enrollment_tokens')->insert([
            'company_id' => $companyId,
            'key_id' => $keyId,
            'secret_hash' => hash('sha256', $secret),
            'expires_at' => now()->addMinutes(max(1, (int) config('m20.scanner.enrollment_ttl_minutes', 15))),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['token' => $keyId.'.'.$secret, 'key_id' => $keyId];
    }

    /** @return array{agent_token:string,public_id:string} */
    public function enroll(string $enrollmentToken, string $name): array
    {
        if (! str_contains($enrollmentToken, '.')) {
            throw new DomainException('Invalid scanner enrollment token.');
        }
        [$keyId, $secret] = explode('.', $enrollmentToken, 2);

        return DB::transaction(function () use ($keyId, $secret, $name): array {
            $row = DB::table('scanner_enrollment_tokens')->where('key_id', $keyId)->lockForUpdate()->first();
            if ($row === null || $row->consumed_at !== null || now()->greaterThan($row->expires_at) || ! hash_equals((string) $row->secret_hash, hash('sha256', $secret))) {
                throw new DomainException('Invalid or expired scanner enrollment token.');
            }
            $name = trim($name);
            if ($name === '') {
                throw new DomainException('Scanner agent name is required.');
            }
            $publicId = (string) Str::ulid();
            $agentSecret = Str::random(64);
            DB::table('scanner_agents')->insert([
                'company_id' => $row->company_id,
                'public_id' => $publicId,
                'name' => mb_substr($name, 0, 160),
                'secret_hash' => hash('sha256', $agentSecret),
                'capabilities' => '{}',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('scanner_enrollment_tokens')->where('id', $row->id)->update(['consumed_at' => now(), 'updated_at' => now()]);

            return ['agent_token' => $publicId.'.'.$agentSecret, 'public_id' => $publicId];
        });
    }

    /** @return array{id:int,company_id:int,public_id:string}|null */
    public function authenticate(?string $plainToken): ?array
    {
        if (! is_string($plainToken) || ! str_contains($plainToken, '.')) {
            return null;
        }
        [$publicId, $secret] = explode('.', $plainToken, 2);
        $row = DB::table('scanner_agents')->where('public_id', $publicId)->first();
        if ($row === null || $row->revoked_at !== null || ! hash_equals((string) $row->secret_hash, hash('sha256', $secret))) {
            return null;
        }

        return ['id' => (int) $row->id, 'company_id' => (int) $row->company_id, 'public_id' => (string) $row->public_id];
    }

    /** @param array<string, mixed> $capabilities */
    public function heartbeat(int $agentId, array $capabilities): void
    {
        DB::table('scanner_agents')->where('id', $agentId)->update([
            'capabilities' => json_encode($capabilities, JSON_THROW_ON_ERROR),
            'last_seen_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $payload */
    public function enqueue(int $companyId, string $agentPublicId, string $operation, array $payload, string $idempotencyKey): string
    {
        if (! Str::isUuid($idempotencyKey)) {
            throw new DomainException('Scanner job idempotency key must be a UUID.');
        }
        $agent = DB::table('scanner_agents')->where('company_id', $companyId)->where('public_id', $agentPublicId)->whereNull('revoked_at')->first();
        if ($agent === null) {
            throw new DomainException('Scanner agent not found.');
        }
        $operation = trim($operation);
        if ($operation === '') {
            throw new DomainException('Scanner operation is required.');
        }
        $existing = DB::table('scanner_agent_jobs')->where('scanner_agent_id', $agent->id)->where('idempotency_key', $idempotencyKey)->first();
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        if ($existing !== null) {
            $existingPayload = json_decode((string) $existing->payload, true, flags: JSON_THROW_ON_ERROR);
            if ((string) $existing->operation !== $operation || ! is_array($existingPayload) || $this->canonicalJson($existingPayload) !== $this->canonicalJson($payload)) {
                throw new DomainException('Scanner job idempotency payload drift detected.');
            }

            return (string) $existing->public_id;
        }
        $publicId = (string) Str::ulid();
        DB::table('scanner_agent_jobs')->insert([
            'company_id' => $companyId,
            'scanner_agent_id' => $agent->id,
            'public_id' => $publicId,
            'idempotency_key' => $idempotencyKey,
            'operation' => $operation,
            'payload' => $encoded,
            'status' => 'queued',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $publicId;
    }

    /** @return array{public_id:string,operation:string,payload:array<string,mixed>}|null */
    public function claim(int $agentId): ?array
    {
        return DB::transaction(function () use ($agentId): ?array {
            $job = DB::table('scanner_agent_jobs')->where('scanner_agent_id', $agentId)->where('status', 'queued')->orderBy('id')->lockForUpdate()->first();
            if ($job === null) {
                return null;
            }
            DB::table('scanner_agent_jobs')->where('id', $job->id)->update(['status' => 'running', 'claimed_at' => now(), 'updated_at' => now()]);
            $payload = json_decode((string) $job->payload, true, flags: JSON_THROW_ON_ERROR);

            return ['public_id' => (string) $job->public_id, 'operation' => (string) $job->operation, 'payload' => is_array($payload) ? $payload : []];
        });
    }

    /** @param array<string, mixed> $result */
    public function complete(int $agentId, string $jobPublicId, array $result): void
    {
        $updated = DB::table('scanner_agent_jobs')->where('scanner_agent_id', $agentId)->where('public_id', $jobPublicId)->where('status', 'running')->update([
            'status' => 'completed',
            'result' => json_encode($result, JSON_THROW_ON_ERROR),
            'completed_at' => now(),
            'updated_at' => now(),
        ]);
        if ($updated === 0 && ! DB::table('scanner_agent_jobs')->where('scanner_agent_id', $agentId)->where('public_id', $jobPublicId)->where('status', 'completed')->exists()) {
            throw new DomainException('Scanner job cannot complete from current status.');
        }
    }

    public function fail(int $agentId, string $jobPublicId, string $error): void
    {
        $updated = DB::table('scanner_agent_jobs')->where('scanner_agent_id', $agentId)->where('public_id', $jobPublicId)->where('status', 'running')->update([
            'status' => 'failed',
            'last_error' => mb_substr($error, 0, 4000),
            'completed_at' => now(),
            'updated_at' => now(),
        ]);
        if ($updated === 0) {
            throw new DomainException('Scanner job cannot fail from current status.');
        }
    }

    /** @param array<mixed> $value */
    private function canonicalJson(array $value): string
    {
        return json_encode($this->sortJsonObjectKeys($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function sortJsonObjectKeys(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortJsonObjectKeys($item);
            }
        }

        return $value;
    }
}
