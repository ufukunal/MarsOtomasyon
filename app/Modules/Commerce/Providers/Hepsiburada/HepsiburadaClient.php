<?php

namespace App\Modules\Commerce\Providers\Hepsiburada;

use DomainException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class HepsiburadaClient
{
    public const PROD_LISTING_BASE_URL = 'https://listing-external.hepsiburada.com';

    public const SIT_LISTING_BASE_URL = 'https://listing-external-sit.hepsiburada.com';

    public const PROD_OMS_BASE_URL = 'https://oms-external.hepsiburada.com';

    public const SIT_OMS_BASE_URL = 'https://oms-external-sit.hepsiburada.com';

    public const PROD_MPOP_BASE_URL = 'https://mpop.hepsiburada.com';

    public const SIT_MPOP_BASE_URL = 'https://mpop-sit.hepsiburada.com';

    /** @param array<string,mixed> $credentials */
    public function connectionTest(array $credentials): Response
    {
        return $this->listings($credentials, ['offset' => 0, 'limit' => 1]);
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @param  array<string,mixed>  $query
     */
    public function listings(array $credentials, array $query = []): Response
    {
        [$merchantId, $listingBaseUrl, , , $request] = $this->context($credentials);
        $offset = isset($query['offset']) ? (int) $query['offset'] : 0;
        $limit = isset($query['limit']) ? (int) $query['limit'] : 10;
        if ($offset < 0 || $limit <= 0) {
            throw new DomainException('Hepsiburada listing pagination is invalid.');
        }
        $query['offset'] = $offset;
        $query['limit'] = $limit;

        return $request->get($listingBaseUrl.'/listings/merchantid/'.$merchantId, $query);
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @param  array<string,mixed>  $query
     */
    public function paidOrders(array $credentials, array $query): Response
    {
        [$merchantId, , $omsBaseUrl, , $request] = $this->context($credentials);
        $offset = isset($query['offset']) ? (int) $query['offset'] : 0;
        $limit = isset($query['limit']) ? (int) $query['limit'] : 0;
        if ($offset < 0 || $limit <= 0 || $limit > 100) {
            throw new DomainException('Hepsiburada order polling requires limit between 1 and 100 and non-negative offset.');
        }
        $query['offset'] = $offset;
        $query['limit'] = $limit;

        return $request->get($omsBaseUrl.'/orders/merchantid/'.$merchantId, $query);
    }

    /** @param array<string,mixed> $credentials */
    public function orderDetail(array $credentials, string $orderNumber): Response
    {
        [$merchantId, , $omsBaseUrl, , $request] = $this->context($credentials);
        $orderNumber = $this->identifier($orderNumber, 'order number');

        return $request->get($omsBaseUrl.'/orders/merchantid/'.$merchantId.'/ordernumber/'.$orderNumber);
    }

    /** @param array<string,mixed> $credentials */
    public function inventoryUploadStatus(array $credentials, string $inventoryUploadId): Response
    {
        [$merchantId, $listingBaseUrl, , , $request] = $this->context($credentials);
        $inventoryUploadId = $this->identifier($inventoryUploadId, 'inventory upload id');

        return $request->get($listingBaseUrl.'/listings/merchantid/'.$merchantId.'/inventory-uploads/id/'.$inventoryUploadId);
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @param  array<string,mixed>  $query
     */
    public function productsByStatus(array $credentials, string $productStatus, array $query = []): Response
    {
        [$merchantId, , , $mpopBaseUrl, $request] = $this->context($credentials);
        $productStatus = trim($productStatus);
        if ($productStatus === '' || mb_strlen($productStatus) > 80) {
            throw new DomainException('Hepsiburada product status is invalid.');
        }
        $page = isset($query['page']) ? (int) $query['page'] : 0;
        $size = isset($query['size']) ? (int) $query['size'] : 1000;
        if ($page < 0 || $size <= 0) {
            throw new DomainException('Hepsiburada product status pagination is invalid.');
        }
        $query['merchantId'] = $merchantId;
        $query['productStatus'] = $productStatus;
        $query['version'] = $query['version'] ?? 1;
        $query['page'] = $page;
        $query['size'] = $size;

        return $request->get($mpopBaseUrl.'/product/api/products/products-by-merchant-and-status', $query);
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @return array{0:string,1:string,2:string,3:string,4:PendingRequest}
     */
    private function context(array $credentials): array
    {
        $merchantId = $this->identifier((string) ($credentials['merchant_id'] ?? ''), 'merchant id');
        $username = trim((string) ($credentials['username'] ?? ''));
        $password = (string) ($credentials['password'] ?? '');
        if ($username === '' || $password === '') {
            throw new DomainException('Hepsiburada username and password are required.');
        }
        $userAgent = trim((string) ($credentials['user_agent'] ?? ''));
        if ($userAgent === '' || mb_strlen($userAgent) > 255 || preg_match('/[\r\n]/', $userAgent) === 1) {
            throw new DomainException('Hepsiburada User-Agent is required and must be valid.');
        }
        $environment = strtolower(trim((string) ($credentials['environment'] ?? 'sit')));
        [$listingBaseUrl, $omsBaseUrl, $mpopBaseUrl] = match ($environment) {
            'sit', 'test', 'stage' => [self::SIT_LISTING_BASE_URL, self::SIT_OMS_BASE_URL, self::SIT_MPOP_BASE_URL],
            'production', 'prod' => [self::PROD_LISTING_BASE_URL, self::PROD_OMS_BASE_URL, self::PROD_MPOP_BASE_URL],
            default => throw new DomainException('Hepsiburada environment must be sit or production.'),
        };

        $request = Http::acceptJson()
            ->asJson()
            ->withBasicAuth($username, $password)
            ->withHeaders(['User-Agent' => $userAgent])
            ->timeout(30);

        return [$merchantId, $listingBaseUrl, $omsBaseUrl, $mpopBaseUrl, $request];
    }

    private function identifier(string $value, string $field): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > 192 || preg_match('/^[A-Za-z0-9._-]+$/', $value) !== 1) {
            throw new DomainException('Hepsiburada '.$field.' is invalid.');
        }

        return $value;
    }
}
