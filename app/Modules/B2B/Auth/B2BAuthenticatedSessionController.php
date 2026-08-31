<?php

namespace App\Modules\B2B\Auth;

use App\Modules\B2B\Models\B2BUser;
use App\Modules\Core\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class B2BAuthenticatedSessionController
{
    private const int MAX_ATTEMPTS = 5;

    private const int DECAY_SECONDS = 60;

    private const string DUMMY_PASSWORD_HASH = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

    public function create(): View|RedirectResponse
    {
        $current = Auth::guard('b2b')->user();
        if ($current instanceof B2BUser && $current->canAccessPortal()) {
            return redirect()->route('b2b.home');
        }
        if ($current instanceof B2BUser) {
            Auth::guard('b2b')->logout();
        }

        return view('b2b.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $current = Auth::guard('b2b')->user();
        if ($current instanceof B2BUser && $current->canAccessPortal()) {
            return redirect()->route('b2b.home');
        }
        if ($current instanceof B2BUser) {
            Auth::guard('b2b')->logout();
        }

        $validated = $request->validate([
            'company_code' => ['required', 'string', 'max:32'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:4096'],
        ]);

        $companyCode = mb_strtolower(trim((string) $validated['company_code']));
        $email = mb_strtolower(trim((string) $validated['email']));
        $password = (string) $validated['password'];
        $throttleKey = $this->throttleKey($companyCode, $email, $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages(['email' => 'Çok fazla giriş denemesi. Kısa süre sonra tekrar deneyin.']);
        }

        $company = Company::query()->whereRaw('lower(code) = ?', [$companyCode])->first();
        $user = $company instanceof Company
            ? B2BUser::query()->where('company_id', $company->getKey())->whereRaw('lower(email) = ?', [$email])->first()
            : null;

        if (! $user instanceof B2BUser) {
            Hash::check($password, self::DUMMY_PASSWORD_HASH);
            $this->rejectLogin($throttleKey);
        }

        if (! Hash::check($password, $user->getAuthPassword()) || ! $user->canAccessPortal()) {
            $this->rejectLogin($throttleKey);
        }

        RateLimiter::clear($throttleKey);
        Auth::guard('b2b')->login($user);
        $request->session()->regenerate();
        $request->session()->put('b2b_auth_version', (int) $user->auth_version);

        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('b2b.home', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('b2b')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('b2b.login');
    }

    private function throttleKey(string $companyCode, string $email, ?string $ipAddress): string
    {
        return 'b2b-login:'.hash('sha256', $companyCode.'|'.$email.'|'.($ipAddress ?? 'unknown'));
    }

    private function rejectLogin(string $throttleKey): never
    {
        RateLimiter::hit($throttleKey, self::DECAY_SECONDS);
        throw ValidationException::withMessages(['email' => 'Giriş bilgileri geçersiz.']);
    }
}
