<?php

namespace App\Modules\B2B\Auth;

use App\Modules\B2B\Models\B2BUser;
use App\Modules\Core\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class B2BPasswordResetController
{
    public function requestForm(): View
    {
        return view('b2b.auth.forgot-password');
    }

    public function requestLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_code' => ['required', 'string', 'max:32'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
        ]);
        $companyCode = mb_strtolower(trim((string) $validated['company_code']));
        $email = mb_strtolower(trim((string) $validated['email']));
        $key = 'b2b-reset:'.hash('sha256', $companyCode.'|'.$email.'|'.($request->ip() ?? 'unknown'));

        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages(['email' => 'Çok fazla talep. Daha sonra tekrar deneyin.']);
        }
        RateLimiter::hit($key, 300);

        $company = Company::query()->whereRaw('lower(code) = ?', [$companyCode])->first();
        $user = $company instanceof Company
            ? B2BUser::query()->where('company_id', $company->getKey())->whereRaw('lower(email) = ?', [$email])->first()
            : null;

        if ($user instanceof B2BUser && $user->canAccessPortal()) {
            $plainToken = Str::random(64);
            DB::table('b2b_password_reset_tokens')->where('company_id', $company->getKey())->where('b2b_user_id', $user->getKey())->delete();
            DB::table('b2b_password_reset_tokens')->insert([
                'company_id' => $company->getKey(),
                'b2b_user_id' => $user->getKey(),
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addMinutes(30),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $url = route('b2b.password.reset', [
                'companyCode' => (string) $company->code,
                'user' => (string) $user->public_id,
                'token' => $plainToken,
            ]);
            Mail::raw("Bayi portalı şifre yenileme bağlantınız (30 dakika geçerli):\n\n{$url}", function ($message) use ($user): void {
                $message->to((string) $user->email)->subject('Mars B2B Şifre Yenileme');
            });
        }

        return back()->with('status', 'Bilgiler eşleşiyorsa şifre yenileme bağlantısı gönderildi.');
    }

    public function resetForm(string $companyCode, string $user, string $token): View
    {
        return view('b2b.auth.reset-password', compact('companyCode', 'user', 'token'));
    }

    public function reset(Request $request, string $companyCode, string $user, string $token): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
        ]);
        $company = Company::query()->whereRaw('lower(code) = ?', [mb_strtolower(trim($companyCode))])->firstOrFail();
        $b2bUser = B2BUser::query()
            ->where('company_id', $company->getKey())
            ->where('public_id', mb_strtoupper(trim($user)))
            ->firstOrFail();
        $tokenHash = hash('sha256', $token);

        DB::transaction(function () use ($company, $b2bUser, $tokenHash, $validated): void {
            $reset = DB::table('b2b_password_reset_tokens')
                ->where('company_id', $company->getKey())
                ->where('b2b_user_id', $b2bUser->getKey())
                ->where('token_hash', $tokenHash)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();
            if ($reset === null) {
                throw ValidationException::withMessages(['password' => 'Şifre yenileme bağlantısı geçersiz veya süresi dolmuş.']);
            }

            $b2bUser->forceFill([
                'password' => (string) $validated['password'],
                'auth_version' => ((int) $b2bUser->auth_version) + 1,
                'password_changed_at' => now(),
            ])->save();
            DB::table('b2b_password_reset_tokens')->where('id', $reset->id)->update(['used_at' => now(), 'updated_at' => now()]);
        });

        return redirect()->route('b2b.login')->with('status', 'Şifreniz yenilendi. Yeniden giriş yapabilirsiniz.');
    }
}
