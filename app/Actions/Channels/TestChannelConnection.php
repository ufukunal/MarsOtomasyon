<?php

namespace App\Actions\Channels;

use App\DataObjects\Channels\ChannelOperationResult;
use App\Models\SalesChannelAccount;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Channels\ChannelAdapterResolver;
use App\Support\Channels\ChannelSensitiveDataRedactor;
use App\Support\Period\PeriodContext;
use DomainException;

final class TestChannelConnection
{
    public function __construct(
        private readonly ChannelAdapterResolver $resolver,
        private readonly ChannelSensitiveDataRedactor $redactor,
    ) {}

    public function handle(SalesChannelAccount $account): ChannelOperationResult
    {
        MutationAuthorizer::authorize('channel_accounts.update');

        if ((int) $account->company_id !== (int) PeriodContext::companyId()) {
            throw new DomainException('Kanal hesabı aktif şirkete ait değil.');
        }

        $adapter = $this->resolver->resolve($account);

        try {
            $result = $adapter->testConnection($account);
        } catch (\Throwable $exception) {
            AuditContext::master(
                'Kanal bağlantı testi başarısız.',
                [
                    'channel_account_id' => $account->id,
                    'platform' => $account->platform->value,
                    'error' => $this->redactor->redact($exception->getMessage(), $account),
                ],
                $account,
                'channel_connection_test_failed',
            );

            throw $exception;
        }

        AuditContext::master(
            'Kanal bağlantı testi tamamlandı.',
            [
                'channel_account_id' => $account->id,
                'platform' => $account->platform->value,
                'success' => $result->success,
                'external_id' => $result->externalId,
                'safe_metadata' => $result->safeMetadata,
            ],
            $account,
            'channel_connection_tested',
        );

        return $result;
    }
}
