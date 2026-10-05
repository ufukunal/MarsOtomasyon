<?php

namespace App\Actions\Channels;

use App\Enums\SalesChannelPlatform;
use App\Models\SalesChannelAccount;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Channels\WooCommerce\WooCommerceClient;
use App\Support\Period\PeriodContext;
use DomainException;
use Illuminate\Support\Facades\URL;

final class SetupWooCommerceWebhooks
{
    private const TOPICS = [
        'order.created',
        'order.updated',
        'action.woocommerce_order_refunded',
    ];

    public function __construct(private readonly WooCommerceClient $client) {}

    /** @return array<string,int> */
    public function handle(SalesChannelAccount $account): array
    {
        MutationAuthorizer::authorize('channel_accounts.update');

        if ($account->platform !== SalesChannelPlatform::WooCommerce
            || (int) $account->company_id !== (int) PeriodContext::companyId()) {
            throw new DomainException('Webhook kurulumu yalnız aktif şirkete ait WooCommerce hesabında yapılabilir.');
        }

        $deliveryUrl = URL::route('webhooks.woocommerce', ['account' => $account->id]);
        $host = strtolower((string) parse_url($deliveryUrl, PHP_URL_HOST));

        if (! str_starts_with(strtolower($deliveryUrl), 'https://')
            || $host === ''
            || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            throw new DomainException('WooCommerce webhook URL public HTTPS olmalıdır.');
        }

        $credentials = $account->credentials();
        $secret = trim((string) ($credentials['webhook_secret'] ?? ''));

        if (mb_strlen($secret) < 32) {
            $secret = bin2hex(random_bytes(32));
            $credentials['webhook_secret'] = $secret;
            $account->credentials_encrypted = $credentials;
            $account->version = (int) $account->version + 1;
            $account->save();
            $account->refresh();
        }

        $settings = $account->settings ?? [];
        $ids = is_array($settings['woocommerce_webhook_ids'] ?? null)
            ? $settings['woocommerce_webhook_ids']
            : [];
        $remote = $this->client->get(
            $account,
            'webhooks',
            ['per_page' => 100, 'page' => 1],
        );
        $remoteWebhooks = array_is_list($remote)
            ? array_values(array_filter($remote, 'is_array'))
            : [];

        foreach (self::TOPICS as $topic) {
            $localId = (int) ($ids[$topic] ?? 0);
            $existing = collect($remoteWebhooks)->first(function (array $webhook) use (
                $localId,
                $topic,
                $deliveryUrl,
            ): bool {
                if ($localId > 0 && (int) ($webhook['id'] ?? 0) === $localId) {
                    return true;
                }

                return (string) ($webhook['topic'] ?? '') === $topic
                    && rtrim((string) ($webhook['delivery_url'] ?? ''), '/') === rtrim($deliveryUrl, '/');
            });

            if (is_array($existing) && (int) ($existing['id'] ?? 0) > 0) {
                $id = (int) $existing['id'];
                $this->client->put(
                    $account,
                    'webhooks/'.$id,
                    [
                        'name' => 'MarsOtomasyon '.$topic.' #'.$account->id,
                        'status' => 'active',
                        'topic' => $topic,
                        'delivery_url' => $deliveryUrl,
                        'secret' => $secret,
                    ],
                );
            } else {
                $response = $this->client->post(
                    $account,
                    'webhooks',
                    [
                        'name' => 'MarsOtomasyon '.$topic.' #'.$account->id,
                        'status' => 'active',
                        'topic' => $topic,
                        'delivery_url' => $deliveryUrl,
                        'secret' => $secret,
                    ],
                );
                $id = (int) ($response['id'] ?? 0);

                if ($id <= 0) {
                    throw new DomainException('WooCommerce webhook create yanıtında id bulunamadı: '.$topic);
                }

                $remoteWebhooks[] = [
                    'id' => $id,
                    'topic' => $topic,
                    'delivery_url' => $deliveryUrl,
                    'status' => 'active',
                ];
            }

            $ids[$topic] = $id;
            $settings['woocommerce_webhook_ids'] = $ids;
            $account->settings = $settings;
            $account->version = (int) $account->version + 1;
            $account->save();
            $account->refresh();
        }

        AuditContext::master(
            'WooCommerce webhook kurulumu tamamlandı.',
            [
                'channel_account_id' => $account->id,
                'topics' => self::TOPICS,
                'webhook_ids' => $ids,
            ],
            $account,
            'woocommerce_webhooks_created',
        );

        return array_map('intval', $ids);
    }
}
