<?php

use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Enums\DocumentType;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\DocumentSequence;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\TaxZeroReason;
use App\Modules\Core\Models\User;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\SalesOrders\Enums\SalesOrderProgressType;
use App\Modules\SalesOrders\Models\SalesOrder;
use App\Modules\SalesOrders\Models\SalesOrderLineProgressEffect;
use App\Modules\SalesOrders\Progress\SalesOrderProgressService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

it('keeps reservation progress tax and readonly order UI aligned through the browser exit gate', function (): void {
    $company = Company::query()->create(['code' => 'BROWSER-M67', 'name' => 'Browser M6.7 Company']);
    $user = User::query()->create([
        'name' => 'Browser M67 Manager', 'email' => 'browser-m67@example.test',
        'password' => 'correct-password', 'status' => UserStatus::Active,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(), 'user_id' => $user->getKey(), 'is_active' => true, 'joined_at' => now(),
    ]);
    $role = Role::query()->create([
        'company_id' => $company->getKey(), 'code' => 'ORDER-MANAGER', 'name' => 'Sipariş Yöneticisi', 'is_active' => true,
    ]);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::SalesOrderView);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::SalesOrderManage);
    app(AssignRoleToMembership::class)->handle($membership, $role);

    $account = Account::query()->create([
        'company_id' => $company->getKey(), 'code' => 'CUST', 'type' => AccountType::Customer, 'status' => AccountStatus::Active,
        'legal_name' => 'Browser M67 Müşterisi', 'trade_name' => null, 'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null, 'tax_office' => null, 'book_currency_code' => 'TRY', 'due_days' => 0,
        'discount_rate' => '0.000000', 'risk_limit' => '0.000000',
    ]);
    $category = Category::query()->create([
        'company_id' => $company->getKey(), 'code' => 'CAT', 'name' => 'Kategori', 'is_active' => true,
    ]);
    $unit = Unit::query()->create([
        'company_id' => $company->getKey(), 'code' => 'ADET', 'name' => 'Adet', 'is_active' => true,
    ]);
    $tax = Tax::query()->create([
        'company_id' => $company->getKey(), 'code' => 'KDV20', 'name' => 'KDV %20', 'rate' => '20.000000', 'is_active' => true,
    ]);
    $reason = TaxZeroReason::query()->create([
        'company_id' => $company->getKey(), 'code' => 'ISTISNA', 'name' => 'İstisna', 'is_active' => true,
    ]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(), 'code' => 'BROWSER-M67-SKU', 'status' => ProductStatus::Active,
        'name' => 'Browser M67 Ürünü', 'category_id' => $category->getKey(), 'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(), 'sale_price_net' => '100.000000', 'purchase_price_net' => '60.000000',
    ]);
    $warehouse = Warehouse::query()->create([
        'company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Merkez Depo', 'is_active' => true,
    ]);
    $location = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(), 'warehouse_id' => $warehouse->getKey(),
        'code' => 'A-01', 'name' => 'A Rafı', 'is_active' => true,
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(), 'document_type' => DocumentType::SalesOrder->value,
        'series_code' => 'default', 'prefix' => 'SO-', 'padding' => 4, 'next_value' => 1, 'is_active' => true,
    ]);
    DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: new SourceEffectIdentity(
            (int) $company->getKey(),
            'sales_order.exit_gate.browser',
            'opening',
            'inventory.opening',
        ),
        productId: (int) $product->getKey(),
        warehouseId: (int) $warehouse->getKey(),
        locationId: (int) $location->getKey(),
        movementType: StockMovementType::OpeningIn,
        quantity: '10',
        unitCost: '10',
    )));

    $page = visit('/login')
        ->fill('email', 'browser-m67@example.test')
        ->fill('password', 'correct-password')
        ->click('Giriş Yap')
        ->assertPathIs('/workspace')
        ->click('Satış')
        ->click('Yeni Sipariş')
        ->assertPathIs('/sales-orders/create')
        ->select('account_id', (string) $account->getKey())
        ->fill('order_date', '2026-08-26')
        ->select('currency_code', 'TRY')
        ->fill('document_discount_rate', '0')
        ->fill('[data-product-search-input]', 'BROWSER-M67-SKU')
        ->click('BROWSER-M67-SKU — Browser M67 Ürünü')
        ->select('[name="lines[0][warehouse_id]"]', (string) $warehouse->getKey())
        ->select('[name="lines[0][location_id]"]', (string) $location->getKey())
        ->fill('[name="lines[0][quantity]"]', '6')
        ->fill('[name="lines[0][unit_price]"]', '100')
        ->click('[name="lines[0][tax_is_zeroed]"]')
        ->select('[name="lines[0][tax_zero_reason_id]"]', (string) $reason->getKey())
        ->click('Kaydet')
        ->assertSee('KDV Sıfırlandı')
        ->assertSee('Neden: ISTISNA')
        ->assertSee('600.000000')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    $order = SalesOrder::query()->where('company_id', $company->getKey())->firstOrFail();
    $line = $order->lines()->firstOrFail();
    $progress = app(SalesOrderProgressService::class);
    DB::transaction(fn (): SalesOrderLineProgressEffect => $progress->record(
        new SourceEffectIdentity((int) $company->getKey(), 'sales_order.exit_gate.browser', 'dispatch-2', 'progress.dispatch'),
        (int) $line->getKey(),
        SalesOrderProgressType::Dispatched,
        '2',
    ));
    DB::transaction(fn (): SalesOrderLineProgressEffect => $progress->record(
        new SourceEffectIdentity((int) $company->getKey(), 'sales_order.exit_gate.browser', 'invoice-1', 'progress.invoice'),
        (int) $line->getKey(),
        SalesOrderProgressType::Invoiced,
        '1',
    ));
    $cancel = DB::transaction(fn (): SalesOrderLineProgressEffect => $progress->record(
        new SourceEffectIdentity((int) $company->getKey(), 'sales_order.exit_gate.browser', 'cancel-1', 'progress.cancel'),
        (int) $line->getKey(),
        SalesOrderProgressType::Cancelled,
        '1',
    ));
    DB::transaction(fn (): SalesOrderLineProgressEffect => $progress->reverse(
        new SourceEffectIdentity((int) $company->getKey(), 'sales_order.exit_gate.browser', 'cancel-reopen', 'progress.cancel_reversal'),
        (int) $cancel->getKey(),
    ));

    $page->click('Liste')
        ->assertPathIs('/sales-orders')
        ->click((string) $order->number)
        ->assertPathIs('/sales-orders/'.$order->getKey())
        ->assertSee('KDV Sıfırlandı')
        ->assertSee('Neden: ISTISNA')
        ->assertSee('6.000000')
        ->assertSee('2.000000')
        ->assertSee('1.000000')
        ->assertSee('4.000000')
        ->assertDontSee('Düzenle')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();
    expect((string) $line->fresh()->progress()->firstOrFail()->remaining_quantity)->toBe('4.000000')
        ->and((string) $balance->quantity)->toBe('10.000000')
        ->and((string) $balance->reserved_quantity)->toBe('6.000000')
        ->and((string) $balance->available_quantity)->toBe('4.000000')
        ->and(StockMovement::query()->where('company_id', $company->getKey())->count())->toBe(1);
});
