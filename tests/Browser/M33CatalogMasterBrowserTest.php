<?php

use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Unit;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('drives category and unit create edit and deactivate flows without browser errors', function (): void {
    $company = Company::query()->create([
        'code' => 'BROWSER-M33',
        'name' => 'Browser M3.3 Company',
    ]);
    $user = User::query()->create([
        'name' => 'Browser Catalog Manager',
        'email' => 'browser-m33@example.test',
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
        'code' => 'CATALOG-MANAGER',
        'name' => 'Katalog Yöneticisi',
        'is_active' => true,
    ]);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::ProductView);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::ProductManage);
    app(AssignRoleToMembership::class)->handle($membership, $role);
    Branch::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'MAIN',
        'name' => 'Merkez',
        'is_active' => true,
    ]);

    $page = visit('/login')
        ->fill('email', 'browser-m33@example.test')
        ->fill('password', 'correct-password')
        ->click('Giriş Yap')
        ->assertPathIs('/workspace')
        ->click('Ürün/Stok')
        ->assertPathIs('/inventory')
        ->assertSee('Kategoriler')
        ->assertSee('Birimler')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    $page->click('Kategoriler')
        ->assertPathIs('/inventory/categories')
        ->click('Yeni Kategori')
        ->assertPathIs('/inventory/categories/create')
        ->fill('code', 'browser-cat')
        ->fill('name', 'Browser Kategori')
        ->select('status', 'active')
        ->click('Kaydet')
        ->assertSee('Kategori oluşturuldu.')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    $category = Category::query()->where('company_id', $company->getKey())->where('code', 'BROWSER-CAT')->firstOrFail();
    expect($category->name)->toBe('Browser Kategori')
        ->and($category->is_active)->toBeTrue();

    $page->assertPathIs('/inventory/categories/'.$category->getKey().'/edit')
        ->fill('name', 'Browser Kategori Güncel')
        ->select('status', 'inactive')
        ->click('Kaydet')
        ->assertPathIs('/inventory/categories/'.$category->getKey().'/edit')
        ->assertSee('Kategori güncellendi.')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    $category->refresh();
    expect($category->name)->toBe('Browser Kategori Güncel')
        ->and($category->is_active)->toBeFalse();

    $page->click('Listeye Dön')
        ->assertPathIs('/inventory/categories')
        ->assertSee('BROWSER-CAT')
        ->assertSee('Browser Kategori Güncel')
        ->assertSee('Pasif')
        ->click('Birimler')
        ->assertPathIs('/inventory/units')
        ->click('Yeni Birim')
        ->assertPathIs('/inventory/units/create')
        ->fill('code', 'browser-unit')
        ->fill('name', 'Browser Birim')
        ->select('status', 'active')
        ->click('Kaydet')
        ->assertSee('Birim oluşturuldu.')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    $unit = Unit::query()->where('company_id', $company->getKey())->where('code', 'BROWSER-UNIT')->firstOrFail();
    expect($unit->name)->toBe('Browser Birim')
        ->and($unit->is_active)->toBeTrue();

    $page->assertPathIs('/inventory/units/'.$unit->getKey().'/edit')
        ->fill('name', 'Browser Birim Güncel')
        ->select('status', 'inactive')
        ->click('Kaydet')
        ->assertPathIs('/inventory/units/'.$unit->getKey().'/edit')
        ->assertSee('Birim güncellendi.')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    $unit->refresh();
    expect($unit->name)->toBe('Browser Birim Güncel')
        ->and($unit->is_active)->toBeFalse();

    $page->click('Listeye Dön')
        ->assertPathIs('/inventory/units')
        ->assertSee('BROWSER-UNIT')
        ->assertSee('Browser Birim Güncel')
        ->assertSee('Pasif')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});
