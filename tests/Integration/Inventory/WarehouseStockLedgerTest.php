<?php

use App\Foundation\Correlation\CorrelationContext;
use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\AuditEntry;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\User;
use App\Modules\Inventory\Actions\PostManualStockMovement;
use App\Modules\Inventory\Enums\ManualStockMovementKind;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('creates company scoped warehouses and locations with audit evidence', function (): void {
    [$company, $manager] = m41ActorContext('M41-MASTER', [PermissionKey::InventoryView, PermissionKey::InventoryManage]);

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/inventory/warehouses', ['code' => 'main', 'name' => 'Merkez Depo'])
        ->assertRedirect('/inventory/warehouses');

    $warehouse = Warehouse::query()->where('company_id', $company->getKey())->firstOrFail();
    expect($warehouse->code)->toBe('MAIN');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/inventory/warehouses/'.$warehouse->getKey().'/locations', ['code' => 'a-01', 'name' => 'A Rafı'])
        ->assertRedirect('/inventory/warehouses');

    $location = WarehouseLocation::query()->where('warehouse_id', $warehouse->getKey())->firstOrFail();
    expect($location->code)->toBe('A-01')
        ->and(AuditEntry::query()->where('action', AuditAction::WarehouseCreated->value)->count())->toBe(1)
        ->and(AuditEntry::query()->where('action', AuditAction::WarehouseLocationCreated->value)->count())->toBe(1);
});

it('keeps stock viewing separate from stock management', function (): void {
    [$company, $viewer] = m41ActorContext('M41-RBAC', [PermissionKey::InventoryView]);

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/inventory/stock')
        ->assertOk();

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/inventory/warehouses')
        ->assertOk()
        ->assertDontSee('Depo Oluştur');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/inventory/warehouses', ['code' => 'BLOCKED', 'name' => 'Blocked'])
        ->assertForbidden();
});

it('does not treat product catalog permission as inventory permission', function (): void {
    [$company, $productViewer] = m41ActorContext('M41-PRODUCT-ONLY', [PermissionKey::ProductView]);

    $this->actingAs($productViewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/inventory')
        ->assertOk();

    $this->actingAs($productViewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/inventory/stock')
        ->assertForbidden();
});

it('posts idempotent opening inventory and records the rebuildable balance projection', function (): void {
    [$company, $actor, $product, $warehouse, $location] = m41StockContext('M41-OPEN');

    $movement = app(PostManualStockMovement::class)->handle(
        'm41-open-1',
        $product->getKey(),
        $warehouse->getKey(),
        $location->getKey(),
        ManualStockMovementKind::OpeningIn,
        '10.000000',
        '100.000000',
        'Açılış',
    );

    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();
    expect($movement->quantity_delta)->toBe('10.000000')
        ->and($movement->value_delta)->toBe('1000.000000')
        ->and($balance->quantity)->toBe('10.000000')
        ->and($balance->average_unit_cost)->toBe('100.000000')
        ->and($balance->inventory_value)->toBe('1000.000000');

    $replayed = app(PostManualStockMovement::class)->handle(
        'm41-open-1',
        $product->getKey(),
        $warehouse->getKey(),
        $location->getKey(),
        ManualStockMovementKind::OpeningIn,
        '10.000000',
        '100.000000',
        'Açılış',
    );

    expect($replayed->getKey())->toBe($movement->getKey())
        ->and(StockMovement::query()->where('company_id', $company->getKey())->count())->toBe(1)
        ->and(StockBalance::query()->findOrFail($balance->getKey())->quantity)->toBe('10.000000');

    expect(fn () => app(PostManualStockMovement::class)->handle(
        'm41-open-1',
        $product->getKey(),
        $warehouse->getKey(),
        $location->getKey(),
        ManualStockMovementKind::OpeningIn,
        '11.000000',
        '100.000000',
        'Açılış',
    ))->toThrow(ValidationException::class);

    expect(AuditEntry::query()->where('action', AuditAction::StockMovementPosted->value)->count())->toBe(1);
});

it('updates moving weighted average and carries that cost on outbound stock', function (): void {
    [, , $product, $warehouse, $location] = m41StockContext('M41-COST');

    app(PostManualStockMovement::class)->handle(
        'm41-cost-1', $product->getKey(), $warehouse->getKey(), $location->getKey(),
        ManualStockMovementKind::OpeningIn, '10', '100', null,
    );
    app(CorrelationContext::class)->set('m4-1-cost-in-2');
    app(PostManualStockMovement::class)->handle(
        'm41-cost-2', $product->getKey(), $warehouse->getKey(), $location->getKey(),
        ManualStockMovementKind::AdjustmentIn, '10', '200', null,
    );

    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();
    expect($balance->quantity)->toBe('20.000000')
        ->and($balance->average_unit_cost)->toBe('150.000000')
        ->and($balance->inventory_value)->toBe('3000.000000');

    app(CorrelationContext::class)->set('m4-1-cost-out');
    $out = app(PostManualStockMovement::class)->handle(
        'm41-cost-3', $product->getKey(), $warehouse->getKey(), $location->getKey(),
        ManualStockMovementKind::AdjustmentOut, '5', null, null,
    );

    $balance->refresh();
    expect($out->quantity_delta)->toBe('-5.000000')
        ->and($out->unit_cost)->toBe('150.000000')
        ->and($out->value_delta)->toBe('-750.000000')
        ->and($balance->quantity)->toBe('15.000000')
        ->and($balance->average_unit_cost)->toBe('150.000000')
        ->and($balance->inventory_value)->toBe('2250.000000');
});

it('blocks negative stock atomically and rejects silent zero cost positive inventory', function (): void {
    [, , $product, $warehouse, $location] = m41StockContext('M41-GUARD');

    expect(fn () => app(PostManualStockMovement::class)->handle(
        'm41-zero-cost', $product->getKey(), $warehouse->getKey(), $location->getKey(),
        ManualStockMovementKind::OpeningIn, '2', '0', null,
    ))->toThrow(ValidationException::class);

    app(PostManualStockMovement::class)->handle(
        'm41-guard-in', $product->getKey(), $warehouse->getKey(), $location->getKey(),
        ManualStockMovementKind::OpeningIn, '2', '90', null,
    );
    app(CorrelationContext::class)->set('m4-1-negative-block');

    expect(fn () => app(PostManualStockMovement::class)->handle(
        'm41-guard-out', $product->getKey(), $warehouse->getKey(), $location->getKey(),
        ManualStockMovementKind::AdjustmentOut, '3', null, null,
    ))->toThrow(ValidationException::class);

    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();
    expect($balance->quantity)->toBe('2.000000')
        ->and($balance->inventory_value)->toBe('180.000000')
        ->and(StockMovement::query()->count())->toBe(1);
});

it('zeros quantity carrying value and average cost when the last unit leaves stock', function (): void {
    [, , $product, $warehouse, $location] = m41StockContext('M41-ZERO');

    app(PostManualStockMovement::class)->handle(
        'm41-zero-in', $product->getKey(), $warehouse->getKey(), $location->getKey(),
        ManualStockMovementKind::OpeningIn, '3', '25', null,
    );
    app(CorrelationContext::class)->set('m4-1-zero-out');
    app(PostManualStockMovement::class)->handle(
        'm41-zero-out', $product->getKey(), $warehouse->getKey(), $location->getKey(),
        ManualStockMovementKind::AdjustmentOut, '3', null, null,
    );

    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();
    expect($balance->quantity)->toBe('0.000000')
        ->and($balance->average_unit_cost)->toBe('0.000000')
        ->and($balance->inventory_value)->toBe('0.000000');
});

it('enforces warehouse location and stock tenant ownership at PostgreSQL level', function (): void {
    [$companyA, , $productA, $warehouseA] = m41StockContext('M41-DB-A');
    [$companyB, , , $warehouseB, $locationB] = m41StockContext('M41-DB-B');

    expect(fn () => DB::table('warehouse_locations')->insert([
        'company_id' => $companyA->getKey(),
        'warehouse_id' => $warehouseB->getKey(),
        'code' => 'FOREIGN',
        'name' => 'Foreign',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('stock_balances')->insert([
        'company_id' => $companyA->getKey(),
        'product_id' => $productA->getKey(),
        'warehouse_id' => $warehouseA->getKey(),
        'location_id' => $locationB->getKey(),
        'quantity' => '0',
        'average_unit_cost' => '0',
        'inventory_value' => '0',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect($companyB->getKey())->not->toBe($companyA->getKey());
});

it('prevents silent update and delete of stock ledger rows at PostgreSQL level', function (): void {
    [, , $product, $warehouse, $location] = m41StockContext('M41-IMMUTABLE');
    $movement = app(PostManualStockMovement::class)->handle(
        'm41-immutable', $product->getKey(), $warehouse->getKey(), $location->getKey(),
        ManualStockMovementKind::OpeningIn, '1', '50', null,
    );

    expect(fn () => DB::table('stock_movements')->where('id', $movement->getKey())->update(['note' => 'mutated']))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('stock_movements')->where('id', $movement->getKey())->delete())
        ->toThrow(QueryException::class);

    expect(StockMovement::query()->findOrFail($movement->getKey())->note)->toBeNull();
});

/**
 * @param  list<PermissionKey>  $permissions
 * @return array{Company, User}
 */
function m41ActorContext(string $code, array $permissions): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $user = User::query()->create([
        'name' => $code.' User',
        'email' => strtolower($code).'@m41.test',
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
        'code' => 'M41-'.$code,
        'name' => 'M4.1 '.$code,
        'is_active' => true,
    ]);
    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    app(ActiveCompanyContext::class)->set($company);
    app(CorrelationContext::class)->set('m4-1-'.$code);
    test()->actingAs($user);

    return [$company, $user];
}

/** @return array{Company, User, Product, Warehouse, WarehouseLocation} */
function m41StockContext(string $code): array
{
    [$company, $user] = m41ActorContext($code, [
        PermissionKey::ProductView,
        PermissionKey::ProductManage,
        PermissionKey::InventoryView,
        PermissionKey::InventoryManage,
    ]);
    $category = Category::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'LIGHT',
        'name' => 'Aydınlatma',
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
        'code' => 'SKU-'.$code,
        'status' => ProductStatus::Active,
        'name' => 'Ürün '.$code,
        'category_id' => $category->getKey(),
        'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(),
        'sale_price_net' => '100.000000',
        'purchase_price_net' => '60.000000',
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

    return [$company, $user, $product, $warehouse, $location];
}
