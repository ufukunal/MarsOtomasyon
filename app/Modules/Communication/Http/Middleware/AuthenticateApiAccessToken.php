<?php

namespace App\Modules\Communication\Http\Middleware;

use App\Modules\Communication\ApiAccessTokenService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateApiAccessToken
{
    public function __construct(private readonly ApiAccessTokenService $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->tokens->authenticate($request->bearerToken());
        if ($token === null) {
            return new JsonResponse(['error' => ['code' => 'AUTHENTICATION_REQUIRED', 'message' => 'Valid API bearer token required.']], 401);
        }
        $request->attributes->set('api_access_token', $token);
        $request->attributes->set('company_id', $token['company_id']);

        return $next($request);
    }
}
