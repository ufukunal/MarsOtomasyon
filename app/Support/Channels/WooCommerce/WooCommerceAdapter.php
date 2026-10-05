<?php

namespace App\Support\Channels\WooCommerce;

use App\Contracts\Channels\ChannelAdapter;
use App\DataObjects\Channels\ChannelInboundEvent;
use App\DataObjects\Channels\ChannelOperationResult;
use App\Models\Period\ChannelProductListing;
use App\Models\SalesChannelAccount;
use Carbon\CarbonImmutable;
use DomainException;

final class WooCommerceAdapter implements ChannelAdapter
{
    /** @var array<string,list<array<string,mixed>>> */
    private array $orderCache = [];

    public function __construct(
        private readonly WooCommerceClient $client,
        private readonly WooCommercePayloadBuilder $payloads,
    ) {}

    public function testConnection(SalesChannelAccount $account): ChannelOperationResult
    {
        $this->client->get($account, 'products', ['per_page' => 1, 'page' => 1]);

        return new ChannelOperationResult(
            true,
            message: 'WooCommerce bağlantısı başarılı.',
            safeMetadata: ['store_url' => $this->client->storeUrl($account)],
        );
    }

    public function publishListing(
        SalesChannelAccount $account,
        ChannelProductListing $listing,
    ): ChannelOperationResult {
        $productId = $this->productId($listing, required: false);
        $response = $productId
            ? $this->client->put(
                $account,
                'products/'.$productId,
                $this->payloads->create($listing),
            )
            : $this->client->post(
                $account,
                'products',
                $this->payloads->create($listing),
            );
        $id = (int) ($response['id'] ?? 0);

        if ($id <= 0) {
            return new ChannelOperationResult(
                false,
                message: 'WooCommerce product create/update yanıtında id bulunamadı.',
            );
        }

        if (! $productId) {
            $listing = $listing->refresh();
            $listing->updateWithVersion(
                [
                    'external_product_id' => (string) $id,
                    'external_listing_id' => (string) $id,
                    'external_sku' => trim((string) ($response['sku'] ?? ''))
                        ?: $listing->external_sku,
                ],
                (int) $listing->version,
            );
        }

        return new ChannelOperationResult(
            true,
            (string) $id,
            $productId
                ? 'WooCommerce ürün yayını güncellendi.'
                : 'WooCommerce ürün yayını oluşturuldu.',
            [
                'product_id' => $id,
                'status' => $response['status'] ?? null,
            ],
        );
    }

    public function updateContent(
        SalesChannelAccount $account,
        ChannelProductListing $listing,
    ): ChannelOperationResult {
        $id = $this->productId($listing);
        $response = $this->client->put(
            $account,
            'products/'.$id,
            $this->payloads->content($listing),
        );

        return $this->productResult($response, 'WooCommerce içerik/görsel güncellendi.');
    }

    public function updatePrice(
        SalesChannelAccount $account,
        ChannelProductListing $listing,
        string $priceTry,
    ): ChannelOperationResult {
        $id = $this->productId($listing);
        $response = $this->client->put(
            $account,
            'products/'.$id,
            $this->payloads->price($priceTry),
        );

        return $this->productResult($response, 'WooCommerce fiyat güncellendi.');
    }

    public function updateStock(
        SalesChannelAccount $account,
        ChannelProductListing $listing,
        string $quantity,
        ?int $leadTimeDays = null,
    ): ChannelOperationResult {
        $id = $this->productId($listing);
        $response = $this->client->put(
            $account,
            'products/'.$id,
            $this->payloads->stock($quantity),
        );

        return $this->productResult(
            $response,
            $leadTimeDays !== null
                ? 'WooCommerce stok güncellendi; core REST API lead-time alanı sağlamaz.'
                : 'WooCommerce stok güncellendi.',
        );
    }

    public function fetchOrders(
        SalesChannelAccount $account,
        ?CarbonImmutable $since = null,
    ): array {
        $events = [];

        foreach ($this->orders($account, $since) as $order) {
            if ((int) ($order['id'] ?? 0) <= 0
                || strtolower((string) ($order['status'] ?? '')) === 'trash') {
                continue;
            }

            $data = $this->normalizeOrder($order);
            $events[] = new ChannelInboundEvent(
                eventType: 'order',
                externalId: (string) $order['id'],
                occurredAt: $this->date(
                    $order['date_modified_gmt']
                        ?? $order['date_modified']
                        ?? $order['date_created_gmt']
                        ?? null,
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
        $events = [];

        foreach ($this->orders($account, $since) as $order) {
            $status = strtolower(trim((string) ($order['status'] ?? '')));

            if (! in_array($status, ['cancelled', 'failed'], true)
                || (int) ($order['id'] ?? 0) <= 0) {
                continue;
            }

            $data = $this->normalizeOrder($order);
            $data['lines'] = array_map(
                fn (array $line): array => [
                    'quantity' => $line['quantity'],
                    'lineId' => $line['lineId'],
                    'stockCode' => $line['stockCode'],
                    'barcode' => $line['barcode'],
                ],
                $data['lines'],
            );

            $events[] = new ChannelInboundEvent(
                eventType: 'cancel',
                externalId: mb_substr($order['id'].':'.$status, 0, 120),
                occurredAt: $this->date(
                    $order['date_modified_gmt'] ?? $order['date_modified'] ?? null,
                    $since,
                ),
                data: $data,
            );
        }

        return $events;
    }

    public function fetchReturns(
        SalesChannelAccount $account,
        ?CarbonImmutable $since = null,
    ): array {
        $events = [];
        $since ??= CarbonImmutable::now()->subMinutes(
            max(15, (int) config('channels.poll_lookback_minutes', 30)),
        );

        foreach ($this->orders($account, $since) as $order) {
            $refundRefs = is_array($order['refunds'] ?? null)
                ? $order['refunds']
                : [];

            if ((int) ($order['id'] ?? 0) <= 0
                || ($refundRefs === [] && strtolower((string) ($order['status'] ?? '')) !== 'refunded')) {
                continue;
            }

            foreach ($this->refunds($account, (int) $order['id']) as $refund) {
                $refundId = (int) ($refund['id'] ?? 0);

                if ($refundId <= 0) {
                    continue;
                }

                $occurredAt = $this->date(
                    $refund['date_created_gmt'] ?? $refund['date_created'] ?? null,
                    $since,
                );

                if ($occurredAt->lt($since)) {
                    continue;
                }

                $returnLines = [];

                foreach (($refund['line_items'] ?? []) as $refundLine) {
                    if (! is_array($refundLine)) {
                        continue;
                    }

                    $quantity = abs((int) ($refundLine['quantity'] ?? 0));

                    if ($quantity <= 0) {
                        continue;
                    }

                    $sourceLine = $this->sourceOrderLine($order, $refundLine);

                    $returnLines[] = [
                        'externalLineId' => (string) ($sourceLine['id'] ?? ''),
                        'stockCode' => (string) (
                            $sourceLine['sku']
                            ?? $refundLine['sku']
                            ?? ''
                        ),
                        'barcode' => '',
                        'quantity' => (string) $quantity,
                        'reason' => trim((string) ($refund['reason'] ?? '')),
                    ];
                }

                if ($returnLines === []) {
                    continue;
                }

                $events[] = new ChannelInboundEvent(
                    eventType: 'return',
                    externalId: mb_substr($order['id'].':'.$refundId, 0, 120),
                    occurredAt: $occurredAt,
                    data: [
                        'externalOrderId' => (string) $order['id'],
                        'orderNumber' => (string) ($order['number'] ?? $order['id']),
                        'externalPackageId' => null,
                        'returnLines' => $returnLines,
                    ],
                );
            }
        }

        return $events;
    }

    public function pushShipmentStatus(
        SalesChannelAccount $account,
        array $shipment,
    ): ChannelOperationResult {
        return new ChannelOperationResult(
            false,
            message: 'WooCommerce core REST API standart shipment-tracking/status push endpointi sağlamaz.',
        );
    }

    /** @return list<array<string,mixed>> */
    private function orders(
        SalesChannelAccount $account,
        ?CarbonImmutable $since,
    ): array {
        $since ??= CarbonImmutable::now()->subMinutes(
            max(15, (int) config('channels.poll_lookback_minutes', 30)),
        );
        $cacheKey = $account->id.':'.$since->getTimestamp();

        if (isset($this->orderCache[$cacheKey])) {
            return $this->orderCache[$cacheKey];
        }

        $page = 1;
        $all = [];

        do {
            $response = $this->client->get(
                $account,
                'orders',
                [
                    'modified_after' => $since->utc()->toIso8601String(),
                    'dates_are_gmt' => true,
                    'per_page' => 100,
                    'page' => $page,
                    'orderby' => 'modified',
                    'order' => 'asc',
                ],
            );
            $rows = array_is_list($response)
                ? array_values(array_filter($response, 'is_array'))
                : [];

            foreach ($rows as $row) {
                $all[] = $row;
            }

            $page++;
        } while (count($rows) === 100 && $page <= 100);

        return $this->orderCache[$cacheKey] = $all;
    }

    /** @return list<array<string,mixed>> */
    private function refunds(SalesChannelAccount $account, int $orderId): array
    {
        $page = 1;
        $all = [];

        do {
            $response = $this->client->get(
                $account,
                'orders/'.$orderId.'/refunds',
                ['per_page' => 100, 'page' => $page],
            );
            $rows = array_is_list($response)
                ? array_values(array_filter($response, 'is_array'))
                : [];

            foreach ($rows as $row) {
                $all[] = $row;
            }

            $page++;
        } while (count($rows) === 100 && $page <= 100);

        return $all;
    }

    /** @return array<string,mixed> */
    private function normalizeOrder(array $order): array
    {
        $billing = is_array($order['billing'] ?? null) ? $order['billing'] : [];
        $shipping = is_array($order['shipping'] ?? null) ? $order['shipping'] : [];
        $lines = [];

        foreach (($order['line_items'] ?? []) as $line) {
            if (! is_array($line)) {
                continue;
            }

            $quantity = max(0, (int) ($line['quantity'] ?? 0));

            if ($quantity <= 0) {
                continue;
            }

            $subtotal = bcadd((string) ($line['subtotal'] ?? '0'), '0', 4);
            $total = bcadd((string) ($line['total'] ?? $subtotal), '0', 4);
            $grossUnit = bcdiv($subtotal, (string) $quantity, 4);
            $discountPerUnit = bcdiv(
                bccomp($subtotal, $total, 4) > 0
                    ? bcsub($subtotal, $total, 4)
                    : '0',
                (string) $quantity,
                4,
            );

            $lines[] = [
                'quantity' => (string) $quantity,
                'lineGrossAmount' => $grossUnit,
                'lineTotalDiscount' => $discountPerUnit,
                'lineId' => (string) ($line['id'] ?? ''),
                'stockCode' => (string) ($line['sku'] ?? ''),
                'barcode' => '',
                'externalProductId' => (string) (
                    ((int) ($line['variation_id'] ?? 0) > 0)
                        ? $line['variation_id']
                        : ($line['product_id'] ?? '')
                ),
                'vatRate' => $this->vatRate($line),
            ];
        }

        $address = $shipping !== [] ? $shipping : $billing;
        $orderId = (string) ($order['id'] ?? '');
        $number = (string) ($order['number'] ?? $orderId);

        return [
            'externalOrderId' => $orderId,
            'orderNumber' => $number,
            'currencyCode' => strtoupper((string) ($order['currency'] ?? 'TRY')),
            'orderDate' => $this->date(
                $order['date_created_gmt'] ?? $order['date_created'] ?? null,
            )->getTimestampMs(),
            'customerFirstName' => (string) ($billing['first_name'] ?? ''),
            'customerLastName' => (string) ($billing['last_name'] ?? ''),
            'customerEmail' => (string) ($billing['email'] ?? ''),
            'shipmentAddress' => [
                'fullName' => trim((string) (
                    ($address['first_name'] ?? '').' '.($address['last_name'] ?? '')
                )),
                'phone' => (string) ($billing['phone'] ?? ''),
                'fullAddress' => trim((string) (
                    ($address['address_1'] ?? '').' '.($address['address_2'] ?? '')
                )),
                'city' => (string) ($address['city'] ?? ''),
                'district' => (string) ($address['state'] ?? ''),
                'postalCode' => (string) ($address['postcode'] ?? ''),
            ],
            'cargoProviderName' => '',
            'cargoTrackingNumber' => '',
            'shipmentNumber' => '',
            'shipmentPackageId' => '',
            'paymentMethod' => (string) (
                $order['payment_method_title']
                ?? $order['payment_method']
                ?? ''
            ),
            'status' => (string) ($order['status'] ?? ''),
            'lastModifiedDate' => $this->date(
                $order['date_modified_gmt'] ?? $order['date_modified'] ?? null,
            )->getTimestampMs(),
            'lines' => $lines,
        ];
    }

    /** @return array<string,mixed> */
    private function sourceOrderLine(array $order, array $refundLine): array
    {
        $productId = (int) ($refundLine['product_id'] ?? 0);
        $variationId = (int) ($refundLine['variation_id'] ?? 0);
        $sku = trim((string) ($refundLine['sku'] ?? ''));

        $matches = collect($order['line_items'] ?? [])
            ->filter(function ($line) use ($productId, $variationId, $sku): bool {
                if (! is_array($line)) {
                    return false;
                }

                if ($variationId > 0 && (int) ($line['variation_id'] ?? 0) === $variationId) {
                    return true;
                }

                if ($variationId <= 0 && $productId > 0 && (int) ($line['product_id'] ?? 0) === $productId) {
                    return true;
                }

                return $sku !== '' && (string) ($line['sku'] ?? '') === $sku;
            })
            ->values();

        return $matches->count() === 1 && is_array($matches->first())
            ? $matches->first()
            : [];
    }

    private function vatRate(array $line): string
    {
        $subtotal = bcadd((string) ($line['subtotal'] ?? '0'), '0', 4);
        $tax = bcadd((string) ($line['subtotal_tax'] ?? '0'), '0', 4);

        if (bccomp($subtotal, '0', 4) <= 0 || bccomp($tax, '0', 4) <= 0) {
            return '0.0000';
        }

        return bcadd(
            bcmul(bcdiv($tax, $subtotal, 8), '100', 8),
            '0',
            4,
        );
    }

    private function productId(
        ChannelProductListing $listing,
        bool $required = true,
    ): ?int {
        $raw = trim((string) (
            $listing->external_product_id
            ?: $listing->external_listing_id
        ));
        $id = ctype_digit($raw) ? (int) $raw : 0;

        if ($id <= 0 && $required) {
            throw new DomainException('WooCommerce sync için numeric external product ID zorunludur.');
        }

        return $id > 0 ? $id : null;
    }

    private function productResult(
        array $response,
        string $message,
    ): ChannelOperationResult {
        $id = (int) ($response['id'] ?? 0);

        return new ChannelOperationResult(
            success: $id > 0,
            externalId: $id > 0 ? (string) $id : null,
            message: $id > 0 ? $message : 'WooCommerce product yanıtında id bulunamadı.',
            safeMetadata: [
                'product_id' => $id > 0 ? $id : null,
                'status' => $response['status'] ?? null,
            ],
        );
    }

    private function date(
        mixed $value,
        ?CarbonImmutable $fallback = null,
    ): CarbonImmutable {
        if (is_string($value) && trim($value) !== '') {
            try {
                return CarbonImmutable::parse($value, 'UTC')
                    ->setTimezone(config('app.timezone'));
            } catch (\Throwable) {
                // fall through
            }
        }

        return $fallback ?? CarbonImmutable::now();
    }
}
