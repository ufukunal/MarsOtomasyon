<?php

namespace App\Livewire\Pages\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Livewire\Component;

class ForgotPassword extends Component
{
    public string $email = '';

    public ?string $status = null;

    public function send(): void
    {
        $this->validate(['email' => ['required', 'email']]);

        Password::sendResetLink(['email' => $this->email]);

        $this->status = 'Parola sıfırlama bağlantısı, hesap mevcutsa e-posta adresine gönderildi.';
    }

    public function render(): View
    {
        return view('livewire.pages.auth.forgot-password')->layout('layouts.guest');
    }
}
