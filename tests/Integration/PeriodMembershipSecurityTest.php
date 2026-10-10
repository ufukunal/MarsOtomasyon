<?php

use App\Models\User;
use App\Support\Period\PeriodContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

it('rejects another company and inactive period access despite valid login', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId, int $periodId): void {
        $db = DB::connection('master');
        $userId = $db->table('users')->insertGetId([
            'name' => 'V4 test actor', 'email' => 'v4.'.strtolower(\Illuminate\Support\Str::random(9)).'@invalid.test',
            'password' => \Illuminate\Support\Facades\Hash::make('test-only'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $db->table('company_user')->insert([
            'company_id' => $companyId, 'user_id' => $userId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $db->table('period_user_access')->insert([
            'period_id' => $periodId, 'user_id' => $userId, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Auth::login(User::query()->findOrFail($userId));

        $verify = new ReflectionMethod(PeriodContext::class, 'assertAuthenticatedUserAccess');
        try {
            $verify->invoke(null, $companyId, $periodId);
            expect(fn () => $verify->invoke(null, $companyId + 100000, $periodId))
                ->toThrow(AuthorizationException::class);

            $db->table('period_user_access')->where('period_id', $periodId)->where('user_id', $userId)
                ->update(['is_active' => false]);
            expect(fn () => $verify->invoke(null, $companyId, $periodId))
                ->toThrow(AuthorizationException::class);
        } finally {
            Auth::logout();
        }
    });
});
