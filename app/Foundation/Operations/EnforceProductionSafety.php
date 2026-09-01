<?php

namespace App\Foundation\Operations;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnforceProductionSafety
{
    public function __construct(private ProductionSafetyState $safety) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->safety->recoveryMode() || in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        return new JsonResponse([
            'message' => 'System is temporarily read-only while recovery mode is active.',
            'code' => 'recovery_mode',
        ], Response::HTTP_SERVICE_UNAVAILABLE, [
            'Retry-After' => (string) $this->safety->retryAfterSeconds(),
        ]);
    }
}
