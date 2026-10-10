<?php

use App\Actions\Channels\SetupWooCommerceWebhooks;
use App\Models\SalesChannelAccount;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('registers exactly three WooCommerce webhook topics using a mocked local-only HTTP adapter', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        AuthorizedPeriod::login();
        $originalUrl = (string) config('app.url');
        URL::forceRootUrl('https://hooks.example.test');
        URL::forceScheme('https');

        try {
            $account = SalesChannelAccount::query()->create([
                'company_id' => $companyId,
                'platform' => 'woocommerce',
                'name' => 'V4 disposable webhook registration',
                'credentials_encrypted' => [
                    'consumer_key' => 'ck_v4_only',
                    'consumer_secret' => 'cs_v4_only',
                    'webhook_secret' => str_repeat('W', 48),
                ],
                'settings' => ['store_url' => 'https://shop.example.test'],
                'is_active' => true,
            ]);

            Http::preventStrayRequests();
            Http::fake(function (Request $request) {
                if ($request->method() === 'GET') {
                    return Http::response([]);
                }

                return Http::response([
                    'id' => match ($request->data()['topic'] ?? '') {
                        'order.created' => 11,
                        'order.updated' => 12,
                        'action.woocommerce_order_refunded' => 13,
                        default => 0,
                    },
                ]);
            });

            $ids = app(SetupWooCommerceWebhooks::class)->handle($account);

            expect($ids)->toBe([
                'order.created' => 11,
                'order.updated' => 12,
                'action.woocommerce_order_refunded' => 13,
            ]);
            expect($account->fresh()->settings['woocommerce_webhook_ids'])->toBe($ids);

            Http::assertSentCount(4);
            Http::assertSent(fn (Request $request) => $request->method() === 'POST'
                && $request->data()['topic'] === 'order.created'
                && $request->data()['delivery_url'] === route('webhooks.woocommerce', ['account' => $account->id])
                && $request->data()['secret'] === str_repeat('W', 48));
        } finally {
            URL::forceRootUrl($originalUrl);
            URL::forceScheme(parse_url($originalUrl, PHP_URL_SCHEME) ?: 'http');
            AuthorizedPeriod::logout();
        }
    });
});
