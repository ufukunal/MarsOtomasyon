<?php

use App\Jobs\PollHepsiburadaWebhookJob;
use App\Jobs\PollWooCommerceWebhookJob;
use App\Models\SalesChannelAccount;
use Illuminate\Support\Facades\Queue;
use Tests\Support\IsolatedPostgres;

function marsWebhookChannel(int $companyId, string $platform, array $credentials, array $settings = []): SalesChannelAccount
{
    return SalesChannelAccount::query()->create([
        'company_id' => $companyId,
        'platform' => $platform,
        'name' => 'V4 isolated webhook account',
        'credentials_encrypted' => $credentials,
        'settings' => $settings,
        'is_active' => true,
    ]);
}

it('rejects invalid WooCommerce webhook signatures before queueing jobs', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        Queue::fake();
        $account = marsWebhookChannel($companyId, 'woocommerce',
            ['webhook_secret' => 'v4-only-secret'],
            ['store_url' => 'https://shop.example.com'],
        );
        $payload = '{"id":101,"status":"processing"}';

        $response = $this->withHeaders([
            'X-WC-Webhook-Signature' => base64_encode(hash_hmac('sha256', 'tampered', 'v4-only-secret', true)),
            'X-WC-Webhook-Topic' => 'order.updated',
            'X-WC-Webhook-Source' => 'https://shop.example.com',
        ])->call('POST', route('webhooks.woocommerce', ['account' => $account->id]), [], [], [],
            ['CONTENT_TYPE' => 'application/json'], $payload);

        $response->assertStatus(401);
        Queue::assertNotPushed(PollWooCommerceWebhookJob::class);
    });
});

it('queues a valid WooCommerce webhook only when signature, topic and source host agree', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        Queue::fake();
        $account = marsWebhookChannel($companyId, 'woocommerce',
            ['webhook_secret' => 'v4-only-secret'],
            ['store_url' => 'https://shop.example.com'],
        );
        $payload = '{"id":202,"status":"processing"}';

        $response = $this->withHeaders([
            'X-WC-Webhook-Signature' => base64_encode(hash_hmac('sha256', $payload, 'v4-only-secret', true)),
            'X-WC-Webhook-Topic' => 'order.updated',
            'X-WC-Webhook-Source' => 'https://shop.example.com',
        ])->call('POST', route('webhooks.woocommerce', ['account' => $account->id]), [], [], [],
            ['CONTENT_TYPE' => 'application/json'], $payload);

        $response->assertNoContent();
        Queue::assertPushed(PollWooCommerceWebhookJob::class, 1);
    });
});

it('rejects WooCommerce webhooks from the wrong source host even with the correct HMAC', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        Queue::fake();
        $account = marsWebhookChannel($companyId, 'woocommerce',
            ['webhook_secret' => 'v4-only-secret'],
            ['store_url' => 'https://shop.example.com'],
        );
        $payload = '{"id":203}';

        $response = $this->withHeaders([
            'X-WC-Webhook-Signature' => base64_encode(hash_hmac('sha256', $payload, 'v4-only-secret', true)),
            'X-WC-Webhook-Topic' => 'order.created',
            'X-WC-Webhook-Source' => 'https://attacker.example.com',
        ])->call('POST', route('webhooks.woocommerce', ['account' => $account->id]), [], [], [],
            ['CONTENT_TYPE' => 'application/json'], $payload);

        $response->assertStatus(401);
        Queue::assertNotPushed(PollWooCommerceWebhookJob::class);
    });
});

it('rejects Hepsiburada webhook requests with missing basic-auth credentials', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        Queue::fake();
        $account = marsWebhookChannel($companyId, 'hepsiburada', [
            'webhook_username' => 'hook-v4',
            'webhook_password' => 'password-v4',
        ]);

        $this->call('PUT', route('webhooks.hepsiburada', [
            'account' => $account->id, 'event' => 'createOrder',
        ]))->assertStatus(401);
        Queue::assertNotPushed(PollHepsiburadaWebhookJob::class);
    });
});

it('queues one Hepsiburada poll with valid HTTP basic credentials', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        Queue::fake();
        $account = marsWebhookChannel($companyId, 'hepsiburada', [
            'webhook_username' => 'hook-v4',
            'webhook_password' => 'password-v4',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Basic '.base64_encode('hook-v4:password-v4'),
        ])->call('PUT', route('webhooks.hepsiburada', [
            'account' => $account->id, 'event' => 'createOrder',
        ]));

        $response->assertNoContent();
        Queue::assertPushed(PollHepsiburadaWebhookJob::class, 1);
    });
});

it('does not accept Trendyol requests without the configured API key', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        $account = marsWebhookChannel($companyId, 'trendyol', [
            'webhook_api_key' => 'trendyol-v4-only',
        ]);

        $this->postJson(route('webhooks.trendyol', ['account' => $account->id]), [
            'status' => 'CREATED', 'orderNumber' => 'V4-100',
        ])->assertStatus(401);
    });
});
