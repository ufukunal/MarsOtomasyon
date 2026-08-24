<?php

use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('drives the authenticated V16.3 shell tabs and command palette without browser errors', function (): void {
    $company = Company::query()->create([
        'code' => 'BROWSER-SHELL',
        'name' => 'Browser Shell Company',
    ]);
    $user = User::query()->create([
        'name' => 'Browser Shell User',
        'email' => 'browser-shell@example.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
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

    $page = visit('/login')
        ->fill('email', 'browser-shell@example.test')
        ->fill('password', 'correct-password')
        ->click('Giriş Yap')
        ->assertPathIs('/workspace')
        ->assertSee('Browser Shell Company')
        ->assertSee('Merkez')
        ->assertCount('.workspace-tab', 1)
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    $page->click('[data-command-open]')
        ->assertScript('document.querySelector("[data-command-palette]").open', true)
        ->type('[data-command-search]', 'ayarlar')
        ->assertScript('document.querySelector("[data-command-option][data-command-text=ayarlar]").hidden', false)
        ->assertNoJavaScriptErrors();

    $page->click('[data-command-close]')
        ->click('Ayarlar')
        ->assertPathIs('/settings')
        ->assertCount('.workspace-tab', 2)
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});
