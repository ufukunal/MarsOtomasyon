<?php

namespace App\Http\Controllers;

use App\Actions\Channels\ProcessChannelInboundEvent;
use App\DataObjects\Channels\ChannelInboundEvent;
use App\Enums\SalesChannelPlatform;
use App\Models\Period;
use App\Models\SalesChannelAccount;
use App\Support\Period\PeriodContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TrendyolWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        int $account,
        ProcessChannelInboundEvent $processor,
    ): JsonResponse {
        $channel = SalesChannelAccount::query()
            ->whereKey($account)
            ->where('platform', SalesChannelPlatform::Trendyol->value)
            ->where('is_active', true)
            ->firstOrFail();
        $expectedKey = trim((string) ($channel->credentials()['webhook_api_key'] ?? ''));
        $providedKey = trim((string) $request->header('x-api-key', ''));

        abort_unless(
            $expectedKey !== ''
                && $providedKey !== ''
                && hash_equals($expectedKey, $providedKey),
            401,
        );

        $payload = $request->json()->all();
        $status = strtoupper(trim((string) ($payload['status'] ?? '')));
        $eventType = match ($status) {
            'CREATED' => 'order',
            'CANCELLED', 'UNSUPPLIED' => 'cancel',
            default => null,
        };

        if ($eventType === null) {
            return response()->json(['accepted' => true, 'processed' => false]);
        }

        $externalId = $eventType === 'order'
            ? trim((string) ($payload['orderNumber'] ?? ''))
            : trim((string) ($payload['shipmentPackageId'] ?? ''));

        if ($externalId === '') {
            return response()->json(['message' => 'Trendyol webhook external id eksik.'], 422);
        }

        $period = Period::query()
            ->where('company_id', $channel->company_id)
            ->where('status', 'active')
            ->orderByDesc('year')
            ->firstOrFail();

        PeriodContext::useSystem((int) $channel->company_id, (int) $period->id);

        try {
            $document = $processor->handle(
                $channel,
                new ChannelInboundEvent(
                    eventType: $eventType,
                    externalId: $externalId,
                    occurredAt: $this->occurredAt($payload),
                    data: $payload,
                ),
            );

            return response()->json([
                'accepted' => true,
                'processed' => $document !== null,
            ]);
        } finally {
            PeriodContext::clear();
        }
    }

    /** @param array<string,mixed> $payload */
    private function occurredAt(array $payload): CarbonImmutable
    {
        $value = $payload['lastModifiedDate'] ?? $payload['orderDate'] ?? null;

        if (is_numeric($value) && (int) $value > 0) {
            return CarbonImmutable::createFromTimestampMs((int) $value, config('app.timezone'));
        }

        return CarbonImmutable::now();
    }
}
