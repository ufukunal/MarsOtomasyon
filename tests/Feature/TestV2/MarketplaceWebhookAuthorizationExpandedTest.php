<?php

use App\Jobs\PollHepsiburadaWebhookJob;
use App\Jobs\PollWooCommerceWebhookJob;
use App\Models\SalesChannelAccount;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2WEBHOOK');
    $this->v2WebhookCompanyId = $company->id;
    Bus::fake();
});

function v2WebhookAccount(int $companyId, string $platform, array $credentials, array $settings = []): SalesChannelAccount
{
    return SalesChannelAccount::query()->create([
        'company_id' => $companyId,
        'name' => 'V2 '.$platform.' integration',
        'platform' => $platform,
        'credentials_encrypted' => $credentials,
        'settings' => $settings,
        'is_active' => true,
    ]);
}

it('v2 Trendyol rejects absent and incorrect x-api-key before any order import', function () {
    $account = v2WebhookAccount($this->v2WebhookCompanyId, 'trendyol', ['webhook_api_key' => 'v2-trendyol-test-key']);
    $path = '/hooks/channel/'.$account->id;
    $this->postJson($path, ['status' => 'CREATED', 'orderNumber' => 'V2-ORD-1'])->assertUnauthorized();
    $this->withHeaders(['x-api-key' => 'v2-wrong-key'])
        ->postJson($path, ['status' => 'CREATED', 'orderNumber' => 'V2-ORD-1'])->assertUnauthorized();
});

it('v2 Trendyol drops unrecognized state without creating a document', function () {
    $account = v2WebhookAccount($this->v2WebhookCompanyId, 'trendyol', ['webhook_api_key' => 'v2-trendyol-key']);
    $this->withHeaders(['x-api-key' => 'v2-trendyol-key'])
        ->postJson('/hooks/channel/'.$account->id, ['status' => 'UNKNOWN', 'orderNumber' => 'V2-X'])
        ->assertOk()->assertJson(['accepted' => true, 'processed' => false]);
});

it('v2 Trendyol refuses missing external identity for a recognized inbound event', function () {
    $account = v2WebhookAccount($this->v2WebhookCompanyId, 'trendyol', ['webhook_api_key' => 'v2-trendyol-key']);
    $this->withHeaders(['x-api-key' => 'v2-trendyol-key'])
        ->postJson('/hooks/channel/'.$account->id, ['status' => 'CREATED'])
        ->assertUnprocessable();
});

it('v2 Hepsiburada rejects unsupported event and wrong HTTP Basic credentials', function () {
    $account = v2WebhookAccount($this->v2WebhookCompanyId, 'hepsiburada', [
        'webhook_username' => 'v2merchant', 'webhook_password' => 'v2integrationpass',
    ]);
    $this->putJson('/hooks/channel/hepsiburada/'.$account->id.'/notSupported', [])->assertNotFound();
    $this->putJson('/hooks/channel/hepsiburada/'.$account->id.'/createOrder', [])->assertUnauthorized();
    $this->withHeaders(['Authorization' => 'Basic '.base64_encode('v2merchant:incorrect')])
        ->putJson('/hooks/channel/hepsiburada/'.$account->id.'/createOrder', [])->assertUnauthorized();
    Bus::assertNotDispatched(PollHepsiburadaWebhookJob::class);
});

it('v2 Hepsiburada queues exactly one polling job after valid Basic authentication', function () {
    $account = v2WebhookAccount($this->v2WebhookCompanyId, 'hepsiburada', [
        'webhook_username' => 'v2merchant', 'webhook_password' => 'v2integrationpass',
    ]);
    $this->withHeaders(['Authorization' => 'Basic '.base64_encode('v2merchant:v2integrationpass')])
        ->putJson('/hooks/channel/hepsiburada/'.$account->id.'/createOrder', [])->assertNoContent();
    Bus::assertDispatched(PollHepsiburadaWebhookJob::class, 1);
});

it('v2 WooCommerce rejects missing signature and unsupported topic before queueing', function () {
    $account = v2WebhookAccount($this->v2WebhookCompanyId, 'woocommerce', ['webhook_secret' => 'v2-webhook-secret'], [
        'store_url' => 'https://shop.example.test',
    ]);
    $path = '/hooks/channel/woocommerce/'.$account->id;
    $this->post($path)->assertUnauthorized();
    $this->withHeaders([
        'X-WC-Webhook-Signature' => 'WRONG',
        'X-WC-Webhook-Topic' => 'unrecognized.topic',
        'X-WC-Webhook-Source' => 'https://shop.example.test',
    ])->postJson($path, ['id' => 42])->assertUnauthorized();
    Bus::assertNotDispatched(PollWooCommerceWebhookJob::class);
});

it('v2 WooCommerce rejects replay of signature against modified body', function () {
    $account = v2WebhookAccount($this->v2WebhookCompanyId, 'woocommerce', ['webhook_secret' => 'v2-webhook-secret'], [
        'store_url' => 'https://shop.example.test',
    ]);
    $signedBody = '{"id":42}';
    $signature = base64_encode(hash_hmac('sha256', $signedBody, 'v2-webhook-secret', true));
    $this->withHeaders([
        'X-WC-Webhook-Signature' => $signature,
        'X-WC-Webhook-Topic' => 'order.created',
        'X-WC-Webhook-Source' => 'https://shop.example.test',
    ])->call('POST', '/hooks/channel/woocommerce/'.$account->id, [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"id":43}')
        ->assertUnauthorized();
    Bus::assertNotDispatched(PollWooCommerceWebhookJob::class);
});

it('v2 WooCommerce rejects matching HMAC when source hostname belongs to another store', function () {
    $account = v2WebhookAccount($this->v2WebhookCompanyId, 'woocommerce', ['webhook_secret' => 'v2-webhook-secret'], [
        'store_url' => 'https://shop.example.test',
    ]);
    $body = '{"id":42}';
    $this->withHeaders([
        'X-WC-Webhook-Signature' => base64_encode(hash_hmac('sha256', $body, 'v2-webhook-secret', true)),
        'X-WC-Webhook-Topic' => 'order.created',
        'X-WC-Webhook-Source' => 'https://other.example.test',
    ])->call('POST', '/hooks/channel/woocommerce/'.$account->id, [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)
        ->assertUnauthorized();
    Bus::assertNotDispatched(PollWooCommerceWebhookJob::class);
});

it('v2 WooCommerce valid HMAC and source queues exactly one job rather than a direct order mutation', function () {
    $account = v2WebhookAccount($this->v2WebhookCompanyId, 'woocommerce', ['webhook_secret' => 'v2-webhook-secret'], [
        'store_url' => 'https://shop.example.test',
    ]);
    $body = '{"id":42}';
    $this->withHeaders([
        'X-WC-Webhook-Signature' => base64_encode(hash_hmac('sha256', $body, 'v2-webhook-secret', true)),
        'X-WC-Webhook-Topic' => 'order.created',
        'X-WC-Webhook-Source' => 'https://shop.example.test',
    ])->call('POST', '/hooks/channel/woocommerce/'.$account->id, [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)
        ->assertNoContent();
    Bus::assertDispatched(PollWooCommerceWebhookJob::class, 1);
});

it('v2 marketplace webhook does not accept an inactive channel account', function () {
    $account = v2WebhookAccount($this->v2WebhookCompanyId, 'trendyol', ['webhook_api_key' => 'v2-key']);
    $account->update(['is_active' => false]);
    $this->withHeaders(['x-api-key' => 'v2-key'])
        ->postJson('/hooks/channel/'.$account->id, ['status' => 'CREATED', 'orderNumber' => 'V2-INACTIVE'])
        ->assertNotFound();
});

it('v2 WooCommerce Hookshot ping is accepted without queueing an order', function () {
    $account = v2WebhookAccount($this->v2WebhookCompanyId, 'woocommerce', [
        'webhook_secret' => 'v2-webhook-secret',
    ], ['store_url' => 'https://shop.example.test']);
    $this->withHeaders(['User-Agent' => 'WooCommerce/10.0 Hookshot'])
        ->call('POST', '/hooks/channel/woocommerce/'.$account->id, [], [], [],
            ['CONTENT_TYPE' => 'text/plain'], 'webhook_id=456')
        ->assertOk();
    Bus::assertNotDispatched(PollWooCommerceWebhookJob::class);
});
