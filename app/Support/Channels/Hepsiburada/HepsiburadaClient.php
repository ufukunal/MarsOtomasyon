<?php

namespace App\Support\Channels\Hepsiburada;

use App\Models\SalesChannelAccount;
use App\Support\Channels\ChannelSensitiveDataRedactor;
use DomainException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;

final class HepsiburadaClient
{
    public function __construct(private readonly ChannelSensitiveDataRedactor $redactor) {}

    /** @return array<string,mixed> */
    public function get(
        SalesChannelAccount $account,
        string $service,
        string $path,
        array $query = [],
    ): array {
        return $this->decode(
            $account,
            $this->request($account)->get($this->url($account, $service, $path), $query),
        );
    }

    /** @return array<string,mixed> */
    public function postJson(
        SalesChannelAccount $account,
        string $service,
        string $path,
        array $payload,
    ): array {
        return $this->decode(
            $account,
            $this->request($account)->asJson()->post(
                $this->url($account, $service, $path),
                $payload,
            ),
        );
    }

    /** @return array<string,mixed> */
    public function postJsonFile(
        SalesChannelAccount $account,
        string $service,
        string $path,
        array $payload,
        string $filename = 'products.json',
    ): array {
        try {
            $json = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new DomainException(
                'Hepsiburada katalog JSON dosyası üretilemedi.',
                previous: $exception,
            );
        }

        return $this->decode(
            $account,
            $this->request($account)
                ->attach('file', $json, $filename, ['Content-Type' => 'application/json'])
                ->post($this->url($account, $service, $path)),
        );
    }

    /** @return array<string,mixed> */
    public function postEmptyObject(
        SalesChannelAccount $account,
        string $service,
        string $path,
    ): array {
        return $this->decode(
            $account,
            $this->request($account)
                ->withBody('{}', 'application/json')
                ->post($this->url($account, $service, $path)),
        );
    }

    /** @return array<string,mixed> */
    public function putJson(
        SalesChannelAccount $account,
        string $service,
        string $path,
        array $payload,
    ): array {
        return $this->decode(
            $account,
            $this->request($account)->asJson()->put(
                $this->url($account, $service, $path),
                $payload,
            ),
        );
    }

    public function merchantId(SalesChannelAccount $account): string
    {
        $merchantId = trim((string) (
            $account->external_store_id
            ?: ($account->credentials()['merchant_id'] ?? '')
        ));

        if ($merchantId === '') {
            throw new DomainException('Hepsiburada MerchantId / External Store ID eksik.');
        }

        return $merchantId;
    }

    private function request(SalesChannelAccount $account): PendingRequest
    {
        $credentials = $account->credentials();
        $username = trim((string) ($credentials['username'] ?? ''));
        $password = trim((string) (
            $credentials['password']
            ?? $credentials['service_key']
            ?? ''
        ));

        if ($username === '' || $password === '') {
            throw new DomainException('Hepsiburada username/password(service_key) credential bilgileri eksik.');
        }

        $merchantId = $this->merchantId($account);
        $integrator = preg_replace(
            '/[^A-Za-z0-9._-]/',
            '',
            (string) ($account->settings['integrator_name'] ?? ''),
        ) ?: 'MarsOtomasyon';

        return Http::acceptJson()
            ->withBasicAuth($username, $password)
            ->withHeaders([
                'User-Agent' => mb_substr($integrator, 0, 120),
            ])
            ->connectTimeout(10)
            ->timeout(45);
    }

    private function url(
        SalesChannelAccount $account,
        string $service,
        string $path,
    ): string {
        $sit = in_array(
            strtolower((string) ($account->settings['environment'] ?? 'prod')),
            ['sit', 'stage', 'test'],
            true,
        );

        $host = match ($service) {
            'catalog' => $sit ? 'mpop-sit.hepsiburada.com' : 'mpop.hepsiburada.com',
            'listing' => $sit ? 'listing-external-sit.hepsiburada.com' : 'listing-external.hepsiburada.com',
            'oms' => $sit ? 'oms-external-sit.hepsiburada.com' : 'oms-external.hepsiburada.com',
            default => throw new DomainException('Geçersiz Hepsiburada servis adı.'),
        };

        return 'https://'.$host.'/'.ltrim($path, '/');
    }

    /** @return array<string,mixed> */
    private function decode(SalesChannelAccount $account, Response $response): array
    {
        if (! $response->successful()) {
            $summary = $this->redactor->redact(
                'Hepsiburada HTTP '.$response->status().' request failed.',
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
