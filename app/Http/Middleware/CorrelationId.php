<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = strtoupper(bin2hex(random_bytes(3)));

        $request->attributes->set('correlation_id', $correlationId);

        Log::withContext([
            'correlation_id' => $correlationId,
        ]);

        $response = $next($request);
        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }
}
