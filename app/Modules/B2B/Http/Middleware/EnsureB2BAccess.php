<?php

namespace App\Modules\B2B\Http\Middleware;

use App\Modules\B2B\Models\B2BUser;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureB2BAccess
{
    public function __construct(private ActiveCompanyContext $companyContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('b2b')->user();
        if (! $user instanceof B2BUser) {
            return redirect()->route('b2b.login');
        }

        if (! $user->canAccessPortal()) {
            return $this->revoke($request, 'Bayi erişiminiz devre dışı.');
        }

        $sessionVersion = $request->session()->get('b2b_auth_version');
        if ($sessionVersion === null) {
            $request->session()->put('b2b_auth_version', (int) $user->auth_version);
        } elseif ((int) $sessionVersion !== (int) $user->auth_version) {
            return $this->revoke($request, 'Bayi oturumunuz güvenlik değişikliği nedeniyle yenilenmeli.');
        }

        $company = $user->company()->first();
        if (! $company instanceof Company || (int) $company->getKey() !== (int) $user->company_id) {
            abort(403);
        }
        $this->companyContext->set($company);

        try {
            return $next($request);
        } finally {
            $this->companyContext->clear();
        }
    }

    private function revoke(Request $request, string $message): Response
    {
        Auth::guard('b2b')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('b2b.login')->withErrors(['email' => $message]);
    }
}
