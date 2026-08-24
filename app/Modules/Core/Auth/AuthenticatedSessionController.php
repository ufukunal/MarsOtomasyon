<?php

namespace App\Modules\Core\Auth;

use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AuthenticatedSessionController
{
    private const int MAX_ATTEMPTS = 5;

    private const int DECAY_SECONDS = 60;

    private const string DUMMY_PASSWORD_HASH = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:4096'],
        ]);

        $email = mb_strtolower(trim((string) $validated['email']));
        $password = (string) $validated['password'];
        $throttleKey = $this->throttleKey($email, $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => 'Çok fazla giriş denemesi. Kısa süre sonra tekrar deneyin.',
            ]);
        }

        $user = User::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->first();

        if (! $user instanceof User) {
            Hash::check($password, self::DUMMY_PASSWORD_HASH);
            $this->rejectLogin($throttleKey);
        }

        $passwordMatches = Hash::check($password, $user->getAuthPassword());
        $rawStatus = $user->getRawOriginal('status');
        $isActive = is_string($rawStatus)
            && UserStatus::tryFrom($rawStatus) === UserStatus::Active;

        if (! $passwordMatches || ! $isActive) {
            $this->rejectLogin($throttleKey);
        }

        RateLimiter::clear($throttleKey);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        return redirect()->intended(route('home', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function throttleKey(string $email, ?string $ipAddress): string
    {
        return 'login:'.hash('sha256', $email.'|'.($ipAddress ?? 'unknown'));
    }

    private function rejectLogin(string $throttleKey): never
    {
        RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

        throw ValidationException::withMessages([
            'email' => 'Giriş bilgileri geçersiz.',
        ]);
    }
}
