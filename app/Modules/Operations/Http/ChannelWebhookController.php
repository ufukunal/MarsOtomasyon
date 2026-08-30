<?php

namespace App\Modules\Operations\Http;

use App\Modules\Operations\ChannelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class ChannelWebhookController
{
    public function __construct(private ChannelService $channels) {}

    public function __invoke(Request $request, string $connection): JsonResponse
    {
        $row = DB::table('integration_connections')
            ->where('public_id', strtoupper(trim($connection)))
            ->where('status', 'active')
            ->first(['id', 'provider']);
        abort_if($row === null, 404);
        $provider = (string) $row->provider;
        $signature = match ($provider) {
            'woocommerce' => $this->headerString($request, 'X-WC-Webhook-Signature'),
            'trendyol' => $this->headerString($request, 'x-api-key') ?: $this->headerString($request, 'Authorization'),
            default => $this->headerString($request, 'X-Mars-Signature'),
        };
        $eventType = $provider === 'woocommerce'
            ? ($this->headerString($request, 'X-WC-Webhook-Topic') ?: $this->inputString($request, 'event_type', 'unknown'))
            : ($provider === 'trendyol' ? 'order.updated' : $this->inputString($request, 'event_type', 'unknown'));
        $externalId = $provider === 'woocommerce'
            ? ($this->headerString($request, 'X-WC-Webhook-Delivery-ID') ?: $this->headerString($request, 'X-WC-Webhook-ID'))
            : ($provider === 'trendyol' ? '' : $this->inputString($request, 'event_id'));

        $id = $this->channels->ingestWebhook((int) $row->id, $externalId, $eventType, $request->getContent(), $signature);

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
