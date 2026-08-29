<?php

use App\Foundation\Correlation\CorrelationContext;
use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Accounts\Ledger\AccountTransactionPoster;
use App\Modules\Accounts\Ledger\PostAccountTransactionData;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\PostingPeriodStatus;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\PostingPeriod;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\User;
use App\Modules\Inventory\Actions\PostManualStockMovement;
use App\Modules\Inventory\Enums\ManualStockMovementKind;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Reports\ReportService;
use App\Modules\Treasury\Ledger\PostTreasuryMovementData;
use App\Modules\Treasury\Ledger\TreasuryMovementPoster;
use App\Modules\Treasury\Models\TreasuryAccount;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('builds tenant scoped finance snapshot and fifo aging from canonical ledgers', function (): void {
    [$company, $user] = m13ActorContext('M13-FINANCE', [PermissionKey::ReportsView]);
    m13PostingPeriods($company);

    $customer = m13Account($company, 'CUST-1', 'customer', 'M13 Müşteri');
    $supplier = m13Account($company, 'SUP-1', 'supplier', 'M13 Tedarikçi');

    m13PostAccount($company, $customer, '2026-07-01', '100.000000', 'customer-invoice', 'account.m13_customer_invoice');
    m13PostAccount($company, $customer, '2026-07-15', '-30.000000', 'customer-payment', 'account.m13_customer_payment');
    m13PostAccount($company, $supplier, '2026-08-20', '-50.000000', 'supplier-invoice', 'account.m13_supplier_invoice');

    $treasury = TreasuryAccount::query()->create([
        'company_id' => $company->getKey(),
        'type' => 'bank',
        'code' => 'BANK-M13',
        'name' => 'M13 Banka',
        'currency_code' => 'TRY',
        'is_active' => true,
        'bank_name' => 'M13 Bank',
    ]);

    DB::transaction(fn () => app(TreasuryMovementPoster::class)->post(
        new PostTreasuryMovementData(
            sourceEffect: new SourceEffectIdentity(
                (int) $company->getKey(),
                'm13_report_test',
                'treasury-in',
                'treasury.bank_in',
            ),
            treasuryAccountId: (int) $treasury->getKey(),
            postingDate: '2026-08-10',
            signedAmount: '200.000000',
            movementType: 'bank_in',
            memo: 'M13 report fixture',
        ),
    ));

    $foreign = Company::query()->create(['code' => 'M13-FOREIGN', 'name' => 'Foreign M13']);
    m13PostingPeriods($foreign);
    $foreignTreasury = TreasuryAccount::query()->create([
        'company_id' => $foreign->getKey(),
        'type' => 'bank',
        'code' => 'BANK-FOREIGN',
        'name' => 'Foreign Banka',
        'currency_code' => 'TRY',
        'is_active' => true,
        'bank_name' => 'Foreign Bank',
    ]);
    DB::transaction(fn () => app(TreasuryMovementPoster::class)->post(
        new PostTreasuryMovementData(
            sourceEffect: new SourceEffectIdentity(
                (int) $foreign->getKey(),
                'm13_report_test',
                'foreign-treasury-in',
                'treasury.bank_in',
            ),
            treasuryAccountId: (int) $foreignTreasury->getKey(),
            postingDate: '2026-08-10',
            signedAmount: '999.000000',
            movementType: 'bank_in',
        ),
    ));

    $report = app(ReportService::class)->build((int) $company->getKey(), [
        'as_of' => '2026-08-29',
        'currency' => 'TRY',
        'warehouse_id' => null,
        'account_type' => null,
    ]);

    expect($report['finance'])->toHaveCount(1)
        ->and($report['finance'][0]['currency'])->toBe('TRY')
        ->and($report['finance'][0]['treasury'])->toBe(200.0)
        ->and($report['finance'][0]['receivable'])->toBe(70.0)
        ->and($report['finance'][0]['payable'])->toBe(50.0)
        ->and($report['finance'][0]['net'])->toBe(220.0);

    $customerAging = collect($report['aging'])->firstWhere('code', 'CUST-1');
    $supplierAging = collect($report['aging'])->firstWhere('code', 'SUP-1');

    expect($customerAging)->not->toBeNull()
        ->and($customerAging['days_31_60'])->toBe(70.0)
        ->and($customerAging['total'])->toBe(70.0)
        ->and($supplierAging)->not->toBeNull()
        ->and($supplierAging['days_1_30'])->toBe(50.0)
        ->and($supplierAging['total'])->toBe(50.0);

    $this->actingAs($user)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/reports?as_of=2026-08-29&currency=TRY')
        ->assertOk()
        ->assertSee('Finansal Pozisyon')
        ->assertSee('Yaşlandırma')
        ->assertSee('220,00');
});

it('reports as-of stock valuation and exports report sections as csv', function (): void {
    [$company, $user, $product, $warehouse, $location] = m13StockContext('M13-STOCK');

    app(PostManualStockMovement::class)->handle(
        'm13-stock-opening',
        $product->getKey(),
        $warehouse->getKey(),
        $location->getKey(),
        ManualStockMovementKind::OpeningIn,
        '10',
        '100',
        'M13 opening',
    );
    app(CorrelationContext::class)->set('m13-stock-out');
    app(PostManualStockMovement::class)->handle(
        'm13-stock-out',
        $product->getKey(),
        $warehouse->getKey(),
        $location->getKey(),
        ManualStockMovementKind::AdjustmentOut,
        '2',
        null,
        'M13 adjustment',
    );

    $report = app(ReportService::class)->build((int) $company->getKey(), [
        'as_of' => now()->toDateString(),
        'currency' => null,
        'warehouse_id' => (int) $warehouse->getKey(),
        'account_type' => null,
    ]);

    expect($report['stock'])->toHaveCount(1)
        ->and($report['stock'][0]['product_code'])->toBe($product->code)
        ->and($report['stock'][0]['warehouse_code'])->toBe($warehouse->code)
        ->and($report['stock'][0]['quantity'])->toBe(8.0)
        ->and($report['stock'][0]['unit_cost'])->toBe(100.0)
        ->and($report['stock'][0]['value'])->toBe(800.0)
        ->and($report['movements'])->toHaveCount(2);

    $response = $this->actingAs($user)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/reports/export?section=stock&as_of='.now()->toDateString().'&warehouse_id='.$warehouse->getKey());

    $response->assertOk();
    expect((string) $response->headers->get('content-type'))->toContain('text/csv');
});

it('keeps reports behind reports view permission', function (): void {
    [$company, $user] = m13ActorContext('M13-RBAC', []);

    $this->actingAs($user)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/reports')
        ->assertForbidden();
});

/**
 * @param  list<PermissionKey>  $permissions
 * @return array{Company, User}
 */
function m13ActorContext(string $code, array $permissions): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $user = User::query()->create([
        'name' => $code.' User',
        'email' => strtolower($code).'@m13.test',
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
        'code' => 'M13-'.$code,
        'name' => 'M13 '.$code,
        'is_active' => true,
    ]);
    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    app(ActiveCompanyContext::class)->set($company);
    app(CorrelationContext::class)->set('m13-'.$code);
    test()->actingAs($user);

    return [$company, $user];
}

function m13PostingPeriods(Company $company): void
{
    foreach ([
        ['2026-07', '2026-07-01', '2026-07-31'],
        ['2026-08', '2026-08-01', '2026-08-31'],
    ] as [$code, $startsOn, $endsOn]) {
        PostingPeriod::query()->create([
            'company_id' => $company->getKey(),
            'code' => $code,
            'name' => $code,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'status' => PostingPeriodStatus::Open,
            'closed_at' => null,
        ]);
    }
}

function m13Account(Company $company, string $code, string $type, string $name): Account
{
    return Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => $code,
        'type' => $type,
        'status' => 'active',
        'legal_name' => $name,
        'book_currency_code' => 'TRY',
        'due_days' => 0,
    ]);
}

function m13PostAccount(
    Company $company,
    Account $account,
    string $date,
    string $amount,
    string $sourceId,
    string $effectType,
): void {
    DB::transaction(fn () => app(AccountTransactionPoster::class)->post(
        new PostAccountTransactionData(
            accountId: (int) $account->getKey(),
            postingDate: $date,
            signedAmount: $amount,
            sourceEffect: new SourceEffectIdentity(
                (int) $company->getKey(),
                'm13_report_test',
                $sourceId,
                $effectType,
            ),
        ),
    ));
}

/** @return array{Company, User, Product, Warehouse, WarehouseLocation} */
function m13StockContext(string $code): array
{
    [$company, $user] = m13ActorContext($code, [
        PermissionKey::ReportsView,
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
