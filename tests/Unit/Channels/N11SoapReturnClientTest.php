<?php

use App\Models\SalesChannelAccount;
use App\Support\Channels\ChannelSensitiveDataRedactor;
use App\Support\Channels\N11\N11Client;
use Illuminate\Support\Facades\Http;

it('parses N11 claim returns using only mocked WSDL and SOAP responses', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.n11.com/ws/ReturnService.wsdl' => Http::response(
            '<definitions><service><port><soap:address location="https://api.n11.com/mock/returns"/></port></service></definitions>'
        ),
        'https://api.n11.com/mock/returns' => Http::response(
            '<Envelope><Body><claimReturn><claimReturnId>RET-100</claimReturnId><status>REQUESTED</status><orderNumber>ORD-1</orderNumber><quantity>2</quantity></claimReturn></Body></Envelope>'
        ),
    ]);
    $account = new SalesChannelAccount;
    $account->forceFill(['credentials_encrypted' => ['app_key' => 'mock-key', 'app_secret' => 'mock-secret']]);

    $client = new N11Client(new ChannelSensitiveDataRedactor);
    $rows = $client->claimReturns($account, '2026-01-01', '2026-01-31', 1);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['claimReturnId'])->toBe('RET-100')
        ->and($rows[0]['status'])->toBe('REQUESTED')
        ->and($rows[0]['orderNumber'])->toBe('ORD-1')
        ->and($rows[0]['quantity'])->toBe('2');
    Http::assertSentCount(2);
});

it('rejects an insecure SOAP service endpoint from an untrusted WSDL', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.n11.com/ws/ReturnService.wsdl' => Http::response(
            '<soap:address location="http://127.0.0.1/mock/returns"/>'
        ),
    ]);
    $account = new SalesChannelAccount;
    $account->forceFill(['credentials_encrypted' => ['app_key' => 'mock-key', 'app_secret' => 'mock-secret']]);

    expect(fn () => (new N11Client(new ChannelSensitiveDataRedactor))
        ->claimReturns($account, '2026-01-01', '2026-01-31', 1))
        ->toThrow(DomainException::class);
    Http::assertSentCount(1);
});
