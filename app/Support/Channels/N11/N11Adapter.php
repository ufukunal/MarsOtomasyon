<?php

namespace App\Support\Channels\N11;

use App\Contracts\Channels\ChannelAdapter;
use App\DataObjects\Channels\ChannelInboundEvent;
use App\DataObjects\Channels\ChannelOperationResult;
use App\Models\Period\ChannelProductListing;
use App\Models\SalesChannelAccount;
use Carbon\CarbonImmutable;
use DomainException;

final class N11Adapter implements ChannelAdapter
{
    public function __construct(
        private readonly N11Client $client,
        private readonly N11PayloadBuilder $payloads,
    ) {}

    public function testConnection(SalesChannelAccount $account): ChannelOperationResult
    {
        $this->client->get($account, '/ms/product-query', ['page' => 0, 'size' => 1]);

        return new ChannelOperationResult(
            true,
            message: 'N11 bağlantısı başarılı.',
        );
    }

    public function publishListing(
        SalesChannelAccount $account,
        ChannelProductListing $listing,
    ): ChannelOperationResult {
        $response = $this->client->post(
            $account,
            '/ms/product/tasks/product-create',
            [
                'payload' => [
                    'integrator' => $this->client->integrator($account),
                    'skus' => [$this->payloads->createSku($listing)],
                ],
            ],
        );

        return $this->taskResult($response, 'N11 ürün yükleme taskı kuyruğa alındı.');
    }

    public function updateContent(
        SalesChannelAccount $account,
        ChannelProductListing $listing,
    ): ChannelOperationResult {
        $response = $this->client->post(
            $account,
            '/ms/product/tasks/product-update',
            [
                'payload' => [
                    'integrator' => $this->client->integrator($account),
                    'skus' => [$this->payloads->contentUpdateSku($listing)],
                ],
            ],
        );

        return $this->taskResult(
            $response,
            'N11 desteklenen ürün içerik alanları güncelleme taskı kuyruğa alındı.',
        );
    }

    public function updatePrice(
        SalesChannelAccount $account,
        ChannelProductListing $listing,
        string $priceTry,
    ): ChannelOperationResult {
        return $this->priceStockUpdate($account, $listing, null, $priceTry);
    }

    public function updateStock(
        SalesChannelAccount $account,
        ChannelProductListing $listing,
        string $quantity,
        ?int $leadTimeDays = null,
    ): ChannelOperationResult {
        $stock = $this->priceStockUpdate($account, $listing, $quantity, null);

        if (! $stock->success || $leadTimeDays === null) {
            return $stock;
        }

        $content = $this->client->post(
            $account,
            '/ms/product/tasks/product-update',
            [
                'payload' => [
                    'integrator' => $this->client->integrator($account),
                    'skus' => [[
                        'stockCode' => $this->stockCode($listing),
                        'preparingDay' => max(1, $leadTimeDays),
                    ]],
                ],
            ],
        );
        $delivery = $this->taskResult($content, 'N11 hazırlık süresi taskı kuyruğa alındı.');

        if (! $delivery->success) {
            return new ChannelOperationResult(
                false,
                externalId: $stock->externalId,
                message: $delivery->message,
                safeMetadata: [
                    'stock_task_id' => $stock->externalId,
                ],
            );
        }

        return new ChannelOperationResult(
            true,
            $stock->externalId,
            'N11 stok ve hazırlık süresi taskları kuyruğa alındı.',
            [
                'stock_task_id' => $stock->externalId,
                'delivery_task_id' => $delivery->externalId,
            ],
        );
    }

    public function fetchOrders(
        SalesChannelAccount $account,
        ?CarbonImmutable $since = null,
    ): array {
        $packages = [];

        foreach (['Created', 'Picking', 'Unpacked', 'Shipped', 'Delivered'] as $status) {
            foreach ($this->shipmentPackages($account, $status, $since) as $package) {
                $orderNumber = trim((string) ($package['orderNumber'] ?? ''));

                if ($orderNumber === '') {
                    continue;
                }

                $packages[$orderNumber][] = $package;
            }
        }

        $events = [];

        foreach ($packages as $orderNumber => $orderPackages) {
            $data = $this->normalizeOrder($orderNumber, $orderPackages);

            $events[] = new ChannelInboundEvent(
                eventType: 'order',
                externalId: $orderNumber,
                occurredAt: $this->millisDate($data['lastModifiedDate'] ?? null, $since),
                data: $data,
            );
        }

        return $events;
    }

    public function fetchCancellations(
        SalesChannelAccount $account,
        ?CarbonImmutable $since = null,
    ): array {
        $events = [];

        foreach (['Cancelled', 'UnSupplied'] as $status) {
            foreach ($this->shipmentPackages($account, $status, $since) as $package) {
                $orderNumber = trim((string) ($package['orderNumber'] ?? ''));
                $packageId = trim((string) ($package['id'] ?? ''));

                if ($orderNumber === '' || $packageId === '') {
                    continue;
                }

                $lines = [];

                foreach (($package['lines'] ?? []) as $line) {
                    if (! is_array($line)) {
                        continue;
                    }

                    $quantity = bcadd((string) ($line['quantity'] ?? '0'), '0', 3);

                    if (bccomp($quantity, '0', 3) <= 0) {
                        continue;
                    }

                    $lines[] = [
                        'quantity' => $quantity,
                        'lineId' => (string) ($line['orderLineId'] ?? ''),
                        'stockCode' => (string) ($line['stockCode'] ?? ''),
                        'barcode' => (string) ($line['barcode'] ?? ''),
                    ];
                }

                if ($lines === []) {
                    continue;
                }

                $events[] = new ChannelInboundEvent(
                    eventType: 'cancel',
                    externalId: mb_substr($packageId.':'.$status, 0, 120),
                    occurredAt: $this->millisDate($package['lastModifiedDate'] ?? null, $since),
                    data: [
                        'orderNumber' => $orderNumber,
                        'shipmentPackageId' => $packageId,
                        'lastModifiedDate' => $package['lastModifiedDate'] ?? null,
                        'lines' => $lines,
                        'channel_metadata' => ['n11_status' => $status],
                    ],
                );
            }
        }

        return $events;
    }

    public function fetchReturns(
        SalesChannelAccount $account,
        ?CarbonImmutable $since = null,
    ): array {
        $since ??= CarbonImmutable::now()->subDays(1);
        $start = $since->setTimezone(config('app.timezone'))->format('d/m/Y');
        $end = CarbonImmutable::now()->setTimezone(config('app.timezone'))->format('d/m/Y');
        $events = [];

        for ($page = 0; $page < 100; $page++) {
            $claims = $this->client->claimReturns($account, $start, $end, $page);

            foreach ($claims as $claim) {
                $claimId = trim((string) ($claim['claimReturnId'] ?? ''));
                $orderNumber = trim((string) ($claim['orderNumber'] ?? ''));

                if ($claimId === '' || $orderNumber === '') {
                    continue;
                }

                $productId = trim((string) ($claim['productId'] ?? ''));
                $listing = $productId !== ''
                    ? ChannelProductListing::query()
                        ->with('product')
                        ->where('channel_account_id', $account->id)
                        ->where(function ($query) use ($productId): void {
                            $query->where('external_product_id', $productId)
                                ->orWhere('external_listing_id', $productId);
                        })
                        ->first()
                    : null;

                [$stockCode, $barcode] = $listing
                    ? [
                        (string) ($listing->external_sku ?: $listing->product?->code),
                        (string) ($listing->product?->barcode ?? ''),
                    ]
                    : $this->resolveReturnProductIdentity($account, $productId);

                $events[] = new ChannelInboundEvent(
                    eventType: 'return',
                    externalId: mb_substr($claimId, 0, 120),
                    occurredAt: $this->soapDate($claim['requestDate'] ?? null, $since),
                    data: [
                        'orderNumber' => $orderNumber,
                        'externalPackageId' => $claim['campaignNumber'] ?? null,
                        'returnLines' => [[
                            'externalLineId' => '',
                            'stockCode' => $stockCode,
                            'barcode' => $barcode,
                            'quantity' => (string) ($claim['quantity'] ?? '0'),
                            'reason' => trim((string) (
                                $claim['returnReasonDescription']
                                ?? $claim['returnReasonType']
                                ?? ''
                            )),
                        ]],
                    ],
                );
            }

            if (count($claims) < 20) {
                break;
            }
        }

        return $events;
    }

    public function pushShipmentStatus(
        SalesChannelAccount $account,
        array $shipment,
    ): ChannelOperationResult {
        $status = strtolower(trim((string) ($shipment['status'] ?? '')));

        if ($status !== 'picking') {
            return new ChannelOperationResult(
                false,
                message: 'N11 UpdateOrder servisi resmi olarak yalnız Picking statüsünü destekliyor.',
            );
        }

        $lines = [];

        foreach (($shipment['lines'] ?? []) as $line) {
            if (! is_array($line)) {
                continue;
            }

            $lineId = (int) (
                $line['lineId']
                ?? $line['line_id']
                ?? $line['orderLineId']
                ?? $line['id']
                ?? 0
            );

            if ($lineId > 0) {
                $lines[] = ['lineId' => $lineId];
            }
        }

        if ($lines === []) {
            return new ChannelOperationResult(
                false,
                message: 'N11 Picking güncellemesi için orderLineId listesi zorunludur.',
            );
        }

        $response = $this->client->put(
            $account,
            '/rest/order/v1/update',
            ['lines' => $lines, 'status' => 'Picking'],
        );
        $results = is_array($response['content'] ?? null) ? $response['content'] : [];
        $failed = collect($results)->contains(
            fn ($row): bool => ! is_array($row)
                || strtoupper((string) ($row['status'] ?? '')) !== 'SUCCESS',
        );

        return new ChannelOperationResult(
            success: ! $failed && $results !== [],
            externalId: (string) ($shipment['package_id'] ?? ''),
            message: ! $failed && $results !== []
                ? 'N11 sipariş kalemleri Picking statüsüne alındı.'
                : 'N11 Picking güncellemesinde başarısız satır var.',
        );
    }

    private function priceStockUpdate(
        SalesChannelAccount $account,
        ChannelProductListing $listing,
        ?string $quantity,
        ?string $price,
    ): ChannelOperationResult {
        $response = $this->client->post(
            $account,
            '/ms/product/tasks/price-stock-update',
            [
                'payload' => [
                    'integrator' => $this->client->integrator($account),
                    'skus' => [$this->payloads->stockPriceSku($listing, $quantity, $price)],
                ],
            ],
        );

        return $this->taskResult($response, 'N11 stok/fiyat taskı kuyruğa alındı.');
    }

    /** @return list<array<string,mixed>> */
    private function shipmentPackages(
        SalesChannelAccount $account,
        string $status,
        ?CarbonImmutable $since,
    ): array {
        $page = 0;
        $all = [];
        $since ??= CarbonImmutable::now()->subMinutes(
            max(15, (int) config('channels.poll_lookback_minutes', 30)),
        );

        do {
            $response = $this->client->get(
                $account,
                '/rest/delivery/v1/shipmentPackages',
                [
                    'startDate' => $since->getTimestampMs(),
                    'endDate' => CarbonImmutable::now()->getTimestampMs(),
                    'status' => $status,
                    'orderByField' => 'true',
                    'orderByDirection' => 'ASC',
                    'sender' => 'SELLER',
                    'page' => $page,
                    'size' => 100,
                ],
            );
            $rows = is_array($response['content'] ?? null)
                ? array_values(array_filter($response['content'], 'is_array'))
                : [];

            foreach ($rows as $row) {
                $all[] = $row;
            }

            $totalPages = max(0, (int) ($response['totalPages'] ?? 0));
            $page++;
        } while ($rows !== [] && $page < $totalPages);

        return $all;
    }

    /**
     * @param list<array<string,mixed>> $packages
     * @return array<string,mixed>
     */
    private function normalizeOrder(string $orderNumber, array $packages): array
    {
        usort(
            $packages,
            fn (array $a, array $b): int => ((int) ($a['lastModifiedDate'] ?? 0))
                <=> ((int) ($b['lastModifiedDate'] ?? 0)),
        );
        $latest = $packages[array_key_last($packages)] ?? [];
        $address = is_array($latest['shippingAddress'] ?? null) ? $latest['shippingAddress'] : [];
        $customer = trim((string) (
            $latest['customerfullName']
            ?? $address['fullName']
            ?? ''
        ));
        [$firstName, $lastName] = $this->splitName($customer);
        $lineMap = [];

        foreach ($packages as $package) {
            foreach (($package['lines'] ?? []) as $line) {
                if (! is_array($line)) {
                    continue;
                }

                $lineId = trim((string) ($line['orderLineId'] ?? ''));

                if ($lineId === '' || isset($lineMap[$lineId])) {
                    continue;
                }

                $quantity = bcadd((string) ($line['quantity'] ?? '0'), '0', 3);

                if (bccomp($quantity, '0', 3) <= 0) {
                    continue;
                }

                $gross = bcadd((string) ($line['price'] ?? '0'), '0', 4);
                $totalDiscount = bcadd((string) (
                    $line['totalSellerDiscountPrice']
                    ?? bcadd(
                        (string) ($line['sellerDiscount'] ?? '0'),
                        (string) ($line['sellerCouponDiscount'] ?? '0'),
                        4,
                    )
                ), '0', 4);
                $discountPerUnit = bcdiv($totalDiscount, $quantity, 4);

                $lineMap[$lineId] = [
                    'quantity' => $quantity,
                    'lineGrossAmount' => $gross,
                    'lineTotalDiscount' => $discountPerUnit,
                    'lineId' => $lineId,
                    'stockCode' => (string) ($line['stockCode'] ?? ''),
                    'barcode' => (string) ($line['barcode'] ?? ''),
                    'vatRate' => (string) ($line['vatRate'] ?? '0'),
                    'sellerInvoiceAmount' => $line['sellerInvoiceAmount'] ?? null,
                    'sellerDiscount' => $line['sellerDiscount'] ?? null,
                    'sellerCouponDiscount' => $line['sellerCouponDiscount'] ?? null,
                ];
            }
        }

        $orderDate = null;

        foreach (($packages[0]['packageHistories'] ?? []) as $history) {
            if (is_array($history) && is_numeric($history['createdDate'] ?? null)) {
                $value = (int) $history['createdDate'];
                $orderDate = $orderDate === null ? $value : min($orderDate, $value);
            }
        }

        $orderDate ??= (int) ($latest['lastModifiedDate'] ?? CarbonImmutable::now()->getTimestampMs());

        return [
            'orderNumber' => $orderNumber,
            'currencyCode' => 'TRY',
            'orderDate' => $orderDate,
            'customerFirstName' => $firstName,
            'customerLastName' => $lastName,
            'customerEmail' => (string) ($latest['customerEmail'] ?? ''),
            'shipmentAddress' => [
                'fullName' => (string) ($address['fullName'] ?? $customer),
                'phone' => (string) ($address['gsm'] ?? ''),
                'fullAddress' => (string) ($address['address'] ?? ''),
                'city' => (string) ($address['city'] ?? ''),
                'district' => (string) ($address['district'] ?? ''),
                'postalCode' => (string) ($address['postalCode'] ?? ''),
            ],
            'cargoProviderName' => (string) ($latest['cargoProviderName'] ?? ''),
            'cargoTrackingNumber' => (string) ($latest['cargoTrackingNumber'] ?? ''),
            'shipmentNumber' => (string) ($latest['cargoSenderNumber'] ?? ''),
            'shipmentPackageId' => (string) ($latest['id'] ?? ''),
            'paymentMethod' => '',
            'status' => (string) ($latest['shipmentPackageStatus'] ?? ''),
            'lastModifiedDate' => $latest['lastModifiedDate'] ?? null,
            'lines' => array_values($lineMap),
        ];
    }

    /** @return array{0:string,1:string} */
    private function resolveReturnProductIdentity(
        SalesChannelAccount $account,
        string $productId,
    ): array {
        if ($productId === '' || ! ctype_digit($productId)) {
            return ['', ''];
        }

        $response = $this->client->get(
            $account,
            '/ms/product-query',
            ['id' => (int) $productId, 'page' => 0, 'size' => 1],
        );
        $rows = is_array($response['content'] ?? null) ? $response['content'] : [];
        $row = is_array($rows[0] ?? null) ? $rows[0] : [];

        return [
            trim((string) ($row['stockCode'] ?? '')),
            trim((string) ($row['barcode'] ?? '')),
        ];
    }

    private function stockCode(ChannelProductListing $listing): string
    {
        $listing->loadMissing('product');

        return trim((string) ($listing->external_sku ?: $listing->product->code));
    }

    private function taskResult(array $response, string $message): ChannelOperationResult
    {
        $id = trim((string) ($response['id'] ?? ''));
        $status = strtoupper(trim((string) ($response['status'] ?? '')));

        return new ChannelOperationResult(
            success: $id !== '' && $status !== 'REJECT',
            externalId: $id !== '' ? $id : null,
            message: $id !== '' && $status !== 'REJECT'
                ? $message
                : 'N11 task kabul edilmedi.',
            safeMetadata: [
                'task_type' => $response['type'] ?? null,
                'task_status' => $status !== '' ? $status : null,
            ],
        );
    }

    private function millisDate(mixed $value, ?CarbonImmutable $fallback = null): CarbonImmutable
    {
        if (is_numeric($value) && (int) $value > 0) {
            return CarbonImmutable::createFromTimestampMs((int) $value, config('app.timezone'));
        }

        return $fallback ?? CarbonImmutable::now();
    }

    private function soapDate(mixed $value, ?CarbonImmutable $fallback = null): CarbonImmutable
    {
        if (is_string($value) && trim($value) !== '') {
            foreach (['d/m/Y', 'd/m/Y H:i:s'] as $format) {
                try {
                    return CarbonImmutable::createFromFormat($format, trim($value), config('app.timezone'));
                } catch (\Throwable) {
                    // try next format
                }
            }
        }

        return $fallback ?? CarbonImmutable::now();
    }

    /** @return array{0:string,1:string} */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];

        if (count($parts) <= 1) {
            return [$name, ''];
        }

        $last = (string) array_pop($parts);

        return [implode(' ', $parts), $last];
    }
}
