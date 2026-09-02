<?php

namespace App\Modules\Communication;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ApiAccessTokenService
{
    /**
     * @param list<string> $permissions
     * @return array{token:string,key_id:string}
     */
    public function issue(int $companyId, string $name, array $permissions, ?\DateTimeInterface $expiresAt = null): array
    {
        $name = trim($name);
        if ($name === '') {
            throw new DomainException('API token name is required.');
        }
        $permissions = array_values(array_unique(array_filter(array_map('trim', $permissions))));
        if ($permissions === []) {
            throw new DomainException('At least one API permission is required.');
        }
        sort($permissions);
        $keyId = (string) Str::ulid();
        $secret = Str::random(64);
        DB::table('api_access_tokens')->insert([
            'company_id' => $companyId,
            'key_id' => $keyId,
            'name' => mb_substr($name, 0, 160),
            'secret_hash' => hash('sha256', $secret),
            'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['token' => $keyId.'.'.$secret, 'key_id' => $keyId];
    }

    /** @return array{id:int,company_id:int,key_id:string,permissions:list<string>}|null */
    public function authenticate(?string $plainToken): ?array
    {
        if (!is_string($plainToken) || !str_contains($plainToken, '.')) {
            return null;
        }
        [$keyId, $secret] = explode('.', $plainToken, 2);
        if (strlen($keyId) !== 26 || $secret === '') {
            return null;
        }
        $row = DB::table('api_access_tokens')->where('key_id', $keyId)->first();
        if ($row === null || $row->revoked_at !== null || ($row->expires_at !== null && now()->greaterThan($row->expires_at))) {
            return null;
        }
        if (!hash_equals((string) $row->secret_hash, hash('sha256', $secret))) {
            return null;
        }
        $decoded = json_decode((string) $row->permissions, true, flags: JSON_THROW_ON_ERROR);
        $permissions = is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
        DB::table('api_access_tokens')->where('id', $row->id)->update(['last_used_at' => now(), 'updated_at' => now()]);

        return ['id' => (int) $row->id, 'company_id' => (int) $row->company_id, 'key_id' => (string) $row->key_id, 'permissions' => $permissions];
    }

    public function revoke(int $companyId, string $keyId): void
    {
        DB::table('api_access_tokens')->where('company_id', $companyId)->where('key_id', $keyId)->update(['revoked_at' => now(), 'updated_at' => now()]);
    }
}
