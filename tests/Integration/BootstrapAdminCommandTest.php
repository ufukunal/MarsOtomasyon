<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

it('bootstraps the first admin company branch membership and permissions idempotently', function (): void {
    $email = 'first-admin@example.test';
    $password = 'Mars-Bootstrap-Secret-2026';

    $this->artisan('mars:bootstrap-admin', [
        'email' => $email,
        '--name' => 'First Admin',
        '--company-code' => 'MARS',
        '--company-name' => 'Mars Test',
        '--branch-code' => 'MERKEZ',
        '--branch-name' => 'Merkez',
    ])
        ->expectsQuestion('Administrator password (minimum 12 characters)', $password)
        ->expectsQuestion('Confirm administrator password', $password)
        ->assertSuccessful();

    $user = DB::table('users')->where('email', $email)->first();
    expect($user)->not->toBeNull()
        ->and((bool) $user->is_platform_admin)->toBeTrue()
        ->and(DB::table('companies')->where('code', 'MARS')->count())->toBe(1)
        ->and(DB::table('branches')->where('code', 'MERKEZ')->count())->toBe(1)
        ->and(DB::table('company_memberships')->where('user_id', $user->id)->count())->toBe(1)
        ->and(DB::table('roles')->where('code', 'platform_admin')->count())->toBe(1);

    $permissionCount = DB::table('permissions')->count();
    $roleId = (int) DB::table('roles')->where('code', 'platform_admin')->value('id');
    expect(DB::table('role_permissions')->where('role_id', $roleId)->count())->toBe($permissionCount);

    $this->artisan('mars:bootstrap-admin', [
        'email' => $email,
        '--company-code' => 'MARS',
        '--company-name' => 'Mars Test',
        '--branch-code' => 'MERKEZ',
        '--branch-name' => 'Merkez',
    ])->assertSuccessful();

    expect(DB::table('users')->where('email', $email)->count())->toBe(1)
        ->and(DB::table('companies')->where('code', 'MARS')->count())->toBe(1)
        ->and(DB::table('branches')->where('code', 'MERKEZ')->count())->toBe(1)
        ->and(DB::table('company_memberships')->where('user_id', $user->id)->count())->toBe(1)
        ->and(DB::table('role_permissions')->where('role_id', $roleId)->count())->toBe($permissionCount);
});

it('refuses to create a second bootstrap identity once users exist', function (): void {
    DB::table('users')->insert([
        'name' => 'Existing',
        'email' => 'existing@example.test',
        'password' => password_hash('irrelevant-password', PASSWORD_BCRYPT),
        'status' => 'active',
        'is_platform_admin' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('mars:bootstrap-admin', ['email' => 'second@example.test'])
        ->assertFailed();

    expect(DB::table('users')->where('email', 'second@example.test')->exists())->toBeFalse();
});
