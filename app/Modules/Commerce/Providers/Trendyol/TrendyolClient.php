<?php

namespace App\Modules\Commerce\Providers\Trendyol;

use DomainException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class TrendyolClient
{
    public const PROD_BASE_URL = 'https://apigw.trendyol.com';

    public const STAGE_BASE_URL = 'https://stageapigw.trendyol.com';

    /** @param array<string,mixed> $credentials */
    public function connectionTest(array $credentials): Response
    {
        [$sellerId, $baseUrl, $request] = $this->context($credentials);

        return $request->get($baseUrl.'/integration/sellers/'.$sellerId.'/addresses');
    }

    /** @param array<string,mixed> $credentials */
    public function brands(array $credentials): Response
    {
        [, $baseUrl, $request] = $this->context($credentials);

        return $request->get($baseUrl.'/integration/product/brands');
    }

    /** @param array<string,mixed> $credentials */
    public function categories(array $credentials): Response
    {
        [, $baseUrl, $request] = $this->context($credentials);

        return $request->get($baseUrl.'/integration/product/product-categories');
    }

    /** @param array<string,mixed> $credentials */
    public function categoryAttributes(array $credentials, int $categoryId): Response
    {
        $this->positiveId($categoryId, 'category id');
        [, $baseUrl, $request] = $this->context($credentials);

        return $request->get($baseUrl.'/integration/product/categories/'.$categoryId.'/attributes');
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @param  array<string,mixed>  $query
     */
    public function categoryAttributeValues(array $credentials, int $categoryId, int $attributeId, array $query = []): Response
    {
        $this->positiveId($categoryId, 'category id');
        $this->positiveId($attributeId, 'attribute id');
        [, $baseUrl, $request] = $this->context($credentials);

        return $request->get($baseUrl.'/integration/product/categories/'.$categoryId.'/attributes/'.$attributeId.'/values', $query);
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @param  array<string,mixed>  $payload
     */
    public function createProductsV2(array $credentials, array $payload): Response
    {
        $this->boundedItems($payload, 1000, 'Trendyol Product V2 create');
        [$sellerId, $baseUrl, $request] = $this->context($credentials);

        return $request->post($baseUrl.'/integration/product/sellers/'.$sellerId.'/v2/products', $payload);
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @param  array<string,mixed>  $payload
     */
    public function updateUnapprovedProductsV2(array $credentials, array $payload): Response
    {
        $this->boundedItems($payload, 1000, 'Trendyol unapproved product update');
        [$sellerId, $baseUrl, $request] = $this->context($credentials);

        return $request->post($baseUrl.'/integration/product/sellers/'.$sellerId.'/products/unapproved-bulk-update', $payload);
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @param  array<string,mixed>  $payload
     */
    public function updateApprovedProductContentV2(array $credentials, array $payload): Response
    {
        $this->boundedItems($payload, 1000, 'Trendyol approved product content update');
        [$sellerId, $baseUrl, $request] = $this->context($credentials);

        return $request->post($baseUrl.'/integration/product/sellers/'.$sellerId.'/products/content-bulk-update', $payload);
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @param  array<string,mixed>  $payload
     */
    public function updateApprovedProductVariantV2(array $credentials, array $payload): Response
    {
        $this->boundedItems($payload, 1000, 'Trendyol approved product variant update');
        [$sellerId, $baseUrl, $request] = $this->context($credentials);

        return $request->post($baseUrl.'/integration/product/sellers/'.$sellerId.'/products/variant-bulk-update', $payload);
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @param  array<string,mixed>  $payload
     */
    public function updateApprovedProductDeliveryV2(array $credentials, array $payload): Response
    {
        $this->boundedItems($payload, 1000, 'Trendyol approved product delivery update');
        [$sellerId, $baseUrl, $request] = $this->context($credentials);

        return $request->post($baseUrl.'/integration/product/sellers/'.$sellerId.'/products/delivery-info-bulk-update', $payload);
    }

    /** @param array<string,mixed> $credentials */
    public function batchResult(array $credentials, string $batchRequestId): Response
    {
        $batchRequestId = $this->identifier($batchRequestId, 'batch request id');
        [$sellerId, $baseUrl, $request] = $this->context($credentials);

        return $request->get($baseUrl.'/integration/product/sellers/'.$sellerId.'/products/batch-requests/'.$batchRequestId);
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @param  array<string,mixed>  $payload
     */
    public function updatePriceAndInventory(array $credentials, array $payload): Response
    {
        $this->boundedItems($payload, 1000, 'Trendyol stock and price update');
        [$sellerId, $baseUrl, $request] = $this->context($credentials);

        return $request->post($baseUrl.'/integration/inventory/sellers/'.$sellerId.'/products/price-and-inventory', $payload);
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @param  array<string,mixed>  $query
     */
    public function ordersV2(array $credentials, array $query = []): Response
    {
        [$sellerId, $baseUrl, $request] = $this->context($credentials);

        return $request->get($baseUrl.'/integration/order/sellers/'.$sellerId.'/v2/orders', $query);
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @param  array<string,mixed>  $payload
     */
    public function cancelPackageItems(array $credentials, int $packageId, array $payload): Response
    {
        $this->positiveId($packageId, 'package id');
        [$sellerId, $baseUrl, $request] = $this->context($credentials);

        return $request->put($baseUrl.'/integration/order/sellers/'.$sellerId.'/shipment-packages/'.$packageId.'/items/unsupplied', $payload);
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @param  array<string,mixed>  $query
     */
    public function claims(array $credentials, array $query = []): Response
    {
        [$sellerId, $baseUrl, $request] = $this->context($credentials);

        return $request->get($baseUrl.'/integration/order/sellers/'.$sellerId.'/claims', $query);
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @param  array<string,mixed>  $payload
     */
    public function createClaim(array $credentials, array $payload): Response
    {
        [$sellerId, $baseUrl, $request] = $this->context($credentials);

        return $request->post($baseUrl.'/integration/order/sellers/'.$sellerId.'/claims/create', $payload);
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @param  array<string,mixed>  $query
     */
    public function questions(array $credentials, array $query = []): Response
    {
        [$sellerId, $baseUrl, $request] = $this->context($credentials);

        return $request->get($baseUrl.'/integration/qna/sellers/'.$sellerId.'/questions/filter', $query);
    }

    /** @param array<string,mixed> $credentials */
    public function answerQuestion(array $credentials, int $questionId, string $text): Response
    {
        $this->positiveId($questionId, 'question id');
        $text = trim($text);
        if (mb_strlen($text) < 10 || mb_strlen($text) > 2000) {
            throw new DomainException('Trendyol question answer must contain between 10 and 2000 characters.');
        }
        [$sellerId, $baseUrl, $request] = $this->context($credentials);

        return $request->post($baseUrl.'/integration/qna/sellers/'.$sellerId.'/questions/'.$questionId.'/answers', ['text' => $text]);
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @param  array<string,mixed>  $payload
     */
    public function sendInvoiceLink(array $credentials, array $payload): Response
    {
        [$sellerId, $baseUrl, $request] = $this->context($credentials);

        return $request->post($baseUrl.'/integration/sellers/'.$sellerId.'/seller-invoice-links', $payload);
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @param  array<string,mixed>  $query
     */
    public function settlements(array $credentials, array $query): Response
    {
        [$sellerId, $baseUrl, $request] = $this->context($credentials);

        return $request->get($baseUrl.'/integration/finance/che/sellers/'.$sellerId.'/settlements', $query);
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @param  array<string,mixed>  $query
     */
    public function otherFinancials(array $credentials, array $query): Response
    {
        [$sellerId, $baseUrl, $request] = $this->context($credentials);

        return $request->get($baseUrl.'/integration/finance/che/sellers/'.$sellerId.'/otherfinancials', $query);
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @param  array<string,mixed>  $payload
     */
    public function registerWebhook(array $credentials, array $payload): Response
    {
        [$sellerId, $baseUrl, $request] = $this->context($credentials);

        return $request->post($baseUrl.'/integration/webhook/sellers/'.$sellerId.'/webhooks', $payload);
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @return array{0:string,1:string,2:PendingRequest}
     */
    private function context(array $credentials): array
    {
        $sellerId = trim((string) ($credentials['seller_id'] ?? ''));
        if ($sellerId === '' || ! ctype_digit($sellerId) || (int) $sellerId <= 0) {
            throw new DomainException('Trendyol seller_id must be a positive integer.');
        }
        $apiKey = trim((string) ($credentials['api_key'] ?? ''));
        $apiSecret = trim((string) ($credentials['api_secret'] ?? ''));
        if ($apiKey === '' || $apiSecret === '') {
            throw new DomainException('Trendyol API key and secret are required.');
        }
        $environment = strtolower(trim((string) ($credentials['environment'] ?? 'production')));
        $baseUrl = match ($environment) {
            'production', 'prod' => self::PROD_BASE_URL,
            'stage', 'staging', 'test' => self::STAGE_BASE_URL,
            default => throw new DomainException('Trendyol environment must be production or stage.'),
        };
        $integrationName = trim((string) ($credentials['integration_name'] ?? 'SelfIntegration'));
        if ($integrationName === '' || mb_strlen($integrationName) > 120 || preg_match('/[\r\n]/', $integrationName) === 1) {
            throw new DomainException('Trendyol integration name is invalid.');
        }
        $storefrontCode = strtoupper(trim((string) ($credentials['storefront_code'] ?? 'TR')));
        if (! preg_match('/^[A-Z]{2}$/', $storefrontCode)) {
            throw new DomainException('Trendyol storefront code must be a two-letter code.');
        }

        $request = Http::acceptJson()
            ->asJson()
            ->withBasicAuth($apiKey, $apiSecret)
            ->withHeaders([
                'User-Agent' => $sellerId.' - '.$integrationName,
                'storeFrontCode' => $storefrontCode,
                'Accept-Language' => strtolower((string) ($credentials['accept_language'] ?? 'tr')),
            ])
            ->timeout(30);

        return [$sellerId, $baseUrl, $request];
    }

    /** @param array<string,mixed> $payload */
    private function boundedItems(array $payload, int $limit, string $operation): void
    {
        $items = $payload['items'] ?? null;
        if (! is_array($items) || $items === [] || count($items) > $limit) {
            throw new DomainException($operation.' requires between 1 and '.$limit.' items.');
        }
    }

    private function positiveId(int $value, string $field): void
    {
        if ($value <= 0) {
            throw new DomainException('Trendyol '.$field.' must be positive.');
        }
    }

    private function identifier(string $value, string $field): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > 192 || ! preg_match('/^[A-Za-z0-9._-]+$/', $value)) {
            throw new DomainException('Trendyol '.$field.' is invalid.');
        }

        return $value;
    }
}
