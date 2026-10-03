<?php

namespace App\Support\Auth;

use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

final class MutationAuthorizer
{
    private static ?User $systemActor = null;

    public static function authorize(string $ability): void
    {
        $user = auth()->user() ?? self::$systemActor;

        if (! $user) {
            throw new AuthorizationException('Bu işlem için kullanıcı bağlamı gereklidir.');
        }

        Gate::forUser($user)->authorize($ability);
    }

    public static function runAs(?User $user, Closure $callback): mixed
    {
        if (! $user) {
            throw new AuthorizationException('Sistem işlemi için aktör kullanıcı bulunamadı.');
        }

        $previous = self::$systemActor;
        self::$systemActor = $user;

        try {
            return $callback();
        } finally {
            self::$systemActor = $previous;
        }
    }
}
