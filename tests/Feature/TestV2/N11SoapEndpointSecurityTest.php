<?php

use App\Models\SalesChannelAccount;
use App\Support\Channels\N11\N11Client;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('v2 N11 return SOAP sends XML-escaped credentials to the resolved public endpoint', function () {
    $account = new SalesChannelAccount([
        'credentials_encrypted' => ['app_key' => 'KEY&ONE', 'app_secret' => 'SECRET<ONE>'],
    ]);
    $posted = [];
    Http::fake(function (Request $request) use (&$posted) {
        if (str_ends_with($request->url(), '/ws/ReturnService.wsdl')) {
            return Http::response('<definitions><soap:address location="https://api.n11.com/ws/ReturnService"/></definitions>', 200);
        }

        $posted[] = ['url' => $request->url(), 'method' => $request->method(), 'body' => $request->body()];

        return Http::response('<Envelope><Body><claimReturn><claimReturnId>51</claimReturnId><status>REQUESTED</status><quantity>2</quantity></claimReturn></Body></Envelope>', 200);
    });

    $rows = app(N11Client::class)->claimReturns($account, '2026-01-01', '2026-01-31', 2);

    expect($rows)->toBe([['claimReturnId' => '51', 'status' => 'REQUESTED', 'quantity' => '2']]);
    expect($posted)->toHaveCount(1)
        ->and($posted[0]['url'])->toBe('https://api.n11.com/ws/ReturnService')
        ->and($posted[0]['method'])->toBe('POST')
        ->and($posted[0]['body'])->toContain('<appKey>KEY&amp;ONE</appKey>')
        ->toContain('<appSecret>SECRET&lt;ONE&gt;</appSecret>')
        ->toContain('<currentPage>2</currentPage>');
    Http::assertSentCount(2);
});

it('v2 N11 return SOAP refuses HTTP endpoints resolved from WSDL', function () {
    Http::fake(['api.n11.com/ws/ReturnService.wsdl' => Http::response(
        '<definitions><soap:address location="http://api.n11.com/soap"/></definitions>',
        200,
    )]);
    $account = new SalesChannelAccount([
        'credentials_encrypted' => ['app_key' => 'key', 'app_secret' => 'secret'],
    ]);

    expect(fn () => app(N11Client::class)->claimReturns($account, '2026-01-01', '2026-01-31', 0))
        ->toThrow(DomainException::class, 'HTTPS değil');
    Http::assertSentCount(1);
});

it('v2 N11 return SOAP must reject private-network WSDL endpoints before any POST', function () {
    // Expected security boundary. The inspected client currently checks only HTTPS,
    // so this regression may fail until explicit host allowlisting is implemented.
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/ws/ReturnService.wsdl')) {
            return Http::response('<definitions><soap:address location="https://127.0.0.1/internal"/></definitions>', 200);
        }

        return Http::response('<Envelope/>', 200);
    });
    $account = new SalesChannelAccount([
        'credentials_encrypted' => ['app_key' => 'key', 'app_secret' => 'secret'],
    ]);

    expect(fn () => app(N11Client::class)->claimReturns($account, '2026-01-01', '2026-01-31', 0))
        ->toThrow(DomainException::class);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '127.0.0.1'));
});
