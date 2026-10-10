<?php

namespace App\Livewire\Pages\Auth;

use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Login extends Component
{
    use WithIdempotentMutations;

    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function mount(): void
    {
        $this->seedMutationKeys(['authenticate']);
    }

    public function authenticate(): void
    {
        $credentials = $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = Str::lower($credentials['email']).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Çok fazla başarısız giriş. 15 dakika sonra tekrar deneyin.',
            ]);
        }

        $userId = (int) $this->runMasterMutation('authenticate', function () use ($credentials, $key): int {
            if (! Auth::attempt($credentials, $this->remember)) {
                RateLimiter::hit($key, 15 * 60);

                throw ValidationException::withMessages([
                    'email' => 'E-posta veya parola hatalı.',
                ]);
            }

            $user = Auth::user();

            if (! $user->is_active) {
                Auth::logout();

                throw ValidationException::withMessages([
                    'email' => 'Kullanıcı hesabı pasif.',
                ]);
            }

            RateLimiter::clear($key);

            return (int) $user->getAuthIdentifier();
        });

        if (! Auth::check() || (int) Auth::id() !== $userId) {
            Auth::login(User::query()->findOrFail($userId), $this->remember);
        }

        request()->session()->regenerate();

        $this->redirect('/secim', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.pages.auth.login')->layout('layouts.guest', [
            'title' => 'Giriş · '.config('app.name'),
        ]);
    }
}
