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
        $keyId = is_array($token) ? (string) ($token['key_id'] ?? '') : '';
        $key = 'm20:api:'.$keyId;
        $max = max(1, (int) config('m20.api.rate_limit_per_minute', 120));
        if (RateLimiter::tooManyAttempts($key, $max)) {
            return new JsonResponse(['error' => ['code' => 'RATE_LIMITED', 'message' => 'API rate limit exceeded.']], 429, ['Retry-After' => (string) RateLimiter::availableIn($key)]);
        }
        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
