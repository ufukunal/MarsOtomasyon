<?php

namespace App\Actions\Channels;

use App\Enums\SalesChannelPlatform;
use App\Models\SalesChannelAccount;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Channels\Trendyol\TrendyolClient;
use App\Support\Period\PeriodContext;
use DomainException;
use Illuminate\Support\Facades\URL;

final class SetupTrendyolWebhook
{
    public function __construct(private readonly TrendyolClient $client) {}

    public function handle(SalesChannelAccount $account): string
    {
        MutationAuthorizer::authorize('channel_accounts.update');

        if ($account->platform !== SalesChannelPlatform::Trendyol
            || (int) $account->company_id !== (int) PeriodContext::companyId()) {
            throw new DomainException('Webhook kurulumu yalnız aktif şirkete ait Trendyol hesabında yapılabilir.');
        }

        if (trim((string) data_get($account->settings, 'trendyol_webhook_id', '')) !== '') {
            throw new DomainException('Trendyol webhook zaten kayıtlı; ikinci subscription oluşturulamaz.');
        }

        $apiKey = trim((string) ($account->credentials()['webhook_api_key'] ?? ''));

        if ($apiKey === '' || mb_strlen($apiKey) < 24) {
            throw new DomainException('Trendyol webhook_api_key en az 24 karakter olmalıdır.');
        }

        $sellerId = $this->client->sellerId($account);
        $url = URL::route('webhooks.trendyol', ['account' => $account->id]);

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $lowerUrl = strtolower($url);

        if (! str_starts_with($url, 'https://')
            || $host === ''
            || $host === 'localhost'
            || str_contains($host, 'localhost')
            || str_contains($lowerUrl, 'trendyol')
            || str_contains($lowerUrl, 'dolap')) {
            throw new DomainException('Trendyol webhook URL public HTTPS olmalı ve yasaklı endpoint ifadelerini içermemelidir.');
        }

        $response = $this->client->post(
            $account,
            "/integration/webhook/sellers/{$sellerId}/webhooks",
            [
                'url' => $url,
                'authenticationType' => 'API_KEY',
                'apiKey' => $apiKey,
                'subscribedStatuses' => ['CREATED', 'CANCELLED', 'UNSUPPLIED'],
            ],
        );
        $webhookId = trim((string) ($response['id'] ?? ''));

        if ($webhookId === '') {
            throw new DomainException('Trendyol webhook create yanıtında id bulunamadı.');
        }

        $settings = $account->settings ?? [];
        $settings['trendyol_webhook_id'] = $webhookId;
        $account->settings = $settings;
        $account->version = (int) $account->version + 1;
        $account->save();

        AuditContext::master(
            'Trendyol webhook kurulumu tamamlandı.',
            [
                'channel_account_id' => $account->id,
                'webhook_id' => $webhookId,
                'subscribed_statuses' => ['CREATED', 'CANCELLED', 'UNSUPPLIED'],
            ],
            $account,
            'trendyol_webhook_created',
        );

        return $webhookId;
    }
}
