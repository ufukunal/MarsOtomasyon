<?php

namespace App\Http\Middleware;

use App\Support\Company\CompanyDatabaseContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * DOC2 request middleware, deliberately not registered until the entire app
 * has migrated away from SetActivePeriod and period-scoped authorizers.
 */
final class SetActiveCompanyDatabase
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('mars_doc2.mode') !== 'doc2' || ! Auth::check()) {
            return $next($request);
        }

        if ($request->routeIs('company.select', 'logout', 'setup')) {
            return $next($request);
        }

        $user = Auth::user();
        $companyId = session('active_company_id') ?? $user?->last_company_id;

        if (! $companyId) {
            return redirect()->route('company.select');
        }

        try {
            CompanyDatabaseContext::activateForUser((int) $companyId, (int) $user->getAuthIdentifier());

            return $next($request);
        } finally {
            CompanyDatabaseContext::release();
        }
    }
}
