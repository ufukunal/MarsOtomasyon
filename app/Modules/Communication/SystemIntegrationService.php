<?php

namespace App\Modules\Communication;

use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class SystemIntegrationService
{
    /** @var list<string> */
    public const FAMILIES = ['sms', 'email', 'whatsapp', 'e_document', 'scanner_agent'];

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, scalar|null>|null  $credentials
     */
    public function save(
        int $companyId,
        string $family,
        ?string $providerKey,
        ?string $endpointUrl,
        array $settings,
        ?array $credentials,
        bool $enabled,
    ): void {
        $family = $this->family($family);
        $providerKey = $this->provider($providerKey);
        $endpointUrl = $this->nullableTrim($endpointUrl);
        ksort($settings);
        $existing = DB::table('system_integration_settings')
            ->where('company_id', $companyId)
            ->where('family', $family)
            ->first();

        $ciphertext = $existing?->credentials_ciphertext;
        $fingerprint = $existing?->credential_fingerprint;
        if ($credentials !== null) {
            ksort($credentials);
            $payload = json_encode($credentials, JSON_THROW_ON_ERROR);
            $ciphertext = Crypt::encryptString($payload);
            $fingerprint = hash('sha256', $payload);
        }

        $existingSettings = [];
        if ($existing !== null && $existing->settings !== null) {
            $decoded = json_decode((string) $existing->settings, true, flags: JSON_THROW_ON_ERROR);
            if (is_array($decoded)) {
                $existingSettings = $decoded;
                ksort($existingSettings);
            }
        }
        $settingsJson = json_encode($settings, JSON_THROW_ON_ERROR);
        $materialChanged = $existing === null
            || (string) ($existing->provider_key ?? '') !== (string) ($providerKey ?? '')
            || (string) ($existing->endpoint_url ?? '') !== (string) ($endpointUrl ?? '')
            || $existingSettings !== $settings
            || (string) ($existing->credential_fingerprint ?? '') !== (string) ($fingerprint ?? '')
            || (bool) $existing->is_enabled !== $enabled;
        $verificationStatus = $existing === null ? 'unverified' : (string) $existing->verification_status;
        $lastValidatedAt = $existing === null ? null : $existing->last_validated_at;
        $createdAt = $existing === null ? now() : $existing->created_at;

        DB::table('system_integration_settings')->updateOrInsert(
            ['company_id' => $companyId, 'family' => $family],
            [
                'provider_key' => $providerKey,
                'is_enabled' => $enabled,
                'verification_status' => $materialChanged ? 'unverified' : $verificationStatus,
                'endpoint_url' => $endpointUrl,
                'settings' => $settingsJson,
                'credentials_ciphertext' => $ciphertext,
                'credential_fingerprint' => $fingerprint,
                'last_validated_at' => $materialChanged ? null : $lastValidatedAt,
                'last_validation_error' => null,
                'created_at' => $createdAt,
                'updated_at' => now(),
            ],
        );
    }

    public function validateConfiguration(int $companyId, string $family): void
    {
        $family = $this->family($family);
        $row = DB::table('system_integration_settings')
            ->where('company_id', $companyId)
            ->where('family', $family)
            ->first();
        if ($row === null || ! (bool) $row->is_enabled) {
            throw new DomainException('Integration must be configured and enabled before validation.');
        }
        if ($row->provider_key === null || trim((string) $row->provider_key) === '') {
            throw new DomainException('Integration provider key is required.');
        }
        if ($family === 'scanner_agent' && ($row->endpoint_url === null || trim((string) $row->endpoint_url) === '')) {
            throw new DomainException('Scanner Agent endpoint is required.');
        }

        DB::table('system_integration_settings')
            ->where('id', $row->id)
            ->update([
                'verification_status' => 'configuration_validated',
                'last_validated_at' => now(),
                'last_validation_error' => null,
                'updated_at' => now(),
            ]);
    }

    /** @return Collection<int, SystemIntegrationSummary> */
    public function summaries(int $companyId): Collection
    {
        $rows = DB::table('system_integration_settings')->where('company_id', $companyId)->get()->keyBy('family');

        return collect(self::FAMILIES)->map(function (string $family) use ($rows): SystemIntegrationSummary {
            $row = $rows->get($family);

            return new SystemIntegrationSummary(
                family: $family,
                providerKey: $row === null || $row->provider_key === null ? null : (string) $row->provider_key,
                isEnabled: $row !== null && (bool) $row->is_enabled,
                verificationStatus: $row === null ? 'unverified' : (string) $row->verification_status,
                endpointUrl: $row === null || $row->endpoint_url === null ? null : (string) $row->endpoint_url,
                settings: $row === null || $row->settings === null ? null : (string) $row->settings,
                hasCredentials: $row !== null && $row->credentials_ciphertext !== null,
                lastValidatedAt: $row === null || $row->last_validated_at === null ? null : (string) $row->last_validated_at,
                lastValidationError: $row === null || $row->last_validation_error === null ? null : (string) $row->last_validation_error,
            );
        })->values();
    }

    /** @return array<string, mixed> */
    public function credentials(int $companyId, string $family): array
    {
        $row = DB::table('system_integration_settings')
            ->where('company_id', $companyId)
            ->where('family', $this->family($family))
            ->first();
        if ($row === null || $row->credentials_ciphertext === null) {
            return [];
        }
        $decoded = json_decode(Crypt::decryptString((string) $row->credentials_ciphertext), true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }

    private function family(string $family): string
    {
        $family = mb_strtolower(trim($family));
        if (! in_array($family, self::FAMILIES, true)) {
            throw new DomainException('Unsupported system integration family.');
        }

        return $family;
    }

    private function provider(?string $provider): ?string
    {
        $provider = $this->nullableTrim($provider);
        if ($provider !== null && preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/D', $provider) !== 1) {
            throw new DomainException('Integration provider key must be canonical.');
        }

        return $provider;
    }

    private function nullableTrim(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
