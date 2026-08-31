<?php

namespace App\Modules\Communication\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireApiPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $token = $request->attributes->get('api_access_token');
        $permissions = is_array($token) && isset($token['permissions']) && is_array($token['permissions']) ? $token['permissions'] : [];
        if (! in_array($permission, $permissions, true)) {
            return new JsonResponse(['error' => ['code' => 'PERMISSION_DENIED', 'message' => 'API token lacks required permission.']], 403);
        }

        return $next($request);
    }
}
