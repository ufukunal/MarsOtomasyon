<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class AuthorizedPeriod
{
    /**
     * Provision a disposable, test-only actor inside the caller's
     * IsolatedPostgres::withActivePeriod() rollback transaction.
     * Never call this helper outside an isolated test context.
     */
    public static function login(): User
    {
        IsolatedPostgres::approved();

        $user = User::query()->create([
            'name' => 'V4 transaction test actor',
            'email' => 'v4-'.strtolower(Str::random(16)).'@invalid.test',
            'password' => 'disposable-only',
            'is_active' => true,
        ]);

        Auth::login($user);
        Gate::before(fn (User $actor): ?bool => (int) $actor->id === (int) $user->id ? true : null);

        return $user;
    }

    public static function logout(): void
    {
        Auth::logout();
    }
}
