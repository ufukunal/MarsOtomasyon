<?php

use App\Foundation\Clock\Clock;
use App\Modules\Accounts\Enums\AccountAddressType;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountAddress;
use App\Modules\Accounts\Models\AccountTransaction;
use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\DocumentType;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\PostingPeriodStatus;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\DocumentSequence;
use App\Modules\Core\Models\PostingPeriod;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\User;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\SalesInvoices\Actions\CancelSalesInvoice;
use App\Modules\SalesInvoices\Actions\FinalizeSalesInvoice;
use App\Modules\SalesInvoices\Enums\SalesInvoiceStatus;
use App\Modules\SalesInvoices\Models\SalesInvoice;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
    app()->instance(Clock::class, new class implements Clock
    {
        public function now(): DateTimeImmutable
        {
            return new DateTimeImmutable('2026-08-27T12:00:00+00:00');
        }
    });
});

it('posts and reverses the sales invoice receivable exactly once', function (): void {
    [$company, $account, $product, $billing, $warehouse, $location, $manager] = invoice83Fixture('INV83-LIFE');
    $invoice = invoice83Draft($this, $company, $manager, $account, $product, $billing, $warehouse, $location);

    expect($invoice->statusEnum())->toBe(SalesInvoiceStatus::Draft)
        ->and(AccountTransaction::query()->count())->toBe(0);

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('sales-invoices.finalize', $invoice->getKey()))
        ->assertRedirect(route('sales-invoices.show', $invoice->getKey()));

    $invoice->refresh();
    $original = AccountTransaction::query()
        ->where('company_id', $company->getKey())
        ->where('source_type', 'sales_invoice')
        ->where('source_id', (string) $invoice->getKey())
        ->where('effect_type', 'account.sales_invoice')
        ->firstOrFail();

    expect($invoice->statusEnum())->toBe(SalesInvoiceStatus::Finalized)
        ->and($invoice->finalized_at)->not->toBeNull()
        ->and((int) $original->account_id)->toBe((int) $account->getKey())
        ->and($original->posting_date->format('Y-m-d'))->toBe('2026-08-26')
        ->and((string) $original->currency_code)->toBe('TRY')
        ->and((string) $original->signed_amount)->toBe((string) $invoice->gross_total)
        ->and($original->reversal_of_transaction_id)->toBeNull();

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('sales-invoices.finalize', $invoice->getKey()))
        ->assertRedirect();

    expect(AccountTransaction::query()->count())->toBe(1);

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('sales-invoices.cancel', $invoice->getKey()))
        ->assertRedirect(route('sales-invoices.show', $invoice->getKey()));

    $invoice->refresh();
    $reversal = AccountTransaction::query()
        ->where('company_id', $company->getKey())
        ->where('source_type', 'sales_invoice')
        ->where('source_id', (string) $invoice->getKey())
        ->where('effect_type', 'account.sales_invoice.reverse')
        ->firstOrFail();

    expect($invoice->statusEnum())->toBe(SalesInvoiceStatus::Cancelled)
        ->and($invoice->cancelled_at)->not->toBeNull()
        ->and($reversal->posting_date->format('Y-m-d'))->toBe('2026-08-27')
        ->and((string) $reversal->signed_amount)->toBe('-'.(string) $invoice->gross_total)
        ->and((int) $reversal->reversal_of_transaction_id)->toBe((int) $original->getKey());

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('sales-invoices.cancel', $invoice->getKey()))
        ->assertRedirect();

    expect(AccountTransaction::query()->count())->toBe(2);
});

it('rolls account effects back with failed lifecycle commits and rejects raw incomplete transitions', function (): void {
    [$company, $account, $product, $billing, $warehouse, $location, $manager] = invoice83Fixture('INV83-ATOMIC');
    $invoice = invoice83Draft($this, $company, $manager, $account, $product, $billing, $warehouse, $location);

    $this->actingAs($manager);
    session(['active_company_id' => $company->getKey()]);
    app(ActiveCompanyContext::class)->set($company);

    expect(fn () => DB::table('sales_invoices')->where('id', $invoice->getKey())->update([
        'status' => 'finalized',
        'finalized_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    DB::statement("ALTER TABLE sales_invoices ADD CONSTRAINT invoice83_force_finalize_failure CHECK (status <> 'finalized')");
    try {
        expect(fn () => app(FinalizeSalesInvoice::class)->handle((int) $invoice->getKey()))
            ->toThrow(QueryException::class);
    } finally {
        DB::statement('ALTER TABLE sales_invoices DROP CONSTRAINT IF EXISTS invoice83_force_finalize_failure');
    }

    expect($invoice->refresh()->statusEnum())->toBe(SalesInvoiceStatus::Draft)
        ->and(AccountTransaction::query()->count())->toBe(0);

    app(FinalizeSalesInvoice::class)->handle((int) $invoice->getKey());
    expect(AccountTransaction::query()->count())->toBe(1);

    DB::statement("ALTER TABLE sales_invoices ADD CONSTRAINT invoice83_force_cancel_failure CHECK (status <> 'cancelled')");
    try {
        expect(fn () => app(CancelSalesInvoice::class)->handle((int) $invoice->getKey()))
            ->toThrow(QueryException::class);
    } finally {
        DB::statement('ALTER TABLE sales_invoices DROP CONSTRAINT IF EXISTS invoice83_force_cancel_failure');
    }

    expect($invoice->refresh()->statusEnum())->toBe(SalesInvoiceStatus::Finalized)
        ->and(AccountTransaction::query()->count())->toBe(1);
});

/** @return array{Company,Account,Product,AccountAddress,Warehouse,WarehouseLocation,User} */
function invoice83Fixture(string $code): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $account = Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'CUST',
        'type' => AccountType::Customer,
        'status' => AccountStatus::Active,
        'legal_name' => 'Müşteri '.$code,
        'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null,
        'tax_office' => null,
        'book_currency_code' => 'TRY',
        'due_days' => 0,
        'discount_rate' => '0.000000',
        'risk_limit' => '0.000000',
    ]);
    $billing = AccountAddress::query()->create([
        'company_id' => $company->getKey(),
        'account_id' => $account->getKey(),
        'type' => AccountAddressType::Billing,
        'label' => 'Fatura',
        'recipient_name' => 'Muhasebe',
        'line1' => 'Mars Cad. 83',
        'line2' => null,
        'district' => 'Şişli',
        'city' => 'İstanbul',
        'postal_code' => '34360',
        'country_code' => 'TR',
        'is_default' => true,
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
    $product = Product::query()->create([
        'company_id' => $company->getKey(), 'code' => 'SKU', 'status' => ProductStatus::Active,
        'name' => 'Ürün '.$code, 'category_id' => $category->getKey(), 'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(), 'sale_price_net' => '100.000000', 'purchase_price_net' => '60.000000',
    ]);
    $warehouse = Warehouse::query()->create([
        'company_id' => $company->getKey(), 'code' => 'WH', 'name' => 'Ana Depo', 'is_active' => true,
    ]);
    $location = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(), 'warehouse_id' => $warehouse->getKey(),
        'code' => 'LOC', 'name' => 'Ana Konum', 'is_active' => true,
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(), 'document_type' => DocumentType::SalesInvoice,
        'series_code' => 'default', 'prefix' => 'INV-', 'padding' => 4, 'next_value' => 1, 'is_active' => true,
    ]);
    PostingPeriod::query()->create([
        'company_id' => $company->getKey(), 'code' => '2026-08', 'name' => 'Ağustos 2026',
        'starts_on' => '2026-08-01', 'ends_on' => '2026-08-31', 'status' => PostingPeriodStatus::Open,
        'closed_at' => null,
    ]);

    return [$company, $account, $product, $billing, $warehouse, $location, invoice83Actor($company, $code)];
}

function invoice83Draft(
    TestCase $test,
    Company $company,
    User $manager,
    Account $account,
    Product $product,
    AccountAddress $billing,
    Warehouse $warehouse,
    WarehouseLocation $location,
): SalesInvoice {
    $test->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-invoices', [
            'series_code' => 'default',
            'mode' => 'direct',
            'account_id' => $account->getKey(),
            'source_billing_address_id' => $billing->getKey(),
            'invoice_date' => '2026-08-26',
            'document_discount_rate' => '0',
            'lines' => [[
                'product_id' => $product->getKey(),
                'quantity' => '2',
                'allocation_key' => $warehouse->getKey().':'.$location->getKey(),
                'unit_price' => '100',
                'price_basis' => 'net',
                'line_discount_rate' => '0',
            ]],
        ])->assertRedirect();

    return SalesInvoice::query()->where('company_id', $company->getKey())->firstOrFail();
}

function invoice83Actor(Company $company, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'Invoice '.$suffix,
        'email' => strtolower($suffix).'@invoice83.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(), 'user_id' => $user->getKey(), 'is_active' => true, 'joined_at' => now(),
    ]);
    $role = Role::query()->create([
        'company_id' => $company->getKey(), 'code' => 'invoice83', 'name' => 'Invoice 83', 'is_active' => true,
    ]);
    foreach ([PermissionKey::SalesInvoiceView, PermissionKey::SalesInvoiceManage] as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}
