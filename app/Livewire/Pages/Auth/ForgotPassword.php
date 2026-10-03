<?php

namespace App\Livewire\Pages\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ForgotPassword extends Component
{
    public string $email = '';

    public ?string $status = null;

    public function send(): void
    {
        $this->validate(['email' => ['required', 'email']]);

        $key = 'password-reset|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages([
                'email' => 'Çok fazla parola sıfırlama isteği. Daha sonra tekrar deneyin.',
            ]);
        }

        RateLimiter::hit($key, 3600);

        Password::sendResetLink(['email' => $this->email]);

        $this->status = 'Parola sıfırlama bağlantısı, hesap mevcutsa e-posta adresine gönderildi.';
    }

    public function render(): View
    {
        return view('livewire.pages.auth.forgot-password')->layout('layouts.guest');
    }
}
