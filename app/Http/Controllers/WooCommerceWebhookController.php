<?php

namespace App\Http\Controllers;

use App\Enums\SalesChannelPlatform;
use App\Jobs\PollWooCommerceWebhookJob;
use App\Models\Period;
use App\Models\SalesChannelAccount;
use App\Support\Channels\WooCommerce\WooCommerceClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class WooCommerceWebhookController extends Controller
{
    private const TOPICS = [
        'order.created',
        'order.updated',
        'action.woocommerce_order_refunded',
    ];

    public function __invoke(
        Request $request,
        int $account,
        WooCommerceClient $client,
    ): Response {
        $channel = SalesChannelAccount::query()
            ->whereKey($account)
            ->where('platform', SalesChannelPlatform::WooCommerce->value)
            ->where('is_active', true)
            ->firstOrFail();
        $secret = trim((string) ($channel->credentials()['webhook_secret'] ?? ''));
        $signature = trim((string) $request->header('X-WC-Webhook-Signature', ''));
        $topic = trim((string) $request->header('X-WC-Webhook-Topic', ''));
        $source = trim((string) $request->header('X-WC-Webhook-Source', ''));

        abort_unless(
            $secret !== ''
                && $signature !== ''
                && in_array($topic, self::TOPICS, true),
            401,
        );

        $expected = base64_encode(hash_hmac(
            'sha256',
            $request->getContent(),
            $secret,
            true,
        ));

        abort_unless(hash_equals($expected, $signature), 401);

        $expectedHost = strtolower((string) parse_url(
            $client->storeUrl($channel),
            PHP_URL_HOST,
        ));
        $sourceHost = strtolower((string) parse_url($source, PHP_URL_HOST));

        abort_unless(
            $sourceHost !== '' && hash_equals($expectedHost, $sourceHost),
            401,
        );

        $period = Period::query()
            ->where('company_id', $channel->company_id)
            ->where('status', 'active')
            ->orderByDesc('year')
            ->firstOrFail();

        PollWooCommerceWebhookJob::dispatch(
            (int) $channel->company_id,
            (int) $period->id,
            (int) $channel->id,
            $topic,
        );

        return response()->noContent();
    }
}
