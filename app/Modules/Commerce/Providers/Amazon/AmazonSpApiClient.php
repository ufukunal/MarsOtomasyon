<?php

namespace App\Modules\Commerce\Providers\Amazon;

use DomainException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class AmazonSpApiClient
{
    public const LWA_TOKEN_URL = 'https://api.amazon.com/auth/o2/token';

    /** @var array<string,string> */
    private const PROD_ENDPOINTS = [
        'na' => 'https://sellingpartnerapi-na.amazon.com',
        'eu' => 'https://sellingpartnerapi-eu.amazon.com',
        'fe' => 'https://sellingpartnerapi-fe.amazon.com',
    ];

    /** @var array<string,string> */
    private const SANDBOX_ENDPOINTS = [
        'na' => 'https://sandbox.sellingpartnerapi-na.amazon.com',
        'eu' => 'https://sandbox.sellingpartnerapi-eu.amazon.com',
        'fe' => 'https://sandbox.sellingpartnerapi-fe.amazon.com',
    ];

    /** @param array<string,mixed> $credentials */
    public function connectionTest(array $credentials): Response
    {
        [$sellerId, $marketplaceId, $baseUrl, $request] = $this->context($credentials);

        return $request->get($baseUrl.'/listings/2021-08-01/items/'.rawurlencode($sellerId), [
            'marketplaceIds' => $marketplaceId,
            'pageSize' => 1,
            'includedData' => 'summaries',
        ]);
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @param  array<string,mixed>  $query
     */
    public function searchListings(array $credentials, array $query = []): Response
    {
        [$sellerId, $marketplaceId, $baseUrl, $request] = $this->context($credentials);
        $query['marketplaceIds'] = $marketplaceId;

        return $request->get($baseUrl.'/listings/2021-08-01/items/'.rawurlencode($sellerId), $query);
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @param  array<string,mixed>  $payload
     */
    public function patchListing(array $credentials, string $sku, array $payload): Response
    {
        $sku = $this->identifier($sku, 'SKU');
        $productType = strtoupper(trim((string) ($payload['productType'] ?? '')));
        $patches = $payload['patches'] ?? null;
        if ($productType === '' || ! is_array($patches) || $patches === []) {
            throw new DomainException('Amazon listing patch requires productType and patches.');
        }
        [$sellerId, $marketplaceId, $baseUrl, $request] = $this->context($credentials);

        return $request->patch(
            $baseUrl.'/listings/2021-08-01/items/'.rawurlencode($sellerId).'/'.rawurlencode($sku),
            $payload + ['marketplaceIds' => $marketplaceId],
        );
    }

    /**
     * Publish Mars desired stock/price using the Listings Items top-level offer attributes.
     * FBA inventory is Amazon-authoritative and cannot be overwritten through this helper.
     *
     * @param array<string,mixed> $credentials
     */
    public function patchDesiredState(
        array $credentials,
        string $sku,
        ?int $quantity,
        ?string $price,
        ?string $currencyCode,
        string $productType = 'PRODUCT',
    ): Response {
        if ($quantity === null && $price === null) {
            throw new DomainException('Amazon desired-state patch requires stock or price.');
        }
        if ($quantity !== null && $quantity < 0) {
            throw new DomainException('Amazon stock cannot be negative.');
        }
        $currencyCode = $currencyCode === null ? null : strtoupper(trim($currencyCode));
        if ($price !== null && ($currencyCode === null || ! preg_match('/^[A-Z]{3}$/', $currencyCode))) {
            throw new DomainException('Amazon price publishing requires a three-letter currency code.');
        }
        [, $marketplaceId] = $this->context($credentials);
        $patches = [];
        if ($quantity !== null) {
            $patches[] = [
                'op' => 'replace',
                'path' => '/attributes/fulfillment_availability',
                'value' => [[
                    'fulfillment_channel_code' => 'DEFAULT',
                    'quantity' => $quantity,
                    'marketplace_id' => $marketplaceId,
                ]],
            ];
        }
        if ($price !== null) {
            $patches[] = [
                'op' => 'replace',
                'path' => '/attributes/purchasable_offer',
                'value' => [[
                    'currency' => $currencyCode,
                    'audience' => 'ALL',
                    'marketplace_id' => $marketplaceId,
                    'our_price' => [[
                        'schedule' => [[
                            'value_with_tax' => (float) $price,
                        ]],
                    ]],
                ]],
            ];
        }

        return $this->patchListing($credentials, $sku, [
            'productType' => strtoupper(trim($productType)) ?: 'PRODUCT',
            'patches' => $patches,
        ]);
    }

    /**
     * Orders API v2026-01-01. Exactly one of createdAfter / lastUpdatedAfter is required by Amazon.
     *
     * @param  array<string,mixed>  $credentials
     * @param  array<string,mixed>  $query
     */
    public function searchOrders(array $credentials, array $query): Response
    {
        [, $marketplaceId, $baseUrl, $request] = $this->context($credentials);
        $createdAfter = trim((string) ($query['createdAfter'] ?? ''));
        $lastUpdatedAfter = trim((string) ($query['lastUpdatedAfter'] ?? ''));
        if (($createdAfter === '') === ($lastUpdatedAfter === '')) {
            throw new DomainException('Amazon order search requires exactly one of createdAfter or lastUpdatedAfter.');
        }
        $query['marketplaceIds'] = $marketplaceId;

        return $request->get($baseUrl.'/orders/2026-01-01/orders', $query);
    }

    /** @param array<string,mixed> $credentials */
    public function getOrder(array $credentials, string $orderId, array $includedData = []): Response
    {
        $orderId = $this->identifier($orderId, 'order id');
        [, , $baseUrl, $request] = $this->context($credentials);
        $query = $includedData === [] ? [] : ['includedData' => implode(',', $includedData)];

        return $request->get($baseUrl.'/orders/2026-01-01/orders/'.rawurlencode($orderId), $query);
    }

    /**
     * @param array<string,mixed> $credentials
     * @param array<string,mixed> $payload
     */
    public function createReport(array $credentials, array $payload): Response
    {
        $reportType = trim((string) ($payload['reportType'] ?? ''));
        if ($reportType === '') {
            throw new DomainException('Amazon reportType is required.');
        }
        [, $marketplaceId, $baseUrl, $request] = $this->context($credentials);
        $payload['marketplaceIds'] ??= [$marketplaceId];

        return $request->post($baseUrl.'/reports/2021-06-30/reports', $payload);
    }

    /** @param array<string,mixed> $credentials */
    public function requestSettlementReport(array $credentials): Response
    {
        return $this->createReport($credentials, [
            'reportType' => 'GET_V2_SETTLEMENT_REPORT_DATA_FLAT_FILE_V2',
        ]);
    }

    /** @param array<string,mixed> $credentials */
    public function requestReturnsReport(array $credentials, ?string $dataStartTime = null, ?string $dataEndTime = null): Response
    {
        $payload = ['reportType' => 'GET_FLAT_FILE_RETURNS_DATA_BY_RETURN_DATE'];
        if ($dataStartTime !== null) {
            $payload['dataStartTime'] = $dataStartTime;
        }
        if ($dataEndTime !== null) {
            $payload['dataEndTime'] = $dataEndTime;
        }

        return $this->createReport($credentials, $payload);
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @return array{0:string,1:string,2:string,3:PendingRequest}
     */
    private function context(array $credentials): array
    {
        $sellerId = $this->identifier((string) ($credentials['seller_id'] ?? ''), 'seller id');
        $marketplaceId = $this->identifier((string) ($credentials['marketplace_id'] ?? ''), 'marketplace id');
        $region = strtolower(trim((string) ($credentials['region'] ?? 'eu')));
        if (! array_key_exists($region, self::PROD_ENDPOINTS)) {
            throw new DomainException('Amazon SP-API region must be na, eu, or fe.');
        }
        $environment = strtolower(trim((string) ($credentials['environment'] ?? 'production')));
        $baseUrl = match ($environment) {
            'production', 'prod' => self::PROD_ENDPOINTS[$region],
            'sandbox', 'test' => self::SANDBOX_ENDPOINTS[$region],
            default => throw new DomainException('Amazon SP-API environment must be production or sandbox.'),
        };
        $accessToken = trim((string) ($credentials['access_token'] ?? ''));
        if ($accessToken === '') {
            $accessToken = $this->exchangeAccessToken($credentials);
        }

        $request = Http::acceptJson()
            ->asJson()
            ->withHeaders([
                'x-amz-access-token' => $accessToken,
                'User-Agent' => trim((string) ($credentials['user_agent'] ?? 'MarsOtomasyon/1.0')),
            ])
            ->timeout(30);

        return [$sellerId, $marketplaceId, $baseUrl, $request];
    }

    /** @param array<string,mixed> $credentials */
    private function exchangeAccessToken(array $credentials): string
    {
        $clientId = trim((string) ($credentials['client_id'] ?? ''));
        $clientSecret = trim((string) ($credentials['client_secret'] ?? ''));
        $refreshToken = trim((string) ($credentials['refresh_token'] ?? ''));
        if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
            throw new DomainException('Amazon LWA client_id, client_secret, and refresh_token are required.');
        }
        $response = Http::asForm()->acceptJson()->timeout(15)->post(self::LWA_TOKEN_URL, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);
        if (! $response->successful()) {
            throw new DomainException('Amazon LWA access-token exchange failed with HTTP '.$response->status().'.');
        }
        $token = trim((string) ($response->json('access_token') ?? ''));
        if ($token === '') {
            throw new DomainException('Amazon LWA response did not contain an access token.');
        }

        return $token;
    }

    private function identifier(string $value, string $field): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > 192 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new DomainException('Amazon '.$field.' is invalid.');
        }

        return $value;
    }
}
