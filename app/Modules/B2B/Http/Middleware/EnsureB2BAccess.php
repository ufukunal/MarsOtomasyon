<?php

namespace App\Modules\B2B\Http\Middleware;

use App\Modules\B2B\Models\B2BUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureB2BAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('b2b')->user();

        if (! $user instanceof B2BUser) {
            return redirect()->route('b2b.login');
        }

        if (! $user->canAccessPortal()) {
            Auth::guard('b2b')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('b2b.login')
                ->withErrors(['email' => 'Bayi erişiminiz devre dışı.']);
        }

        return $next($request);
    }
}
