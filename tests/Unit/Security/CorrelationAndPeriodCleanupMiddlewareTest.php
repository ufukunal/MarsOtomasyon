<?php

use App\Http\Middleware\CorrelationId;
use App\Http\Middleware\SetActiveCompany;
use App\Http\Middleware\SetActivePeriod;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

it('issues a fresh fixed-length correlation ID for each request and returns it in response headers', function (): void {
    $middleware = new CorrelationId;
    $first = Request::create('/v4');
    $second = Request::create('/v4');

    $responseA = $middleware->handle($first, fn (): Response => new Response('A'));
    $responseB = $middleware->handle($second, fn (): Response => new Response('B'));

    $idA = $responseA->headers->get('X-Correlation-ID');
    $idB = $responseB->headers->get('X-Correlation-ID');

    expect($idA)->toMatch('/^[0-9A-F]{6}$/')
        ->and($idB)->toMatch('/^[0-9A-F]{6}$/')
        ->and($first->attributes->get('correlation_id'))->toBe($idA)
        ->and($second->attributes->get('correlation_id'))->toBe($idB);
});

it('does not load privileged master or period state for anonymous requests', function (): void {
    auth()->logout();

    foreach ([new SetActiveCompany, new SetActivePeriod] as $middleware) {
        $request = Request::create('/v4-unauthenticated');
        $response = $middleware->handle($request, fn (): Response => new Response('anonymous'));

        expect($response->getContent())->toBe('anonymous');
    }
});
