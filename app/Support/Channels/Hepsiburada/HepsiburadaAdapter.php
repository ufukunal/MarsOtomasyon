<?php

namespace App\Support\Channels\Hepsiburada;

use App\Contracts\Channels\ChannelAdapter;
use App\DataObjects\Channels\ChannelInboundEvent;
use App\DataObjects\Channels\ChannelOperationResult;
use App\Models\Period\ChannelProductListing;
use App\Models\SalesChannelAccount;
use Carbon\CarbonImmutable;

final class HepsiburadaAdapter implements ChannelAdapter
{
    public function __construct(
        private readonly HepsiburadaClient $client,
        private readonly HepsiburadaPayloadBuilder $payloads,
    ) {}

    public function testConnection(SalesChannelAccount $account): ChannelOperationResult
    {
        $merchantId = $this->client->merchantId($account);

        $this->client->get(
            $account,
            'listing',
            '/listings/merchantid/'.rawurlencode($merchantId),
            ['offset' => 0, 'limit' => 1],
        );

        return new ChannelOperationResult(
            true,
            $merchantId,
            'Hepsiburada bağlantısı başarılı.',
        );
    }

    public function publishListing(
        SalesChannelAccount $account,
        ChannelProductListing $listing,
    ): ChannelOperationResult {
        $response = $this->client->postJson(
            $account,
            'catalog',
            '/product/api/products/import',
            [$this->payloads->catalogItem(
                $listing,
                $this->client->merchantId($account),
            )],
        );

        return $this->trackingResult(
            $response,
            'trackingId',
            'Hepsiburada katalog ürün yüklemesi kuyruğa alındı.',
        );
    }

    public function updateContent(
        SalesChannelAccount $account,
        ChannelProductListing $listing,
    ): ChannelOperationResult {
        $response = $this->client->postJson(
            $account,
            'catalog',
            '/ticket-api/api/integrator/import',
            [$this->payloads->contentUpdateItem($listing)],
        );

        return $this->trackingResult(
            $response,
            'trackingId',
            'Hepsiburada ürün içerik güncellemesi kuyruğa alındı.',
        );
    }

    public function updatePrice(
        SalesChannelAccount $account,
        ChannelProductListing $listing,
        string $priceTry,
    ): ChannelOperationResult {
        $response = $this->client->postJson(
            $account,
            'listing',
            '/listings/merchantid/'.rawurlencode($this->client->merchantId($account)).'/price-uploads',
            [$this->payloads->priceItem($listing, $priceTry)],
        );

        return $this->trackingResult(
            $response,
            'id',
            'Hepsiburada fiyat güncellemesi kuyruğa alındı.',
        );
    }

    public function updateStock(
        SalesChannelAccount $account,
        ChannelProductListing $listing,
        string $quantity,
        ?int $leadTimeDays = null,
    ): ChannelOperationResult {
        $merchantId = rawurlencode($this->client->merchantId($account));
        $stock = $this->client->postJson(
            $account,
            'listing',
            '/listings/merchantid/'.$merchantId.'/stock-uploads',
            [$this->payloads->stockItem($listing, $quantity)],
        );
        $stockId = trim((string) ($stock['id'] ?? ''));

        if ($stockId === '') {
            return new ChannelOperationResult(
                false,
                message: 'Hepsiburada stock upload id dönmedi.',
            );
        }

        $shippingId = null;

        if ($leadTimeDays !== null) {
            $shipping = $this->client->postJson(
                $account,
                'listing',
                '/listings/merchantid/'.$merchantId.'/shipping-info-uploads',
                [$this->payloads->shippingItem($listing, $leadTimeDays)],
            );
            $shippingId = trim((string) ($shipping['id'] ?? ''));

            if ($shippingId === '') {
                return new ChannelOperationResult(
                    false,
                    externalId: $stockId,
                    message: 'Hepsiburada shipping-info upload id dönmedi.',
                    safeMetadata: ['stock_upload_id' => $stockId],
                );
            }
        }

        return new ChannelOperationResult(
            true,
            $stockId,
            $shippingId
                ? 'Hepsiburada stok ve teslimat bilgisi kuyruğa alındı.'
                : 'Hepsiburada stok güncellemesi kuyruğa alındı.',
            [
                'stock_upload_id' => $stockId,
                'shipping_upload_id' => $shippingId,
            ],
        );
    }

    public function fetchOrders(
        SalesChannelAccount $account,
        ?CarbonImmutable $since = null,
    ): array {
        $merchantId = rawurlencode($this->client->merchantId($account));
        $rows = $this->pagedOrderRows(
            $account,
            '/orders/merchantId/'.$merchantId,
            $since,
            100,
        );
        $events = [];

        foreach (collect($rows)->groupBy(
            fn (array $row): string => trim((string) (
                $row['orderNumber']
                ?? $row['OrderNumber']
                ?? ''
            )),
        ) as $orderNumber => $items) {
            if ($orderNumber === '') {
                continue;
            }

            $detail = $this->client->get(
                $account,
                'oms',
                '/orders/merchantId/'.$merchantId.'/ordernumber/'.rawurlencode($orderNumber),
            );
            $data = $this->normalizeOrder(
                $orderNumber,
                $items->values()->all(),
                $detail,
            );

            $events[] = new ChannelInboundEvent(
                eventType: 'order',
                externalId: $orderNumber,
                occurredAt: $this->date(
                    $data['orderDate'] ?? null,
                    $since,
                ),
                data: $data,
            );
        }

        return $events;
    }

    public function fetchCancellations(
        SalesChannelAccount $account,
        ?CarbonImmutable $since = null,
    ): array {
        $merchantId = rawurlencode($this->client->merchantId($account));
        $rows = $this->pagedOrderRows(
            $account,
            '/orders/merchantId/'.$merchantId.'/cancelled',
            $since,
            50,
        );
        $events = [];

        foreach ($rows as $row) {
            $orderNumber = trim((string) ($row['orderNumber'] ?? ''));
            $lineId = trim((string) ($row['lineItemId'] ?? $row['id'] ?? ''));

            if ($orderNumber === '' || $lineId === '') {
                continue;
            }

            $cancelDate = $row['cancelDate'] ?? null;
            $events[] = new ChannelInboundEvent(
                eventType: 'cancel',
                externalId: mb_substr($orderNumber.':'.$lineId, 0, 120),
                occurredAt: $this->date($cancelDate, $since),
                data: [
                    'orderNumber' => $orderNumber,
                    'lastModifiedDate' => $this->timestampMs($cancelDate),
                    'lines' => [[
                        'quantity' => (string) ($row['quantity'] ?? '0'),
                        'lineId' => $lineId,
                        'stockCode' => (string) ($row['merchantSku'] ?? ''),
                        'barcode' => (string) ($row['barcode'] ?? ''),
                    ]],
                    'channel_metadata' => [
                        'cancelled_by' => $row['cancelledBy'] ?? null,
                        'cancel_reason_code' => $row['cancelReasonCode'] ?? null,
                    ],
                ],
            );
        }

        return $events;
    }

    public function fetchReturns(
        SalesChannelAccount $account,
        ?CarbonImmutable $since = null,
    ): array {
        $merchantId = rawurlencode($this->client->merchantId($account));
        $events = [];
        $seen = [];

        foreach (['NewRequest', 'AwaitingAction'] as $status) {
            $offset = 0;

            do {
                $query = [
                    'offset' => $offset,
                    'limit' => 100,
                ];

                if ($since) {
                    $query['beginDate'] = $since->format('Y-m-d H:i');
                    $query['endDate'] = CarbonImmutable::now()->format('Y-m-d H:i');
                }

                $response = $this->client->get(
                    $account,
                    'oms',
                    '/claims/merchantId/'.$merchantId.'/status/'.$status,
                    $query,
                );
                $rows = $this->rows($response);

                foreach ($rows as $claim) {
                    $claimType = strtolower(trim((string) (
                        $claim['claimType']
                        ?? $claim['type']
                        ?? ''
                    )));

                    if ($claimType !== 'return') {
                        continue;
                    }

                    $claimNumber = trim((string) (
                        $claim['claimNumber']
                        ?? $claim['number']
                        ?? $claim['id']
                        ?? ''
                    ));

                    if ($claimNumber === '' || isset($seen[$claimNumber])) {
                        continue;
                    }

                    $seen[$claimNumber] = true;
                    $line = is_array($claim['line'] ?? null)
                        ? $claim['line']
                        : [];
                    $events[] = new ChannelInboundEvent(
                        eventType: 'return',
                        externalId: mb_substr($claimNumber, 0, 120),
                        occurredAt: $this->date(
                            $claim['claimDate'] ?? null,
                            $since,
                        ),
                        data: [
                            'orderNumber' => (string) ($claim['orderNumber'] ?? ''),
                            'externalPackageId' => data_get($claim, 'delivery.packageNumber'),
                            'returnLines' => [[
                                'externalLineId' => (string) (
                                    $claim['lineItemId']
                                    ?? $line['lineItemId']
                                    ?? ''
                                ),
                                'stockCode' => (string) (
                                    $claim['merchantSku']
                                    ?? $line['merchantSku']
                                    ?? ''
                                ),
                                'barcode' => (string) (
                                    $claim['barcode']
                                    ?? $line['barcode']
                                    ?? ''
                                ),
                                'quantity' => (string) (
                                    $claim['quantity']
                                    ?? $line['quantity']
                                    ?? '0'
                                ),
                                'reason' => (string) (
                                    $claim['reason']
                                    ?? $claim['explanation']
                                    ?? ''
                                ),
                            ]],
                        ],
                    );
                }

                $offset += count($rows);
            } while (count($rows) === 100);
        }

        return $events;
    }

    public function pushShipmentStatus(
        SalesChannelAccount $account,
        array $shipment,
    ): ChannelOperationResult {
        $packageNumber = trim((string) ($shipment['package_id'] ?? ''));
        $status = strtolower(trim((string) ($shipment['status'] ?? '')));

        if ($packageNumber === '') {
            return new ChannelOperationResult(
                false,
                message: 'Hepsiburada package number eksik.',
            );
        }

        $action = match ($status) {
            'intransit', 'shipped' => 'intransit',
            'delivered' => 'deliver',
            'undelivered', 'undeliveredproduct' => 'undeliver',
            default => null,
        };

        if ($action === null) {
            return new ChannelOperationResult(
                false,
                message: 'Hepsiburada shipment status desteklenmiyor: '.$status,
            );
        }

        $this->client->postEmptyObject(
            $account,
            'oms',
            '/packages/merchantId/'.rawurlencode($this->client->merchantId($account))
                .'/packagenumber/'.rawurlencode($packageNumber).'/'.$action,
        );

        return new ChannelOperationResult(
            true,
            $packageNumber,
            'Hepsiburada paket durumu güncellendi.',
        );
    }

    /** @return list<array<string,mixed>> */
    private function pagedOrderRows(
        SalesChannelAccount $account,
        string $path,
        ?CarbonImmutable $since,
        int $limit,
    ): array {
        $offset = 0;
        $all = [];

        do {
            $query = [
                'offset' => $offset,
                'limit' => $limit,
            ];

            if ($since) {
                $query['beginDate'] = $since->format('Y-m-d H:i');
                $query['endDate'] = CarbonImmutable::now()->format('Y-m-d H:i');
            }

            $response = $this->client->get(
                $account,
                'oms',
                $path,
                $query,
            );
            $rows = $this->rows($response);

            foreach ($rows as $row) {
                $all[] = $row;
            }

            $offset += count($rows);
        } while (count($rows) === $limit);

        return $all;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private function normalizeOrder(
        string $orderNumber,
        array $rows,
        array $detail,
    ): array {
        $source = $detail !== [] ? $detail : ($rows[0] ?? []);
        $address = $this->arrayValue(
            $source['shippingAddress']
            ?? $source['deliveryAddress']
            ?? $source['DeliveryAddress']
            ?? ($rows[0]['shippingAddress'] ?? []),
        );
        $customer = $this->arrayValue(
            $source['customer']
            ?? $source['Customer']
            ?? [],
        );
        $customerName = trim((string) (
            $source['customerName']
            ?? $customer['name']
            ?? $customer['Name']
            ?? ''
        ));
        [$firstName, $lastName] = $this->splitName($customerName);
        $rawLines = $this->rowsFromPotentialList(
            $source['items']
            ?? $source['lineItems']
            ?? $source['LineItems']
            ?? $rows,
        );
        $lines = [];

        foreach ($rawLines as $row) {
            $quantity = bcadd((string) ($row['quantity'] ?? $row['Quantity'] ?? '0'), '0', 3);

            if (bccomp($quantity, '0', 3) <= 0) {
                continue;
            }

            $unit = $this->moneyAmount(
                $row['unitPrice']
                ?? $row['price']
                ?? $row['Price']
                ?? $row['merchantUnitPrice']
                ?? 0,
            );
            $total = $this->moneyAmount(
                $row['totalPrice']
                ?? $row['TotalPrice']
                ?? $row['merchantTotalPrice']
                ?? bcmul($unit, $quantity, 8),
            );
            $discountPerUnit = bcsub(
                $unit,
                bcdiv($total, $quantity, 8),
                4,
            );

            if (bccomp($discountPerUnit, '0', 4) < 0) {
                $discountPerUnit = '0.0000';
            }

            $lines[] = [
                'quantity' => $quantity,
                'lineGrossAmount' => $unit,
                'lineTotalDiscount' => $discountPerUnit,
                'lineId' => (string) (
                    $row['lineItemId']
                    ?? $row['id']
                    ?? ''
                ),
                'stockCode' => (string) (
                    $row['merchantSku']
                    ?? $row['stockCode']
                    ?? ''
                ),
                'barcode' => (string) ($row['barcode'] ?? ''),
                'vatRate' => $this->vatRate($row),
            ];
        }

        $currency = strtoupper((string) (
            data_get($source, 'totalPrice.currency')
            ?? data_get($rows[0] ?? [], 'unitPrice.currency')
            ?? 'TRY'
        ));
        $orderDate = $source['orderDate']
            ?? $source['OrderDate']
            ?? ($rows[0]['orderDate'] ?? null);

        return [
            'orderNumber' => $orderNumber,
            'currencyCode' => $currency,
            'orderDate' => $this->timestampMs($orderDate),
            'customerFirstName' => $firstName,
            'customerLastName' => $lastName,
            'customerEmail' => (string) (
                $source['email']
                ?? $address['email']
                ?? $address['Email']
                ?? ''
            ),
            'shipmentAddress' => [
                'fullName' => (string) (
                    $address['name']
                    ?? $address['Name']
                    ?? $customerName
                ),
                'phone' => (string) (
                    $address['phoneNumber']
                    ?? $address['PhoneNumber']
                    ?? ''
                ),
                'fullAddress' => (string) (
                    $address['addressDetail']
                    ?? $address['AddressDetail']
                    ?? ''
                ),
                'city' => (string) ($address['city'] ?? $address['City'] ?? ''),
                'district' => (string) (
                    $address['district']
                    ?? $address['District']
                    ?? $address['town']
                    ?? $address['Town']
                    ?? ''
                ),
                'postalCode' => (string) (
                    $address['postalCode']
                    ?? $address['PostalCode']
                    ?? ''
                ),
            ],
            'cargoProviderName' => (string) (
                $source['cargoCompany']
                ?? $rows[0]['cargoCompany']
                ?? ''
            ),
            'cargoTrackingNumber' => (string) (
                $source['trackingInfoCode']
                ?? $rows[0]['trackingInfoCode']
                ?? ''
            ),
            'shipmentNumber' => (string) (
                $source['barcode']
                ?? $rows[0]['barcode']
                ?? ''
            ),
            'shipmentPackageId' => (string) (
                $source['packageNumber']
                ?? $rows[0]['packageNumber']
                ?? ''
            ),
            'paymentMethod' => (string) (
                $source['paymentType']
                ?? $source['paymentStatus']
                ?? ''
            ),
            'status' => (string) (
                $source['status']
                ?? $rows[0]['status']
                ?? 'Open'
            ),
            'lines' => $lines,
        ];
    }

    /** @return list<array<string,mixed>> */
    private function rows(array $response): array
    {
        return $this->rowsFromPotentialList(
            $response['items']
            ?? $response['data']
            ?? $response,
        );
    }

    /** @return list<array<string,mixed>> */
    private function rowsFromPotentialList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        if (! array_is_list($value)) {
            return [$value];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    /** @return array<string,mixed> */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
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

    private function moneyAmount(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value['amount'] ?? $value['Amount'] ?? 0;
        }

        return bcadd((string) $value, '0', 4);
    }

    private function vatRate(array $row): string
    {
        if (isset($row['vatRate'])) {
            return bcadd((string) $row['vatRate'], '0', 4);
        }

        return '0.0000';
    }

    private function trackingResult(
        array $response,
        string $key,
        string $successMessage,
    ): ChannelOperationResult {
        $trackingId = trim((string) (
            $response[$key]
            ?? $response['trackingId']
            ?? $response['id']
            ?? ''
        ));

        return new ChannelOperationResult(
            success: $trackingId !== '',
            externalId: $trackingId !== '' ? $trackingId : null,
            message: $trackingId !== ''
                ? $successMessage
                : 'Hepsiburada async işlem takip id dönmedi.',
        );
    }

    private function date(
        mixed $value,
        ?CarbonImmutable $fallback = null,
    ): CarbonImmutable {
        if (is_numeric($value) && (int) $value > 0) {
            return CarbonImmutable::createFromTimestampMs(
                (int) $value,
                config('app.timezone'),
            );
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return CarbonImmutable::parse($value, config('app.timezone'));
            } catch (\Throwable) {
                // fall through to deterministic polling boundary/current time
            }
        }

        return $fallback ?? CarbonImmutable::now();
    }

    private function timestampMs(mixed $value): int
    {
        return $this->date($value)->getTimestampMs();
    }

}
