<?php

namespace App\Modules\Communication\Http\Middleware;

use App\Modules\Communication\ScannerAgentService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateScannerAgent
{
    public function __construct(private readonly ScannerAgentService $agents) {}

    public function handle(Request $request, Closure $next): Response
    {
        $agent = $this->agents->authenticate($request->bearerToken());
        if ($agent === null) {
            return new JsonResponse(['error' => ['code' => 'SCANNER_AUTHENTICATION_REQUIRED', 'message' => 'Valid scanner agent token required.']], 401);
        }
        $request->attributes->set('scanner_agent', $agent);

        return $next($request);
    }
}
