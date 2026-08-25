<?php

use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\User;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Barcode;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

it('runs barcode quick count and finalizes variance without browser errors', function (): void {
    $company = Company::query()->create(['code' => 'BROWSER-M47', 'name' => 'Browser M4.7 Company']);
    $user = User::query()->create([
        'name' => 'Browser Count Manager',
        'email' => 'browser-m47@example.test',
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
        'code' => 'COUNT-MANAGER',
        'name' => 'Sayım Yöneticisi',
        'is_active' => true,
    ]);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::ProductView);
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
        'code' => 'BROWSER-M47-SKU',
        'status' => ProductStatus::Active,
        'name' => 'Browser Sayım Avize',
        'category_id' => $category->getKey(),
        'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(),
        'sale_price_net' => '1000.000000',
        'purchase_price_net' => '600.000000',
    ]);
    Barcode::query()->create([
        'company_id' => $company->getKey(),
        'product_id' => $product->getKey(),
        'barcode' => '8690000000477',
        'is_primary' => true,
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
    DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: new SourceEffectIdentity(
            companyId: (int) $company->getKey(),
            sourceType: 'inventory.browser-count',
            sourceId: 'opening',
            effectType: 'inventory.opening',
        ),
        productId: (int) $product->getKey(),
        warehouseId: (int) $warehouse->getKey(),
        locationId: (int) $location->getKey(),
        movementType: StockMovementType::OpeningIn,
        quantity: '4',
        unitCost: '125',
    )));

    visit('/login')
        ->fill('email', 'browser-m47@example.test')
        ->fill('password', 'correct-password')
        ->click('Giriş Yap')
        ->assertPathIs('/workspace')
        ->click('Ürün/Stok')
        ->assertPathIs('/inventory')
        ->click('Stok Bakiyeleri')
        ->assertPathIs('/inventory/stock')
        ->click('Stok Sayımı')
        ->assertPathIs('/inventory/stock/counts')
        ->click('Yeni Sayım')
        ->assertPathIs('/inventory/stock/counts/create')
        ->select('location_id', (string) $location->getKey())
        ->click('Sayımı Başlat')
        ->assertSee('Browser Sayım Avize')
        ->assertSee('4.000000')
        ->assertSee('Hızlı Sayım')
        ->fill('barcode', '8690000000477')
        ->fill('quantity', '1')
        ->click('Barkodu Say')
        ->assertSee('1.000000')
        ->assertSee('-3.000000')
        ->select('product_id', (string) $product->getKey())
        ->fill('counted_quantity', '3')
        ->click('Satırı Güncelle')
        ->assertSee('3.000000')
        ->assertSee('-1.000000')
        ->click('Farkları İşle ve Sayımı Tamamla')
        ->assertSee('Tamamlandı')
        ->assertSee('Finalize:')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs()
        ->click('Bakiyeler')
        ->assertPathIs('/inventory/stock')
        ->assertSee('Browser Sayım Avize')
        ->assertSee('3.000000')
        ->assertSee('375.000000')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});
