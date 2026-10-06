<?php

namespace App\Support\Channels\WooCommerce;

use App\Models\SalesChannelAccount;
use App\Support\Channels\ChannelSensitiveDataRedactor;
use DomainException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class WooCommerceClient
{
    public function __construct(private readonly ChannelSensitiveDataRedactor $redactor) {}

    /** @return array<string,mixed>|list<array<string,mixed>> */
    /** @param array<string, mixed> $query @return array<string|int, mixed> */
    public function get(
        SalesChannelAccount $account,
        string $path,
        array $query = [],
    ): array {
        return $this->decode(
            $account,
            $this->request($account)->get($this->url($account, $path), $query),
        );
    }

    /** @return array<string,mixed> */
    /** @param array<string, mixed> $payload @return array<string|int, mixed> */
    public function post(
        SalesChannelAccount $account,
        string $path,
        array $payload,
    ): array {
        $decoded = $this->decode(
            $account,
            $this->request($account)->asJson()->post(
                $this->url($account, $path),
                $payload,
            ),
        );

        return is_array($decoded) && ! array_is_list($decoded) ? $decoded : [];
    }

    /** @return array<string,mixed> */
    /** @param array<string, mixed> $payload @return array<string|int, mixed> */
    public function put(
        SalesChannelAccount $account,
        string $path,
        array $payload,
    ): array {
        $decoded = $this->decode(
            $account,
            $this->request($account)->asJson()->put(
                $this->url($account, $path),
                $payload,
            ),
        );

        return is_array($decoded) && ! array_is_list($decoded) ? $decoded : [];
    }

    public function storeUrl(SalesChannelAccount $account): string
    {
        $url = trim((string) (
            $account->settings['store_url']
            ?? $account->external_store_id
            ?? ''
        ));
        $url = rtrim($url, '/');

        if ($url === '' || ! str_starts_with(strtolower($url), 'https://')) {
            throw new DomainException('WooCommerce store_url public HTTPS adresi olmalıdır.');
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host === ''
            || in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_contains($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            throw new DomainException('WooCommerce store_url geçerli bir public HTTPS mağaza kökü olmalıdır.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)
            && ! filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            )) {
            throw new DomainException('WooCommerce store_url private/reserved IP kullanamaz.');
        }

        return $url;
    }

    /** @return array{consumer_key:string,consumer_secret:string} */
    public function credentials(SalesChannelAccount $account): array
    {
        $credentials = $account->credentials();
        $key = trim((string) ($credentials['consumer_key'] ?? ''));
        $secret = trim((string) ($credentials['consumer_secret'] ?? ''));

        if ($key === '' || $secret === '') {
            throw new DomainException('WooCommerce consumer_key/consumer_secret credential bilgileri eksik.');
        }

        return [
            'consumer_key' => $key,
            'consumer_secret' => $secret,
        ];
    }

    private function request(SalesChannelAccount $account): PendingRequest
    {
        $credentials = $this->credentials($account);

        return Http::acceptJson()
            ->withBasicAuth(
                $credentials['consumer_key'],
                $credentials['consumer_secret'],
            )
            ->withHeaders(['User-Agent' => 'MarsOtomasyon-WooCommerce'])
            ->connectTimeout(10)
            ->timeout(45);
    }

    private function url(SalesChannelAccount $account, string $path): string
    {
        return $this->storeUrl($account).'/wp-json/wc/v3/'.ltrim($path, '/');
    }

    /** @return array<string,mixed>|list<array<string,mixed>> */
    private function decode(SalesChannelAccount $account, Response $response): array
    {
        if (! $response->successful()) {
            throw new DomainException($this->redactor->redact(
                'WooCommerce HTTP '.$response->status().' request failed.',
                $account,
            ));
        }

        $json = $response->json();

        if ($json === null || $json === '') {
            return [];
        }

        return is_array($json) ? $json : ['value' => $json];
    }
}
