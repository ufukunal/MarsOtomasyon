<?php

namespace App\Modules\Dispatches\Shipping;

interface ShippingProviderGateway
{
    public function provider(): string;

    /** @return list<string> */
    public function capabilities(): array;

    /**
     * @param  array<string, mixed>  $request
     * @return array{external_id:string,tracking_number:?string,label_reference:?string,status:string}
     */
    public function createShipment(string $idempotencyKey, array $request): array;

    /** @return array{external_id:string,tracking_number:?string,label_reference:?string,status:string}|null */
    public function findShipment(string $idempotencyKey): ?array;

    public function cancelShipment(string $externalId): void;

    public function label(string $externalId): ?string;

    /** @return array{status:string,occurred_at:?string,payload:array<string,mixed>} */
    public function tracking(string $externalId): array;
}
