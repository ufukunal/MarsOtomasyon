<?php

use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('shows the compact update center only to a platform administrator', function (): void {
    $company = Company::query()->create([
        'code' => 'BROWSER-UPDATES',
        'name' => 'Browser Update Company',
    ]);
    $user = User::query()->create([
        'name' => 'Platform Admin',
        'email' => 'platform-admin@example.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
        'is_platform_admin' => true,
    ]);
    CompanyMembership::query()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'is_active' => true,
        'joined_at' => now(),
    ]);
    Branch::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'MAIN',
        'name' => 'Merkez',
        'is_active' => true,
    ]);

    visit('/login')
        ->fill('email', 'platform-admin@example.test')
        ->fill('password', 'correct-password')
        ->click('Giriş Yap')
        ->assertPathIs('/workspace')
        ->click('[data-nav-group]:has(a[href$="/operations/updates"]) > summary')
        ->assertSee('Güncelleme Merkezi')
        ->click('[data-nav-group] a[href$="/operations/updates"]')
        ->assertPathIs('/operations/updates')
        ->assertSee('Sistem ve Güven Durumu')
        ->assertSee('Backup Readiness')
        ->assertSee('Güncelleme Geçmişi')
        ->assertCount('.app-sidebar', 1)
        ->assertCount('.workspace-tabs', 1)
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

it('does not expose update center navigation to a normal user', function (): void {
    $company = Company::query()->create(['code' => 'BROWSER-NORMAL', 'name' => 'Browser Normal Company']);
    $user = User::query()->create([
        'name' => 'Normal User',
        'email' => 'normal-user@example.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
        'is_platform_admin' => false,
    ]);
    CompanyMembership::query()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'is_active' => true,
        'joined_at' => now(),
    ]);

    visit('/login')
        ->fill('email', 'normal-user@example.test')
        ->fill('password', 'correct-password')
        ->click('Giriş Yap')
        ->assertPathIs('/workspace')
        ->assertDontSee('Güncelleme Merkezi')
        ->assertNoJavaScriptErrors();

    $this->actingAs($user)->get('/operations/updates')->assertForbidden();
});
