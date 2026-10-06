<?php

namespace App\Support\Channels\N11;

use App\Models\SalesChannelAccount;
use App\Support\Channels\ChannelSensitiveDataRedactor;
use DomainException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class N11Client
{
    private ?string $resolvedReturnServiceEndpoint = null;

    public function __construct(private readonly ChannelSensitiveDataRedactor $redactor) {}

    /** @return array<string,mixed> */
    /** @param array<string, mixed> $query @return array<string|int, mixed> */
    public function get(SalesChannelAccount $account, string $path, array $query = []): array
    {
        return $this->decode(
            $account,
            $this->request($account)->get($this->url($path), $query),
        );
    }

    /** @return array<string,mixed> */
    /** @param array<string, mixed> $payload @return array<string|int, mixed> */
    public function post(SalesChannelAccount $account, string $path, array $payload): array
    {
        return $this->decode(
            $account,
            $this->request($account)->asJson()->post($this->url($path), $payload),
        );
    }

    /** @return array<string,mixed> */
    /** @param array<string, mixed> $payload @return array<string|int, mixed> */
    public function put(SalesChannelAccount $account, string $path, array $payload): array
    {
        return $this->decode(
            $account,
            $this->request($account)->asJson()->put($this->url($path), $payload),
        );
    }

    /** @return list<array<string,string>> */
    public function claimReturns(
        SalesChannelAccount $account,
        string $startDate,
        string $endDate,
        int $page,
    ): array {
        $credentials = $this->credentials($account);
        $xml = sprintf(
            '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:sch="http://www.n11.com/ws/schemas">'
            .'<soapenv:Header/><soapenv:Body><sch:ClaimReturnListRequest>'
            .'<auth><appKey>%s</appKey><appSecret>%s</appSecret></auth>'
            .'<searchData><status>REQUESTED</status><executer></executer><searchInfoType></searchInfoType><searchQuery></searchQuery>'
            .'<sender>SELLER</sender><period><startDate>%s</startDate><endDate>%s</endDate></period></searchData>'
            .'<pagingData><currentPage>%d</currentPage></pagingData>'
            .'</sch:ClaimReturnListRequest></soapenv:Body></soapenv:Envelope>',
            htmlspecialchars($credentials['app_key'], ENT_XML1),
            htmlspecialchars($credentials['app_secret'], ENT_XML1),
            htmlspecialchars($startDate, ENT_XML1),
            htmlspecialchars($endDate, ENT_XML1),
            $page,
        );

        $endpoint = $this->returnServiceEndpoint($account);
        $response = Http::withHeaders([
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => '',
            'User-Agent' => $this->integrator($account),
        ])->withBody($xml, 'text/xml; charset=utf-8')
            ->connectTimeout(10)
            ->timeout(45)
            ->post($endpoint);

        if (! $response->successful()) {
            throw new DomainException($this->redactor->redact(
                'N11 Return SOAP HTTP '.$response->status().': '.$response->body(),
                $account,
            ));
        }

        return $this->parseClaimReturns($response->body());
    }

    /** @return array{app_key:string,app_secret:string} */
    public function credentials(SalesChannelAccount $account): array
    {
        $credentials = $account->credentials();
        $appKey = trim((string) ($credentials['app_key'] ?? $credentials['api_key'] ?? ''));
        $appSecret = trim((string) ($credentials['app_secret'] ?? $credentials['api_secret'] ?? ''));

        if ($appKey === '' || $appSecret === '') {
            throw new DomainException('N11 app_key/app_secret credential bilgileri eksik.');
        }

        return ['app_key' => $appKey, 'app_secret' => $appSecret];
    }

    public function integrator(SalesChannelAccount $account): string
    {
        $value = trim((string) ($account->settings['integrator_name'] ?? 'MarsOtomasyon'));

        return mb_substr($value !== '' ? $value : 'MarsOtomasyon', 0, 120);
    }

    private function request(SalesChannelAccount $account): PendingRequest
    {
        $credentials = $this->credentials($account);

        return Http::acceptJson()
            ->withHeaders([
                'appKey' => $credentials['app_key'],
                'appSecret' => $credentials['app_secret'],
                'User-Agent' => $this->integrator($account),
            ])
            ->connectTimeout(10)
            ->timeout(45);
    }

    private function url(string $path): string
    {
        return 'https://api.n11.com/'.ltrim($path, '/');
    }

    private function returnServiceEndpoint(SalesChannelAccount $account): string
    {
        if ($this->resolvedReturnServiceEndpoint !== null) {
            return $this->resolvedReturnServiceEndpoint;
        }

        $wsdl = Http::accept('text/xml')
            ->connectTimeout(10)
            ->timeout(30)
            ->get('https://api.n11.com/ws/ReturnService.wsdl');

        if (! $wsdl->successful()) {
            throw new DomainException($this->redactor->redact(
                'N11 ReturnService WSDL alınamadı: HTTP '.$wsdl->status(),
                $account,
            ));
        }

        if (preg_match('/<[^>]*address[^>]*location=["\']([^"\']+)["\']/i', $wsdl->body(), $match) !== 1) {
            throw new DomainException('N11 ReturnService SOAP endpoint WSDL içinde bulunamadı.');
        }

        $endpoint = trim((string) $match[1]);

        if (! str_starts_with($endpoint, 'https://')) {
            throw new DomainException('N11 ReturnService SOAP endpoint HTTPS değil.');
        }

        return $this->resolvedReturnServiceEndpoint = $endpoint;
    }

    /** @return list<array<string,string>> */
    private function parseClaimReturns(string $xml): array
    {
        if (preg_match_all(
            '/<(?:[A-Za-z0-9_-]+:)?claimReturn\b[^>]*>(.*?)<\/(?:[A-Za-z0-9_-]+:)?claimReturn>/si',
            $xml,
            $matches,
        ) === false) {
            throw new DomainException('N11 Return SOAP yanıtı parse edilemedi.');
        }

        $rows = [];
        $fields = [
            'claimReturnId',
            'status',
            'returnReasonType',
            'returnReasonDescription',
            'orderNumber',
            'campaignNumber',
            'requestDate',
            'productId',
            'skuId',
            'quantity',
            'unitPrice',
        ];

        foreach ($matches[1] ?? [] as $fragment) {
            $row = [];

            foreach ($fields as $field) {
                if (preg_match(
                    '/<(?:[A-Za-z0-9_-]+:)?'.preg_quote($field, '/').'\b[^>]*>(.*?)<\/(?:[A-Za-z0-9_-]+:)?'.preg_quote($field, '/').'>/si',
                    $fragment,
                    $value,
                ) === 1) {
                    $row[$field] = trim(html_entity_decode(
                        strip_tags((string) $value[1]),
                        ENT_QUOTES | ENT_XML1,
                        'UTF-8',
                    ));
                }
            }

            if ($row !== []) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @return array<string,mixed> */
    private function decode(SalesChannelAccount $account, Response $response): array
    {
        if (! $response->successful()) {
            throw new DomainException($this->redactor->redact(
                'N11 HTTP '.$response->status().': '.$response->body(),
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
