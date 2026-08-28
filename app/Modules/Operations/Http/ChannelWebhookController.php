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
            ? $this->headerString($request, 'X-WC-Webhook-Signature')
            : ($this->headerString($request, 'X-Mars-Signature') ?: $this->headerString($request, 'X-Trendyol-Signature'));
        $eventType = $provider === 'woocommerce'
            ? ($this->headerString($request, 'X-WC-Webhook-Topic') ?: $this->inputString($request, 'event_type', 'unknown'))
            : ($this->headerString($request, 'X-Trendyol-Event') ?: $this->inputString($request, 'event_type', 'unknown'));
        $externalId = $provider === 'woocommerce'
            ? ($this->headerString($request, 'X-WC-Webhook-Delivery-ID') ?: $this->headerString($request, 'X-WC-Webhook-ID'))
            : ($this->headerString($request, 'X-Trendyol-Event-ID') ?: $this->inputString($request, 'event_id'));

        $id = $this->channels->ingestWebhook($connection, $externalId, $eventType, $request->getContent(), $signature);

        return response()->json(['accepted' => true, 'event_id' => $id], 202);
    }

    private function headerString(Request $request, string $name): string
    {
        $value = $request->headers->get($name);

        return is_string($value) ? $value : '';
    }

    private function inputString(Request $request, string $key, string $default = ''): string
    {
        $value = $request->input($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }
}
