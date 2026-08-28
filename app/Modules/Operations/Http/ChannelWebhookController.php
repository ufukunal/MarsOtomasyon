<?php

namespace App\Modules\Operations\Http;

use App\Modules\Operations\ChannelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class ChannelWebhookController
{
    public function __construct(private ChannelService $channels) {}

    public function __invoke(Request $request, int $connection): JsonResponse
    {
        $row = DB::table('integration_connections')->where('id', $connection)->first(['provider']);
        abort_if($row === null, 404);
        $provider = (string) $row->provider;
        $signature = $provider === 'woocommerce'
            ? (string) $request->header('X-WC-Webhook-Signature', '')
            : (string) $request->header('X-Mars-Signature', $request->header('X-Trendyol-Signature', ''));
        $eventType = $provider === 'woocommerce'
            ? (string) $request->header('X-WC-Webhook-Topic', $request->input('event_type', 'unknown'))
            : (string) $request->header('X-Trendyol-Event', $request->input('event_type', 'unknown'));
        $externalId = $provider === 'woocommerce'
            ? (string) $request->header('X-WC-Webhook-Delivery-ID', $request->header('X-WC-Webhook-ID', ''))
            : (string) $request->header('X-Trendyol-Event-ID', $request->input('event_id', ''));

        $id = $this->channels->ingestWebhook($connection, $externalId, $eventType, $request->getContent(), $signature);

        return response()->json(['accepted' => true, 'event_id' => $id], 202);
    }
}
