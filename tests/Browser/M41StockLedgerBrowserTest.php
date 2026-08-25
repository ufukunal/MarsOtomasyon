<?php

use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\User;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('posts opening stock through the V16.3 stock workspace without browser errors', function (): void {
    $company = Company::query()->create(['code' => 'BROWSER-M41', 'name' => 'Browser M4.1 Company']);
    $user = User::query()->create([
        'name' => 'Browser Stock Manager',
        'email' => 'browser-m41@example.test',
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
        'code' => 'STOCK-MANAGER',
        'name' => 'Stok Yöneticisi',
        'is_active' => true,
    ]);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::ProductView);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::ProductManage);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::InventoryView);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::InventoryManage);
    app(AssignRoleToMembership::class)->handle($membership, $role);

    $category = Category::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'AVIZE',
        'name' => 'Avize',
        'is_active' => true,
    ]);
    $unit = Unit::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'ADET',
        'name' => 'Adet',
        'is_active' => true,
    ]);
    $tax = Tax::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'KDV20',
        'name' => 'KDV %20',
        'rate' => '20.000000',
        'is_active' => true,
    ]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'BROWSER-M41-SKU',
        'status' => ProductStatus::Active,
        'name' => 'Browser Stok Avize',
        'category_id' => $category->getKey(),
        'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(),
        'sale_price_net' => '1000.000000',
        'purchase_price_net' => '600.000000',
    ]);
    $warehouse = Warehouse::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'MAIN',
        'name' => 'Merkez Depo',
        'is_active' => true,
    ]);
    $location = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(),
        'warehouse_id' => $warehouse->getKey(),
        'code' => 'A-01',
        'name' => 'A Rafı',
        'is_active' => true,
    ]);

    $page = visit('/login')
        ->fill('email', 'browser-m41@example.test')
        ->fill('password', 'correct-password')
        ->click('Giriş Yap')
        ->assertPathIs('/workspace')
        ->click('Ürün/Stok')
        ->assertPathIs('/inventory')
        ->click('Stok Bakiyeleri')
        ->assertPathIs('/inventory/stock')
        ->assertSee('Pozitif fiziksel stok bakiyesi bulunmuyor.')
        ->click('Yeni Stok Hareketi')
        ->assertPathIs('/inventory/stock/movements/create')
        ->select('product_id', (string) $product->getKey())
        ->select('location_id', (string) $location->getKey())
        ->select('movement_type', 'opening_in')
        ->fill('quantity', '4')
        ->fill('unit_cost', '125')
        ->fill('note', 'Browser açılış')
        ->click('Stok Hareketini İşle')
        ->assertPathIs('/inventory/stock/movements')
        ->assertSee('BROWSER-M41-SKU')
        ->assertSee('4.000000')
        ->assertSee('125.000000')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs()
        ->click('Bakiyeler')
        ->assertPathIs('/inventory/stock')
        ->assertSee('Browser Stok Avize')
        ->assertSee('500.000000')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});
