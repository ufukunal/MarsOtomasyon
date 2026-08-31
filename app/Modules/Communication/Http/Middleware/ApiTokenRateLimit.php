<?php

namespace App\Modules\Communication\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

final class ApiTokenRateLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->attributes->get('api_access_token');
        $tokenId = is_array($token) ? (int) ($token['id'] ?? 0) : 0;
        $key = 'm20:api:'.$tokenId;
        $max = max(1, (int) config('m20.api.rate_limit_per_minute', 120));
        if (RateLimiter::tooManyAttempts($key, $max)) {
            return new JsonResponse(['error' => ['code' => 'RATE_LIMITED', 'message' => 'API rate limit exceeded.']], 429, ['Retry-After' => (string) RateLimiter::availableIn($key)]);
        }
        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
