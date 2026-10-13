<?php

use App\Http\Middleware\CorrelationId;
use App\Http\Middleware\SetActiveCompany;
use App\Http\Middleware\SetActivePeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

it('issues a UUID correlation ID for each request, suitable for the audit table', function (): void {
    $middleware = new CorrelationId;
    $first = Request::create('/v4');
    $second = Request::create('/v4');

    $responseA = $middleware->handle($first, fn (): Response => new Response('A'));
    $responseB = $middleware->handle($second, fn (): Response => new Response('B'));

    $idA = $responseA->headers->get('X-Correlation-ID');
    $idB = $responseB->headers->get('X-Correlation-ID');

    $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    expect($idA)->toMatch($uuidPattern)
        ->and($idB)->toMatch($uuidPattern)
        ->and($idA)->not->toBe($idB)
        ->and($first->attributes->get('correlation_id'))->toBe($idA)
        ->and($second->attributes->get('correlation_id'))->toBe($idB);
});

it('does not load privileged master or period state for anonymous requests', function (): void {
    Auth::forgetGuards();

    foreach ([new SetActiveCompany, new SetActivePeriod] as $middleware) {
        $request = Request::create('/v4-unauthenticated');
        $response = $middleware->handle($request, fn (): Response => new Response('anonymous'));

        expect($response->getContent())->toBe('anonymous');
    }
});
