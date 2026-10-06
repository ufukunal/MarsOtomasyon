<?php

namespace App\Support\Channels\Trendyol;

use App\Models\SalesChannelAccount;
use App\Support\Channels\ChannelSensitiveDataRedactor;
use DomainException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class TrendyolClient
{
    public function __construct(private readonly ChannelSensitiveDataRedactor $redactor) {}

    /** @return array<array-key,mixed> */
    /** @param array<array-key,mixed> $query @return array<array-key,mixed> */
    public function get(SalesChannelAccount $account, string $path, array $query = []): array
    {
        return $this->decode($account, $this->request($account)->get($this->url($account, $path), $query));
    }

    /** @return array<array-key,mixed> */
    /** @param array<array-key,mixed> $payload @return array<array-key,mixed> */
    public function post(SalesChannelAccount $account, string $path, array $payload): array
    {
        return $this->decode($account, $this->request($account)->post($this->url($account, $path), $payload));
    }

    /** @return array<array-key,mixed> */
    /** @param array<array-key,mixed> $payload @return array<array-key,mixed> */
    public function put(SalesChannelAccount $account, string $path, array $payload): array
    {
        return $this->decode($account, $this->request($account)->put($this->url($account, $path), $payload));
    }

    public function sellerId(SalesChannelAccount $account): int
    {
        $sellerId = (int) ($account->credentials()['seller_id'] ?? 0);

        if ($sellerId <= 0) {
            throw new DomainException('Trendyol credential seller_id eksik/geçersiz.');
        }

        return $sellerId;
    }

    private function request(SalesChannelAccount $account): PendingRequest
    {
        $credentials = $account->credentials();
        $apiKey = trim((string) ($credentials['api_key'] ?? ''));
        $apiSecret = trim((string) ($credentials['api_secret'] ?? ''));

        if ($apiKey === '' || $apiSecret === '') {
            throw new DomainException('Trendyol api_key/api_secret credential bilgileri eksik.');
        }

        $sellerId = $this->sellerId($account);
        $integrator = preg_replace('/[^A-Za-z0-9]/', '', (string) ($account->settings['integrator_name'] ?? '')) ?: 'SelfIntegration';
        $integrator = mb_substr($integrator, 0, 30);

        return Http::acceptJson()
            ->asJson()
            ->withBasicAuth($apiKey, $apiSecret)
            ->withHeaders([
                'User-Agent' => $sellerId.' - '.$integrator,
            ])
            ->connectTimeout(10)
            ->timeout(30);
    }

    private function url(SalesChannelAccount $account, string $path): string
    {
        $environment = (string) ($account->settings['environment'] ?? 'prod');
        $base = $environment === 'stage'
            ? 'https://stageapigw.trendyol.com'
            : 'https://apigw.trendyol.com';

        return $base.'/'.ltrim($path, '/');
    }

    /** @return array<array-key,mixed> */
    private function decode(SalesChannelAccount $account, Response $response): array
    {
        if (! $response->successful()) {
            $summary = $this->redactor->redact(
                'Trendyol HTTP '.$response->status().' request failed.',
                $account,
            );

            throw new DomainException($summary);
        }

        $json = $response->json();

        if ($json === null || $json === '') {
            return [];
        }

        return is_array($json) ? $json : ['value' => $json];
    }
}
