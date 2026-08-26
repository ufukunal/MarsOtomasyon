<?php

use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Accounts\Enums\AccountAddressType;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountAddress;
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
use App\Modules\Core\Models\User;
use App\Modules\Dispatches\Models\Dispatch;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\SalesOrders\Models\SalesOrder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('inherits reserved order allocation rejects override and locks the source order after dispatch lineage', function (): void {
    [$company, $account, $product, $address, $warehouse, $location] = dispatch71AllocatedFixture('DSP71-INHERIT');
    $alternateWarehouse = Warehouse::query()->create([
        'company_id' => $company->getKey(), 'code' => 'ALT', 'name' => 'Alternatif Depo', 'is_active' => true,
    ]);
    $alternateLocation = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(), 'warehouse_id' => $alternateWarehouse->getKey(),
        'code' => 'ALT-01', 'name' => 'Alternatif Konum', 'is_active' => true,
    ]);
    $manager = dispatch71AllocatedActor($company);
    dispatch71Opening($company, $product, $warehouse, $location, '5');

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])->post('/sales-orders', [
        'series_code' => 'default', 'account_id' => $account->getKey(), 'order_date' => '2026-08-26',
        'currency_code' => 'TRY', 'document_discount_rate' => '0', 'note' => null,
        'lines' => [[
            'product_id' => $product->getKey(), 'warehouse_id' => $warehouse->getKey(),
            'location_id' => $location->getKey(), 'description' => 'Allocated ürün', 'quantity' => '2',
            'unit_price' => '100', 'price_basis' => 'net', 'line_discount_rate' => '0',
            'tax_zero_reason_id' => null,
        ]],
    ])->assertRedirect();

    $order = SalesOrder::query()->where('company_id', $company->getKey())->firstOrFail();
    $orderLine = $order->lines()->firstOrFail();
    $stockBefore = DB::table('stock_movements')->count();
    $progressBefore = DB::table('sales_order_line_progress_effects')->count();

    $basePayload = [
        'series_code' => 'default',
        'sales_order_id' => $order->getKey(),
        'source_address_id' => $address->getKey(),
        'dispatch_date' => '2026-08-26',
        'lines' => [[
            'sales_order_line_id' => $orderLine->getKey(),
            'quantity' => '1',
        ]],
    ];

    $overridePayload = $basePayload;
    $overridePayload['lines'][0]['allocation_key'] = $alternateWarehouse->getKey().':'.$alternateLocation->getKey();
    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post('/dispatches', $overridePayload)
        ->assertSessionHasErrors('lines.0.allocation_key');

    expect(Dispatch::query()->where('company_id', $company->getKey())->count())->toBe(0);

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post('/dispatches', $basePayload)
        ->assertRedirect();

    $dispatch = Dispatch::query()->where('company_id', $company->getKey())->firstOrFail();
    $dispatchLine = $dispatch->lines()->firstOrFail();

    expect((string) $dispatch->number)->toBe('DSP-0001')
        ->and((int) $dispatchLine->warehouse_id)->toBe((int) $warehouse->getKey())
        ->and((int) $dispatchLine->location_id)->toBe((int) $location->getKey())
        ->and(DB::table('stock_movements')->count())->toBe($stockBefore)
        ->and(DB::table('sales_order_line_progress_effects')->count())->toBe($progressBefore);

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->get('/sales-orders/'.$order->getKey().'/edit')
        ->assertStatus(409);
    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->put('/sales-orders/'.$order->getKey(), [])
        ->assertStatus(409);
    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->get('/sales-orders/'.$order->getKey())
        ->assertOk()
        ->assertDontSee('/sales-orders/'.$order->getKey().'/edit', false);

    expect(fn () => DB::table('sales_orders')->where('id', $order->getKey())->update([
        'note' => 'DB mutation must fail',
    ]))->toThrow(QueryException::class);
    expect(fn () => DB::table('sales_order_lines')->where('id', $orderLine->getKey())->update([
        'description' => 'DB mutation must fail',
    ]))->toThrow(QueryException::class);
});

/** @return array{Company, Account, Product, AccountAddress, Warehouse, WarehouseLocation} */
function dispatch71AllocatedFixture(string $code): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $account = Account::query()->create([
        'company_id' => $company->getKey(), 'code' => 'CUST', 'type' => AccountType::Customer,
        'status' => AccountStatus::Active, 'legal_name' => 'Müşteri '.$code, 'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None, 'tax_number' => null, 'tax_office' => null,
        'book_currency_code' => 'TRY', 'due_days' => 0, 'discount_rate' => '0.000000', 'risk_limit' => '0.000000',
    ]);
    $category = Category::query()->create([
        'company_id' => $company->getKey(), 'code' => 'CAT', 'name' => 'Kategori', 'is_active' => true,
    ]);
    $unit = Unit::query()->create([
        'company_id' => $company->getKey(), 'code' => 'ADET', 'name' => 'Adet', 'is_active' => true,
    ]);
    $tax = Tax::query()->create([
        'company_id' => $company->getKey(), 'code' => 'KDV20', 'name' => 'KDV %20',
        'rate' => '20.000000', 'is_active' => true,
    ]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(), 'code' => 'SKU', 'status' => ProductStatus::Active,
        'name' => 'Ürün '.$code, 'category_id' => $category->getKey(), 'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(), 'sale_price_net' => '100.000000', 'purchase_price_net' => '60.000000',
    ]);
    $address = AccountAddress::query()->create([
        'company_id' => $company->getKey(), 'account_id' => $account->getKey(), 'type' => AccountAddressType::Shipping,
        'label' => 'Ana Sevk', 'recipient_name' => 'Depo Teslim', 'line1' => 'Mars Cad. 71', 'line2' => null,
        'district' => 'Şişli', 'city' => 'İstanbul', 'postal_code' => '34360', 'country_code' => 'TR',
        'is_default' => true,
    ]);
    $warehouse = Warehouse::query()->create([
        'company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Merkez Depo', 'is_active' => true,
    ]);
    $location = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(), 'warehouse_id' => $warehouse->getKey(),
        'code' => 'A-01', 'name' => 'A Rafı', 'is_active' => true,
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(), 'document_type' => DocumentType::SalesOrder, 'series_code' => 'default',
        'prefix' => 'SO-', 'padding' => 4, 'next_value' => 1, 'is_active' => true,
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(), 'document_type' => DocumentType::Dispatch, 'series_code' => 'default',
        'prefix' => 'DSP-', 'padding' => 4, 'next_value' => 1, 'is_active' => true,
    ]);

    return [$company, $account, $product, $address, $warehouse, $location];
}

function dispatch71AllocatedActor(Company $company): User
{
    $user = User::query()->create([
        'name' => 'Dispatch allocated', 'email' => strtolower((string) $company->code).'@dispatch-allocated.test',
        'password' => 'correct-password', 'status' => UserStatus::Active,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(), 'user_id' => $user->getKey(), 'is_active' => true, 'joined_at' => now(),
    ]);
    $role = Role::query()->create([
        'company_id' => $company->getKey(), 'code' => 'dispatch-allocated', 'name' => 'Dispatch allocated', 'is_active' => true,
    ]);
    foreach ([PermissionKey::SalesOrderView, PermissionKey::SalesOrderManage, PermissionKey::DispatchView, PermissionKey::DispatchManage] as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}

function dispatch71Opening(
    Company $company,
    Product $product,
    Warehouse $warehouse,
    WarehouseLocation $location,
    string $quantity,
): void {
    DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: new SourceEffectIdentity(
            (int) $company->getKey(),
            'dispatch.test',
            'opening-'.$company->code,
            'inventory.opening',
        ),
        productId: (int) $product->getKey(),
        warehouseId: (int) $warehouse->getKey(),
        locationId: (int) $location->getKey(),
        movementType: StockMovementType::OpeningIn,
        quantity: $quantity,
        unitCost: '10',
    )));
}
