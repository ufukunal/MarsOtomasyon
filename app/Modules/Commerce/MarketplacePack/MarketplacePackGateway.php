<?php

namespace App\Modules\Commerce\MarketplacePack;

use App\Modules\Commerce\Providers\Amazon\AmazonSpApiClient;
use App\Modules\Commerce\Providers\Hepsiburada\HepsiburadaClient;
use DomainException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final readonly class MarketplacePackGateway
{
    public function __construct(
        private HepsiburadaClient $hepsiburada,
        private AmazonSpApiClient $amazon,
    ) {}

    /** @param array<string,mixed> $credentials */
    public function connectionTest(string $provider, array $credentials): Response
    {
        return match ($provider) {
            'hepsiburada' => $this->hepsiburada->connectionTest($credentials),
            'amazon' => $this->amazon->connectionTest($credentials),
            'n11' => $this->n11($credentials)->get('https://api.n11.com/ms/product-query', ['page' => 0, 'size' => 1]),
            'pttavm' => $this->ptt($credentials)->get('https://integration-api.pttavm.com/api/v1/categories/main'),
            'idefix' => $this->idefix($credentials)->get('https://merchantapi.idefix.com/pim/product-category'),
            'allesgo' => $this->allesgo($credentials)->get($this->allesgoBase($credentials).'/v1.0/order/store/'.rawurlencode($this->required($credentials, 'store_id')).'?'.http_build_query([
                'status' => 4,
                'start_date' => now()->subDay()->timestamp,
                'end_date' => now()->timestamp,
                'access_token' => $this->required($credentials, 'access_token'),
            ])),
            default => throw new DomainException('Marketplace pack provider is unsupported.'),
        };
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @param  array<string,mixed>  $payload
     */
    public function publishDesiredState(string $provider, array $credentials, string $identity, array $payload): Response
    {
        $quantity = array_key_exists('quantity', $payload) && $payload['quantity'] !== null ? (int) $payload['quantity'] : null;
        $price = array_key_exists('price', $payload) && $payload['price'] !== null ? (string) $payload['price'] : null;
        $currency = isset($payload['currency_code']) ? strtoupper((string) $payload['currency_code']) : null;

        if ($quantity === null && $price === null) {
            throw new DomainException('Marketplace desired-state requires stock or price.');
        }
        if ($quantity !== null && $quantity < 0) {
            throw new DomainException('Marketplace stock cannot be negative.');
        }

        return match ($provider) {
            'hepsiburada' => $this->hepsiburadaDesiredState($credentials, $identity, $quantity, $price),
            'amazon' => $this->amazon->patchDesiredState(
                $credentials,
                $identity,
                $this->amazonQuantity($payload, $quantity),
                $price,
                $currency,
                isset($payload['product_type']) ? (string) $payload['product_type'] : 'PRODUCT',
            ),
            'n11' => $this->n11($credentials)->post('https://api.n11.com/ms/product/tasks/price-stock-update', [
                'payload' => [
                    'integrator' => $this->required($credentials, 'integrator'),
                    'skus' => [array_filter([
                        'stockCode' => $identity,
                        'listPrice' => $price === null ? null : (float) $price,
                        'salePrice' => $price === null ? null : (float) $price,
                        'quantity' => $quantity,
                        'currencyType' => $currency === 'TRY' || $currency === null ? 'TL' : $currency,
                    ], static fn (mixed $value): bool => $value !== null)],
                ],
            ]),
            'pttavm' => $this->ptt($credentials)->post('https://integration-api.pttavm.com/api/v1/products/stock-prices', [
                'items' => [array_filter([
                    'barcode' => $identity,
                    'quantity' => $quantity,
                    'priceWithVAT' => $price === null ? null : (float) $price,
                ], static fn (mixed $value): bool => $value !== null)],
            ]),
            'idefix' => $this->idefix($credentials)->post(
                'https://merchantapi.idefix.com/pim/catalog/'.rawurlencode($this->required($credentials, 'vendor_id')).'/inventory-upload',
                ['items' => [array_filter([
                    'barcode' => $identity,
                    'inventoryQuantity' => $quantity,
                    'salePrice' => $price === null ? null : (float) $price,
                    'listPrice' => $price === null ? null : (float) $price,
                ], static fn (mixed $value): bool => $value !== null)]],
            ),
            'allesgo' => $this->allesgoDesiredState($credentials, $identity, $quantity, $price, $currency),
            default => throw new DomainException('Marketplace desired-state provider is unsupported.'),
        };
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @param  array<string,mixed>  $query
     */
    public function orders(string $provider, array $credentials, array $query): Response
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $size = max(1, min(100, (int) ($query['size'] ?? 50)));
        $start = isset($query['start']) ? (string) $query['start'] : now()->subDays(7)->toIso8601String();
        $end = isset($query['end']) ? (string) $query['end'] : now()->toIso8601String();
        $paginationToken = isset($query['pagination_token']) && is_scalar($query['pagination_token'])
            ? trim((string) $query['pagination_token'])
            : '';

        return match ($provider) {
            'hepsiburada' => $this->hepsiburada->paidOrders($credentials, ['offset' => ($page - 1) * $size, 'limit' => $size]),
            'amazon' => $this->amazon->searchOrders($credentials, array_filter([
                'lastUpdatedAfter' => $start,
                'lastUpdatedBefore' => $end,
                'maxResultsPerPage' => $size,
                'paginationToken' => $paginationToken === '' ? null : $paginationToken,
            ], static fn (mixed $value): bool => $value !== null)),
            'n11' => $this->n11($credentials)->get('https://api.n11.com/rest/delivery/v1/shipmentPackages', [
                'startDate' => strtotime($start) * 1000,
                'endDate' => strtotime($end) * 1000,
                'page' => $page - 1,
                'size' => $size,
                'orderByDirection' => 'ASC',
            ]),
            'pttavm' => $this->ptt($credentials)->get('https://integration-api.pttavm.com/api/v1/orders/search', [
                'startDate' => $start,
                'endDate' => $end,
                'isActiveOrders' => 'false',
            ]),
            'idefix' => $this->idefix($credentials)->get(
                'https://merchantapi.idefix.com/oms/'.rawurlencode($this->required($credentials, 'vendor_id')).'/list',
                ['page' => $page, 'limit' => $size],
            ),
            'allesgo' => $this->allesgo($credentials)->get($this->allesgoBase($credentials).'/v1.0/order/store/'.rawurlencode($this->required($credentials, 'store_id')), [
                'start_date' => strtotime($start),
                'end_date' => strtotime($end),
                'access_token' => $this->required($credentials, 'access_token'),
            ]),
            default => throw new DomainException('Marketplace order provider is unsupported.'),
        };
    }

    /** @param array<string,mixed> $credentials */
    public function taskStatus(string $provider, array $credentials, string $taskId): Response
    {
        if ($taskId === '') {
            throw new DomainException('Marketplace task id is required.');
        }

        return match ($provider) {
            'hepsiburada' => $this->hepsiburada->inventoryUploadStatus($credentials, $taskId),
            'n11' => $this->n11($credentials)->post('https://api.n11.com/ms/product/task-details/page-query', [
                'taskId' => is_numeric($taskId) ? (int) $taskId : $taskId,
                'pageable' => ['page' => 0, 'size' => 1000],
            ]),
            'pttavm' => $this->ptt($credentials)->get('https://integration-api.pttavm.com/api/v1/products/tracking-result/'.rawurlencode($taskId)),
            'idefix' => $this->idefix($credentials)->get('https://merchantapi.idefix.com/pim/batch-result/'.rawurlencode($taskId)),
            default => throw new DomainException('Marketplace task-status contract is unsupported for provider.'),
        };
    }

    /** @param array<string,mixed> $credentials */
    private function n11(array $credentials): PendingRequest
    {
        return Http::acceptJson()->asJson()->withHeaders([
            'appkey' => $this->required($credentials, 'app_key'),
            'appsecret' => $this->required($credentials, 'app_secret'),
        ])->timeout(30);
    }

    /** @param array<string,mixed> $credentials */
    private function ptt(array $credentials): PendingRequest
    {
        return Http::acceptJson()->asJson()->withHeaders([
            'Api-Key' => $this->required($credentials, 'api_key'),
            'Access-Token' => $this->required($credentials, 'access_token'),
            'X-Correlation-Id' => (string) Str::uuid(),
        ])->timeout(30);
    }

    /** @param array<string,mixed> $credentials */
    private function idefix(array $credentials): PendingRequest
    {
        $token = $credentials['x_api_key'] ?? null;
        if (! is_string($token) || trim($token) === '') {
            $key = $this->required($credentials, 'api_key');
            $secret = $this->required($credentials, 'api_secret');
            $token = base64_encode($key.':'.$secret);
        }

        return Http::acceptJson()->asJson()->withHeaders(['X-API-KEY' => $token])->timeout(30);
    }

    /** @param array<string,mixed> $credentials */
    private function allesgo(array $credentials): PendingRequest
    {
        $this->required($credentials, 'access_token');

        return Http::acceptJson()->asJson()->timeout(30);
    }

    /** @param array<string,mixed> $credentials */
    private function allesgoBase(array $credentials): string
    {
        $environment = strtolower(trim((string) ($credentials['environment'] ?? 'sandbox')));

        return match ($environment) {
            'production', 'prod' => 'https://api.allesgo.com',
            'sandbox', 'test', 'stage' => 'https://sandbox-api.allesgo.com',
            default => throw new DomainException('Allesgo environment must be sandbox or production.'),
        };
    }

    /** @param array<string,mixed> $credentials */
    private function hepsiburadaDesiredState(array $credentials, string $identity, ?int $quantity, ?string $price): Response
    {
        $merchantId = $this->required($credentials, 'merchant_id');
        $environment = strtolower(trim((string) ($credentials['environment'] ?? 'sit')));
        $base = in_array($environment, ['production', 'prod'], true)
            ? HepsiburadaClient::PROD_LISTING_BASE_URL
            : HepsiburadaClient::SIT_LISTING_BASE_URL;
        $request = Http::acceptJson()->asJson()
            ->withBasicAuth($this->required($credentials, 'username'), $this->required($credentials, 'password'))
            ->withHeaders(['User-Agent' => $this->required($credentials, 'user_agent')])
            ->timeout(30);

        return $request->post($base.'/listings/merchantid/'.$merchantId.'/inventory-uploads', [
            'items' => [array_filter([
                'sku' => $identity,
                'availableStock' => $quantity,
                'price' => $price === null ? null : (float) $price,
            ], static fn (mixed $value): bool => $value !== null)],
        ]);
    }

    /** @param array<string,mixed> $payload */
    private function amazonQuantity(array $payload, ?int $quantity): ?int
    {
        $fulfillment = strtoupper(trim((string) ($payload['fulfillment'] ?? 'FBM')));
        if (! in_array($fulfillment, ['FBM', 'FBA'], true)) {
            throw new DomainException('Amazon fulfillment must be FBM or FBA.');
        }
        if ($fulfillment === 'FBA' && $quantity !== null) {
            throw new DomainException('Amazon FBA stock is Amazon-authoritative.');
        }

        return $quantity;
    }

    /** @param array<string,mixed> $credentials */
    private function allesgoDesiredState(array $credentials, string $identity, ?int $quantity, ?string $price, ?string $currency): Response
    {
        if ($price !== null && ! in_array($currency ?? 'TRY', ['TRY', 'TL', 'USD', 'EUR'], true)) {
            throw new DomainException('Allesgo currency is unsupported.');
        }
        $payload = array_filter([
            'product_id' => $identity,
            'stock_count' => $quantity,
            'price' => $price === null ? null : (int) round(((float) $price) * 100),
            'currency' => ($currency ?? 'TRY') === 'TRY' ? 'TL' : $currency,
        ], static fn (mixed $value): bool => $value !== null);

        return $this->allesgo($credentials)->post(
            $this->allesgoBase($credentials).'/v1.0/product/update/store/'.rawurlencode($this->required($credentials, 'store_id')).'?access_token='.rawurlencode($this->required($credentials, 'access_token')),
            $payload,
        );
    }

    /** @param array<string,mixed> $credentials */
    private function required(array $credentials, string $key): string
    {
        $value = trim((string) ($credentials[$key] ?? ''));
        if ($value === '' || preg_match('/[\r\n]/', $value) === 1) {
            throw new DomainException('Marketplace credential '.$key.' is required.');
        }

        return $value;
    }
}
