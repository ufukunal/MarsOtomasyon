<?php

namespace App\Livewire\Pages\Auth;

use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Component;

class ResetPassword extends Component
{
    use WithIdempotentMutations;

    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->seedMutationKeys(['resetPassword']);
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    public function resetPassword(): void
    {
        $data = $this->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:10', 'confirmed', 'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/'],
        ]);

        $status = $this->runMasterMutation(
            'resetPassword',
            fn (): string => Password::reset(
                $data,
                function (User $user, string $password): void {
                    $user->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => Str::random(60),
                    ])->save();

                    event(new PasswordReset($user));
                },
            ),
        );

        if ($status === Password::PASSWORD_RESET) {
            session()->flash('status', 'Parolanız güncellendi.');
            $this->redirectRoute('login', navigate: false);

            return;
        }

        $this->addError('email', __($status));
    }

    public function render(): View
    {
        return view('livewire.pages.auth.reset-password')->layout('layouts.guest');
    }
}
