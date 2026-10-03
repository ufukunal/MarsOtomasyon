<?php

namespace App\Http\Middleware;

use App\Models\Period;
use App\Support\Period\PeriodContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetActivePeriod
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        if ($request->is('period/select', 'logout')) {
            return $next($request);
        }

        $user = Auth::user();

        $companyId = session('active_company_id') ?? $user->last_company_id;
        $periodId = session('active_period_id') ?? $user->last_period_id;

        if (! $companyId) {
            return redirect('/period/select');
        }

        if (! $periodId) {
            $periodId = Period::query()
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->orderByDesc('year')
                ->value('id');
        }

        if (! $periodId) {
            return redirect('/period/select');
        }

        $hasCompanyAccess = DB::connection('master')
            ->table('company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $user->getAuthIdentifier())
            ->exists();

        abort_unless($hasCompanyAccess, 403);

        $hasPeriodAccess = DB::connection('master')
            ->table('period_user_access')
            ->where('period_id', $periodId)
            ->where('user_id', $user->getAuthIdentifier())
            ->where('is_active', true)
            ->exists();

        abort_unless($hasPeriodAccess, 403);

        $period = Period::query()
            ->whereKey($periodId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        if ($period->status === 'archived') {
            return redirect('/period/select')
                ->with('warning', 'Arşivlenmiş dönem önce geri yüklenmelidir.');
        }

        PeriodContext::use((int) $companyId, (int) $periodId);

        return $next($request);
    }
}
