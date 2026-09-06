<?php

namespace Tests\Fixtures\Dispatches;

use App\Modules\Dispatches\Shipping\AmbiguousShippingOutcome;
use App\Modules\Dispatches\Shipping\ShippingProviderGateway;

final class M28FakeShippingProvider implements ShippingProviderGateway
{
    public int $createCalls = 0;

    public int $findCalls = 0;

    public int $cancelCalls = 0;

    public bool $ambiguousNextCreate = false;

    /** @var array{external_id:string,tracking_number:?string,label_reference:?string,status:string}|null */
    public ?array $recover = null;

    public function provider(): string
    {
        return 'fixture';
    }

    public function capabilities(): array
    {
        return ['shipment_create', 'shipment_cancel', 'label_read', 'tracking_read'];
    }

    public function createShipment(string $idempotencyKey, array $request): array
    {
        $this->createCalls++;
        if ($this->ambiguousNextCreate) {
            $this->ambiguousNextCreate = false;
            throw new AmbiguousShippingOutcome('Provider timed out after accepting shipment.');
        }

        return ['external_id' => 'EXT-1', 'tracking_number' => 'TRK-1', 'label_reference' => 'label://EXT-1', 'status' => 'created'];
    }

    public function findShipment(string $idempotencyKey): ?array
    {
        $this->findCalls++;

        return $this->recover;
    }

    public function cancelShipment(string $externalId): void
    {
        $this->cancelCalls++;
    }

    public function label(string $externalId): ?string
    {
        return 'label://'.$externalId;
    }

    public function tracking(string $externalId): array
    {
        return [
            'status' => 'in_transit',
            'occurred_at' => '2026-09-02T12:00:00+03:00',
            'payload' => ['external_id' => $externalId, 'event' => 'departed_hub'],
        ];
    }
}
