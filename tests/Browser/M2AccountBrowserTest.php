<?php

use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('drives the V16.3 account list create readonly detail and profile editor flow without browser errors', function (): void {
    $company = Company::query()->create([
        'code' => 'BROWSER-M2',
        'name' => 'Browser M2 Company',
    ]);
    $user = User::query()->create([
        'name' => 'Browser Account Manager',
        'email' => 'browser-m2@example.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'is_active' => true,
        'joined_at' => now(),
    ]);
    $role = Role::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'ACCOUNT-MANAGER',
        'name' => 'Cari Yöneticisi',
        'is_active' => true,
    ]);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::AccountView);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::AccountManage);
    app(AssignRoleToMembership::class)->handle($membership, $role);
    Branch::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'MAIN',
        'name' => 'Merkez',
        'is_active' => true,
    ]);

    $page = visit('/login')
        ->fill('email', 'browser-m2@example.test')
        ->fill('password', 'correct-password')
        ->click('Giriş Yap')
        ->assertPathIs('/workspace')
        ->assertSee('Cariler')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    $page->click('Cariler')
        ->assertPathIs('/customers')
        ->assertSee('Yeni Cari')
        ->assertNoJavaScriptErrors();

    $page->click('Yeni Cari')
        ->assertPathIs('/customers/create')
        ->fill('code', 'BROWSER-001')
        ->fill('legal_name', 'Browser Cari A.Ş.')
        ->click('Kaydet')
        ->assertSee('Browser Cari A.Ş.')
        ->assertSee('Firma / Ticari')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    $account = Account::query()->where('company_id', $company->getKey())->firstOrFail();
    $page->assertPathIs('/customers/'.$account->getKey())
        ->assertCount('input[name="legal_name"]', 0)
        ->click('İletişim / Adres')
        ->assertPathIs('/customers/'.$account->getKey().'/profile/edit')
        ->assertSee('Firma İletişim Kanalları')
        ->assertSee('Alternatif Firma Ekle')
        ->click('İletişim Ekle')
        ->assertCount('input[name="contacts[0][value]"]', 1)
        ->click('Yetkili Ekle')
        ->assertCount('input[name="authorized_contacts[0][name]"]', 1)
        ->click('Adres Ekle')
        ->assertCount('input[name="addresses[0][line1]"]', 1)
        ->click('Alternatif Firma Ekle')
        ->assertCount('input[name="shipping_preferences[0][company_name]"]', 1)
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});
