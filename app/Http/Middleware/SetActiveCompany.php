<?php

namespace App\Http\Middleware;

use App\Support\Company\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetActiveCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        $companyId = session('active_company_id') ?? $user->last_company_id;

        if (! $companyId) {
            return $next($request);
        }

        $allowed = DB::connection('master')
            ->table('company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $user->getAuthIdentifier())
            ->exists();

        abort_unless($allowed, 403);

        CompanyContext::use((int) $companyId);

        if (session('active_company_id') === null) {
            session(['active_company_id' => (int) $companyId]);
        }

        try {
            return $next($request);
        } finally {
            CompanyContext::clear();
        }
    }
}
