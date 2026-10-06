<?php

namespace App\Support\Channels\Trendyol;

use App\Contracts\Channels\ChannelAdapter;
use App\DataObjects\Channels\ChannelInboundEvent;
use App\DataObjects\Channels\ChannelOperationResult;
use App\Models\Period\ChannelProductListing;
use App\Models\SalesChannelAccount;
use Carbon\CarbonImmutable;

final class TrendyolAdapter implements ChannelAdapter
{
    public function __construct(
        private readonly TrendyolClient $client,
        private readonly TrendyolPayloadBuilder $payloads,
    ) {}

    public function testConnection(SalesChannelAccount $account): ChannelOperationResult
    {
        $sellerId = $this->client->sellerId($account);
        $this->client->get(
            $account,
            "/integration/product/sellers/{$sellerId}/products/approved",
            ['page' => 0, 'size' => 1],
        );

        return new ChannelOperationResult(true, (string) $sellerId, 'Trendyol bağlantısı başarılı.');
    }

    public function publishListing(
        SalesChannelAccount $account,
        ChannelProductListing $listing,
    ): ChannelOperationResult {
        $sellerId = $this->client->sellerId($account);
        $response = $this->client->post(
            $account,
            "/integration/product/sellers/{$sellerId}/v2/products",
            ['items' => [$this->payloads->createItem($listing)]],
        );

        return $this->batchResult($response, 'Trendyol Product V2 yayını kuyruğa alındı.');
    }

    public function updateContent(
        SalesChannelAccount $account,
        ChannelProductListing $listing,
    ): ChannelOperationResult {
        $sellerId = $this->client->sellerId($account);
        $response = $this->client->post(
            $account,
            "/integration/product/sellers/{$sellerId}/products/content-bulk-update",
            ['items' => [$this->payloads->contentItem($listing)]],
        );

        return $this->batchResult($response, 'Trendyol içerik güncellemesi kuyruğa alındı.');
    }

    public function updatePrice(
        SalesChannelAccount $account,
        ChannelProductListing $listing,
        string $priceTry,
    ): ChannelOperationResult {
        return $this->inventoryUpdate($account, $listing, null, $priceTry);
    }

    public function updateStock(
        SalesChannelAccount $account,
        ChannelProductListing $listing,
        string $quantity,
        ?int $leadTimeDays = null,
    ): ChannelOperationResult {
        $inventory = $this->inventoryUpdate($account, $listing, $quantity, null);

        if (! $inventory->success || $leadTimeDays === null) {
            return $inventory;
        }

        $sellerId = $this->client->sellerId($account);
        $delivery = $this->client->post(
            $account,
            "/integration/product/sellers/{$sellerId}/products/delivery-info-bulk-update",
            ['items' => [$this->payloads->deliveryItem($listing, $leadTimeDays)]],
        );
        $deliveryBatch = (string) ($delivery['batchRequestId'] ?? '');

        return new ChannelOperationResult(
            success: $deliveryBatch !== '',
            externalId: $inventory->externalId,
            message: $deliveryBatch !== ''
                ? 'Trendyol stok ve Product V2 teslimat bilgisi kuyruğa alındı.'
                : 'Trendyol delivery batchRequestId dönmedi.',
            safeMetadata: [
                'inventory_batch_request_id' => $inventory->externalId,
                'delivery_batch_request_id' => $deliveryBatch !== '' ? $deliveryBatch : null,
            ],
        );
    }

    public function fetchOrders(SalesChannelAccount $account, ?CarbonImmutable $since = null): array
    {
        return $this->fetchPackages(
            $account,
            ['Created', 'Picking', 'Invoiced', 'Shipped', 'Delivered', 'UnDelivered', 'AtCollectionPoint', 'Verified'],
            'order',
            $since,
        );
    }

    public function fetchCancellations(SalesChannelAccount $account, ?CarbonImmutable $since = null): array
    {
        return $this->fetchPackages($account, ['Cancelled', 'UnSupplied'], 'cancel', $since);
    }

    public function fetchReturns(SalesChannelAccount $account, ?CarbonImmutable $since = null): array
    {
        $sellerId = $this->client->sellerId($account);
        $query = ['page' => 0, 'size' => 200, 'claimItemStatus' => 'Created'];

        if ($since) {
            $query['startDate'] = $since->getTimestampMs();
            $query['endDate'] = now()->getTimestampMs();
        }

        $events = [];

        do {
            $response = $this->client->get(
                $account,
                "/integration/order/sellers/{$sellerId}/claims",
                $query,
            );

            foreach (($response['content'] ?? []) as $claim) {
                if (! is_array($claim)) {
                    continue;
                }

                $externalId = (string) ($claim['id'] ?? $claim['claimId'] ?? '');

                if ($externalId === '') {
                    continue;
                }

                $events[] = new ChannelInboundEvent(
                    eventType: 'return',
                    externalId: $externalId,
                    occurredAt: $this->millisDate($claim['lastModifiedDate'] ?? $claim['claimDate'] ?? null),
                    data: $claim,
                );
            }

            $page = (int) ($response['page'] ?? $query['page']);
            $totalPages = (int) ($response['totalPages'] ?? 0);
            $query['page'] = $page + 1;
        } while ($query['page'] < $totalPages);

        return $events;
    }

    public function pushShipmentStatus(
        SalesChannelAccount $account,
        array $shipment,
    ): ChannelOperationResult {
        $sellerId = $this->client->sellerId($account);
        $packageId = (int) ($shipment['package_id']);
        $status = (string) ($shipment['status']);

        if ($packageId <= 0 || ! in_array($status, ['Picking', 'Invoiced'], true)) {
            return new ChannelOperationResult(false, message: 'Trendyol package_id/status geçersiz.');
        }

        $payload = ['status' => $status];

        if (isset($shipment['lines'])) {
            $payload['lines'] = $shipment['lines'];
        }

        if ($status === 'Invoiced' && ! empty($shipment['invoice_number'])) {
            $payload['params'] = ['invoiceNumber' => (string) $shipment['invoice_number']];
        }

        $this->client->put(
            $account,
            "/integration/order/sellers/{$sellerId}/shipment-packages/{$packageId}",
            $payload,
        );

        return new ChannelOperationResult(true, (string) $packageId, 'Trendyol paket durumu güncellendi.');
    }

    private function inventoryUpdate(
        SalesChannelAccount $account,
        ChannelProductListing $listing,
        ?string $quantity,
        ?string $price,
    ): ChannelOperationResult {
        $sellerId = $this->client->sellerId($account);
        $response = $this->client->post(
            $account,
            "/integration/inventory/sellers/{$sellerId}/products/price-and-inventory",
            ['items' => [$this->payloads->inventoryItem($listing, $quantity, $price)]],
        );

        return $this->batchResult($response, 'Trendyol stok/fiyat güncellemesi kuyruğa alındı.');
    }

    /**
     * @param  list<string>  $statuses
     * @return list<ChannelInboundEvent>
     */
    private function fetchPackages(
        SalesChannelAccount $account,
        array $statuses,
        string $eventType,
        ?CarbonImmutable $since,
    ): array {
        $sellerId = $this->client->sellerId($account);
        $query = [
            'packageItemStatuses' => implode(',', $statuses),
            'size' => 200,
        ];

        if ($since) {
            $query['lastModifiedStartDate'] = $since->getTimestampMs();
            $query['lastModifiedEndDate'] = now()->getTimestampMs();
        }

        $events = [];
        $cursor = null;

        do {
            if ($cursor !== null) {
                $query['nextCursor'] = $cursor;
            }

            $response = $this->client->get(
                $account,
                "/integration/order/sellers/{$sellerId}/orders/stream",
                $query,
            );

            foreach (($response['content'] ?? []) as $package) {
                if (! is_array($package)) {
                    continue;
                }

                $externalId = $eventType === 'order'
                    ? (string) ($package['orderNumber'] ?? '')
                    : (string) ($package['shipmentPackageId'] ?? '');

                if ($externalId === '') {
                    continue;
                }

                $events[] = new ChannelInboundEvent(
                    eventType: $eventType,
                    externalId: $externalId,
                    occurredAt: $this->millisDate($package['lastModifiedDate'] ?? $package['orderDate'] ?? null),
                    data: $package,
                );
            }

            $hasMore = (bool) ($response['hasMore'] ?? false);
            $cursor = $hasMore ? (string) ($response['nextCursor'] ?? '') : null;

            if ($hasMore && $cursor !== '') {
                usleep(5_000_000);
            }
        } while ($cursor !== null && $cursor !== '');

        return $events;
    }

    /** @param  array<array-key,mixed>  $response */
    private function batchResult(array $response, string $message): ChannelOperationResult
    {
        $batch = (string) ($response['batchRequestId'] ?? '');

        return new ChannelOperationResult(
            success: $batch !== '',
            externalId: $batch !== '' ? $batch : null,
            message: $batch !== '' ? $message : 'Trendyol batchRequestId dönmedi.',
        );
    }

    private function millisDate(mixed $value): CarbonImmutable
    {
        if (is_numeric($value) && (int) $value > 0) {
            return CarbonImmutable::createFromTimestampMs((int) $value, config('app.timezone'));
        }

        return CarbonImmutable::now();
    }
}
