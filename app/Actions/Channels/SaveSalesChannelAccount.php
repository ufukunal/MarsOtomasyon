<?php

namespace App\Actions\Channels;

use App\Enums\SalesChannelPlatform;
use App\Models\SalesChannelAccount;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Period\PeriodContext;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SaveSalesChannelAccount
{
    /**
     * @param  array<string,mixed>|null  $credentials
     * @param  array<string,mixed>|null  $settings
     */
    public function handle(
        string $platform,
        string $name,
        ?string $externalStoreId,
        ?array $credentials,
        ?array $settings,
        bool $isActive,
        ?SalesChannelAccount $account = null,
        ?int $expectedVersion = null,
    ): SalesChannelAccount {
        MutationAuthorizer::authorize('channel_accounts.'.($account ? 'update' : 'create'));

        $companyId = PeriodContext::companyId();

        if ($companyId === null) {
            throw new DomainException('Kanal hesabı için aktif şirket bağlamı gereklidir.');
        }

        $platformEnum = SalesChannelPlatform::from($platform);
        $name = trim($name);
        $this->assertSettingsDoNotContainSecrets($settings ?? []);

        if ($name === '') {
            throw new DomainException('Kanal hesap adı zorunludur.');
        }

        return DB::connection('master')->transaction(function () use (
            $companyId,
            $platformEnum,
            $name,
            $externalStoreId,
            $credentials,
            $settings,
            $isActive,
            $account,
            $expectedVersion,
        ): SalesChannelAccount {
            if ($account) {
                $locked = SalesChannelAccount::query()
                    ->where('company_id', $companyId)
                    ->lockForUpdate()
                    ->findOrFail($account->id);

                if ($locked->platform !== $platformEnum) {
                    throw new DomainException('Kanal platformu hesap oluşturulduktan sonra değiştirilemez.');
                }

                $attributes = [
                    'name' => $name,
                    'external_store_id' => trim((string) $externalStoreId) ?: null,
                    'settings' => $settings,
                    'is_active' => $isActive,
                ];

                if ($credentials !== null) {
                    $attributes['credentials_encrypted'] = $credentials;
                }

                $saved = $locked->updateWithVersion(
                    $attributes,
                    $expectedVersion ?? (int) $locked->version,
                );
            } else {
                $saved = SalesChannelAccount::query()->create([
                    'company_id' => $companyId,
                    'platform' => $platformEnum,
                    'name' => $name,
                    'external_store_id' => trim((string) $externalStoreId) ?: null,
                    'credentials_encrypted' => $credentials ?? [],
                    'settings' => $settings,
                    'is_active' => $isActive,
                    'version' => 1,
                ]);
            }

            AuditContext::master(
                'Satış kanalı hesabı kaydedildi.',
                [
                    'channel_account_id' => $saved->id,
                    'platform' => $saved->platform->value,
                    'name' => $saved->name,
                    'external_store_id' => $saved->external_store_id,
                    'is_active' => $saved->is_active,
                    'credentials_changed' => $credentials !== null,
                ],
                $saved,
                'channel_account_saved',
            );

            return $saved->refresh();
        }, attempts: 3);
    }

    /** @param array<string, mixed> $settings */
    private function assertSettingsDoNotContainSecrets(array $settings, string $path = 'settings'): void
    {
        foreach ($settings as $key => $value) {
            $name = strtolower((string) $key);

            if (preg_match('/(^|[_-])(password|secret|token|credential|api[_-]?key|access[_-]?key)([_-]|$)/', $name)) {
                throw new DomainException(
                    $path.'.'.$key.' hassas alanıdır; encrypted credential JSON içinde tutulmalıdır.',
                );
            }

            if (is_array($value)) {
                $this->assertSettingsDoNotContainSecrets($value, $path.'.'.$key);
            }
        }
    }
}
