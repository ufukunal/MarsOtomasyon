<?php

namespace App\Foundation\Correlation;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

final readonly class CorrelationIdMiddleware
{
    public function __construct(
        private CorrelationIdFactory $factory,
        private CorrelationContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) config('mars.correlation.header', 'X-Correlation-ID');
        $id = $this->factory->resolve($request->headers->get($header));

        $this->context->set($id);
        Context::add('correlation_id', $id);
        $request->attributes->set('correlation_id', $id);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set($header, $id);

        return $response;
    }
}
