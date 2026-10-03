<?php

namespace App\Livewire\Pages\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

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

        if (! Auth::attempt($credentials, $this->remember)) {
            RateLimiter::hit($key, 15 * 60);

            throw ValidationException::withMessages([
                'email' => 'E-posta veya parola hatalı.',
            ]);
        }

        if (! Auth::user()->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Kullanıcı hesabı pasif.',
            ]);
        }

        RateLimiter::clear($key);
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
