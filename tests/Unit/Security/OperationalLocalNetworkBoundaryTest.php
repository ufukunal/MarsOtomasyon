<?php

use App\Http\Middleware\EnsureLocalNetwork;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('accepts only loopback and specified RFC1918 private IPv4 addresses for operational health', function (string $ip): void {
    $gate = new ReflectionMethod(EnsureLocalNetwork::class, 'isLocal');

    expect($gate->invoke(new EnsureLocalNetwork, $ip))->toBeTrue();
})->with(['127.0.0.1', '::1', '10.0.0.1', '10.255.255.254', '172.16.0.1', '172.31.255.254', '192.168.1.1']);

it('refuses public, link-local, unspecified and untrusted IPv4/IPv6 ranges', function (?string $ip): void {
    $gate = new ReflectionMethod(EnsureLocalNetwork::class, 'isLocal');

    expect($gate->invoke(new EnsureLocalNetwork, $ip))->toBeFalse();
})->with(['8.8.8.8', '1.1.1.1', '172.32.0.1', '192.167.0.1', '169.254.1.1', '0.0.0.0', '2606:4700:4700::1111', '', null]);

it('enforces operational health tokens before invoking a localhost health endpoint', function (): void {
    $old = config('operations.health.token');
    $called = false;

    try {
        config(['operations.health.token' => 'v4-secret-health-test']);
        $request = Request::create('/health', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']);

        expect(fn () => (new EnsureLocalNetwork)->handle(
            $request,
            function () use (&$called): Response {
                $called = true;

                return new Response('should not run');
            },
        ))->toThrow(HttpException::class);
        expect($called)->toBeFalse();

        $request->headers->set('X-Health-Token', 'v4-secret-health-test');
        $response = (new EnsureLocalNetwork)->handle($request, fn (): Response => new Response('verified'));
        expect($response->getContent())->toBe('verified');
    } finally {
        config(['operations.health.token' => $old]);
    }
});
