<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Auth\CompanyPermissionContext;
use App\Support\Company\CompanyDatabaseContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        abort_unless($user instanceof User && $user->is_active, 403);

        $companyId = session('active_company_id') ?? $user->last_company_id;

        if (! $companyId) {
            return redirect()->route('company.select');
        }

        $access = DB::connection('master')->table('company_user')
            ->where('company_id', (int) $companyId)
            ->where('user_id', (int) $user->getAuthIdentifier())
            ->where('is_active', true)
            ->first(['permission_overrides']);

        abort_unless($access !== null, 403);

        $overrides = is_string($access->permission_overrides)
            ? json_decode($access->permission_overrides, true)
            : $access->permission_overrides;

        try {
            CompanyDatabaseContext::activateForUser((int) $companyId, (int) $user->getAuthIdentifier());
            CompanyPermissionContext::use(is_array($overrides) ? $overrides : []);

            return $next($request);
        } finally {
            CompanyPermissionContext::clear();
            CompanyDatabaseContext::release();
        }
    }
}
