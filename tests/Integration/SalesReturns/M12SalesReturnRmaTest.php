<?php

use App\Foundation\Clock\Clock;
use App\Modules\Accounts\Enums\AccountAddressType;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountAddress;
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
use App\Modules\SalesInvoices\Models\SalesInvoice;
use App\Modules\SalesReturns\Actions\AuthorizeSalesReturn;
use App\Modules\SalesReturns\Actions\CompleteSalesReturn;
use App\Modules\SalesReturns\Actions\CreateSalesReturn;
use App\Modules\SalesReturns\Actions\ReceiveSalesReturn;
use App\Modules\SalesReturns\Actions\SalesReturnDraftData;
use App\Modules\SalesReturns\Actions\SalesReturnInspectionLineData;
use App\Modules\SalesReturns\Actions\SalesReturnLineData;
use App\Modules\SalesReturns\Enums\SalesReturnStatus;
use App\Modules\SalesReturns\Models\SalesReturn;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
    app()->instance(Clock::class, new class implements Clock
    {
        public function now(): DateTimeImmutable
        {
            return new DateTimeImmutable('2026-08-28T12:00:00+00:00');
        }
    });
});

it('completes a partial RMA with exact customer credit and original-cost stock restoration exactly once', function (): void {
    [$company, $account, $product, $billing, $warehouse, $location, $manager] = m12Fixture('M12-HAPPY');
    $invoice = m12FinalizedInvoice($this, $company, $manager, $account, $product, $billing, $warehouse, $location, '2');
    $sourceLine = $invoice->lines()->firstOrFail();

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('returns.store'), [
            'series_code' => 'default',
            'sales_invoice_id' => $invoice->getKey(),
            'return_date' => '2026-08-28',
            'note' => 'M12 partial return',
            'lines' => [[
                'sales_invoice_line_id' => $sourceLine->getKey(),
                'quantity' => '1',
                'reason_code' => 'customer_return',
            ]],
        ])->assertRedirect();

    $return = SalesReturn::query()->where('company_id', $company->getKey())->firstOrFail();
    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('returns.authorize', $return->getKey()))
        ->assertRedirect(route('returns.show', $return->getKey()));
    $line = $return->refresh()->lines()->firstOrFail();

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('returns.receive', $return->getKey()), [
            'lines' => [[
                'sales_return_line_id' => $line->getKey(),
                'accepted_quantity' => '1',
                'rejected_quantity' => '0',
                'restock_quantity' => '1',
                'condition_notes' => 'Ambalaj açılmış, ürün sağlam.',
            ]],
        ])->assertRedirect(route('returns.show', $return->getKey()));

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('returns.complete', $return->getKey()))
        ->assertRedirect(route('returns.show', $return->getKey()));

    $return->refresh();
    $line->refresh();
    $accountEffect = DB::table('account_transactions')
        ->where('company_id', $company->getKey())
        ->where('source_type', 'sales_return')
        ->where('source_id', (string) $return->getKey())
        ->where('effect_type', 'account.sales_return')
        ->first();
    $stockEffect = DB::table('stock_movements')
        ->where('company_id', $company->getKey())
        ->where('source_type', 'sales_return_line')
        ->where('source_id', (string) $line->getKey())
        ->where('effect_type', 'stock.in')
        ->first();
    $balance = DB::table('stock_balances')
        ->where('company_id', $company->getKey())
        ->where('product_id', $product->getKey())
        ->where('warehouse_id', $warehouse->getKey())
        ->where('location_id', $location->getKey())
        ->first();

    expect($return->statusEnum())->toBe(SalesReturnStatus::Completed)
        ->and((string) $return->requested_gross_total)->toBe('120.000000')
        ->and((string) $return->credited_gross_total)->toBe('120.000000')
        ->and((string) $line->unit_cost)->toBe('60.000000')
        ->and($accountEffect)->not->toBeNull()
        ->and((string) $accountEffect->signed_amount)->toBe('-120.000000')
        ->and($stockEffect)->not->toBeNull()
        ->and((string) $stockEffect->movement_type)->toBe('sales_return_in')
        ->and((string) $stockEffect->quantity_delta)->toBe('1.000000')
        ->and((string) $stockEffect->unit_cost)->toBe('60.000000')
        ->and((string) $balance->quantity)->toBe('19.000000')
        ->and((string) $balance->inventory_value)->toBe('1140.000000');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('returns.complete', $return->getKey()))
        ->assertRedirect();

    expect(DB::table('account_transactions')->where('source_type', 'sales_return')->where('source_id', (string) $return->getKey())->count())->toBe(1)
        ->and(DB::table('stock_movements')->where('source_type', 'sales_return_line')->where('source_id', (string) $line->getKey())->count())->toBe(1);
});

it('serializes RMA authorization against remaining invoice return capacity', function (): void {
    [$company, $account, $product, $billing, $warehouse, $location, $manager] = m12Fixture('M12-CAP');
    $invoice = m12FinalizedInvoice($this, $company, $manager, $account, $product, $billing, $warehouse, $location, '2');
    $sourceLine = $invoice->lines()->firstOrFail();
    $this->actingAs($manager);
    app(ActiveCompanyContext::class)->set($company);

    $first = m12CreateReturn($invoice, (int) $sourceLine->getKey(), '2');
    $second = m12CreateReturn($invoice, (int) $sourceLine->getKey(), '1');
    app(AuthorizeSalesReturn::class)->handle((int) $first->getKey());

    expect(fn () => app(AuthorizeSalesReturn::class)->handle((int) $second->getKey()))
        ->toThrow(ValidationException::class);

    expect($first->refresh()->statusEnum())->toBe(SalesReturnStatus::Authorized)
        ->and($second->refresh()->statusEnum())->toBe(SalesReturnStatus::Draft);
});

it('closes a rejected RMA without customer credit or stock restoration', function (): void {
    [$company, $account, $product, $billing, $warehouse, $location, $manager] = m12Fixture('M12-REJECT');
    $invoice = m12FinalizedInvoice($this, $company, $manager, $account, $product, $billing, $warehouse, $location, '1');
    $sourceLine = $invoice->lines()->firstOrFail();
    $this->actingAs($manager);
    app(ActiveCompanyContext::class)->set($company);

    $return = m12CreateReturn($invoice, (int) $sourceLine->getKey(), '1');
    app(AuthorizeSalesReturn::class)->handle((int) $return->getKey());
    $line = $return->refresh()->lines()->firstOrFail();
    app(ReceiveSalesReturn::class)->handle((int) $return->getKey(), [
        new SalesReturnInspectionLineData((int) $line->getKey(), '0', '1', '0', 'RMA rejected'),
    ]);
    app(CompleteSalesReturn::class)->handle((int) $return->getKey());

    expect($return->refresh()->statusEnum())->toBe(SalesReturnStatus::Completed)
        ->and((string) $return->credited_gross_total)->toBe('0.000000')
        ->and(DB::table('account_transactions')->where('source_type', 'sales_return')->where('source_id', (string) $return->getKey())->count())->toBe(0)
        ->and(DB::table('stock_movements')->where('source_type', 'sales_return_line')->where('source_id', (string) $line->getKey())->count())->toBe(0);
});

it('enforces RMA lifecycle and tenant lineage at PostgreSQL boundary', function (): void {
    [$company, $account, $product, $billing, $warehouse, $location, $manager] = m12Fixture('M12-GUARD');
    $invoice = m12FinalizedInvoice($this, $company, $manager, $account, $product, $billing, $warehouse, $location, '2');
    $sourceLine = $invoice->lines()->firstOrFail();
    $this->actingAs($manager);
    app(ActiveCompanyContext::class)->set($company);
    $return = m12CreateReturn($invoice, (int) $sourceLine->getKey(), '1');

    expect(fn () => DB::table('sales_returns')->where('id', $return->getKey())->update([
        'status' => 'completed',
        'authorized_at' => now(),
        'received_at' => now(),
        'completed_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    app(AuthorizeSalesReturn::class)->handle((int) $return->getKey());
    $line = $return->refresh()->lines()->firstOrFail();
    expect(fn () => DB::table('sales_return_lines')->where('id', $line->getKey())->update([
        'quantity' => '2.000000',
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    $foreignCompany = Company::query()->create(['code' => 'M12-FOREIGN', 'name' => 'Foreign Company']);
    app(ActiveCompanyContext::class)->set($foreignCompany);
    expect(fn () => app(CreateSalesReturn::class)->handle(new SalesReturnDraftData(
        salesInvoiceId: (int) $invoice->getKey(),
        returnDate: '2026-08-28',
        note: null,
        lines: [new SalesReturnLineData((int) $sourceLine->getKey(), '1', 'foreign_attempt')],
    )))->toThrow(ValidationException::class);
});

/** @return array{Company,Account,Product,AccountAddress,Warehouse,WarehouseLocation,User} */
function m12Fixture(string $code): array
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
        'line1' => 'Mars Cad. 12',
        'line2' => null,
        'district' => 'Şişli',
        'city' => 'İstanbul',
        'postal_code' => '34360',
        'country_code' => 'TR',
        'is_default' => true,
    ]);
    $category = Category::query()->create(['company_id' => $company->getKey(), 'code' => 'CAT', 'name' => 'Kategori', 'is_active' => true]);
    $unit = Unit::query()->create(['company_id' => $company->getKey(), 'code' => 'ADET', 'name' => 'Adet', 'is_active' => true]);
    $tax = Tax::query()->create(['company_id' => $company->getKey(), 'code' => 'KDV20', 'name' => 'KDV %20', 'rate' => '20.000000', 'is_active' => true]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'SKU',
        'status' => ProductStatus::Active,
        'name' => 'Ürün '.$code,
        'category_id' => $category->getKey(),
        'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(),
        'sale_price_net' => '100.000000',
        'purchase_price_net' => '60.000000',
    ]);
    $warehouse = Warehouse::query()->create(['company_id' => $company->getKey(), 'code' => 'WH', 'name' => 'Ana Depo', 'is_active' => true]);
    $location = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(),
        'warehouse_id' => $warehouse->getKey(),
        'code' => 'LOC',
        'name' => 'Ana Konum',
        'is_active' => true,
    ]);
    DB::table('stock_balances')->insert([
        'company_id' => $company->getKey(),
        'product_id' => $product->getKey(),
        'warehouse_id' => $warehouse->getKey(),
        'location_id' => $location->getKey(),
        'quantity' => '20.000000',
        'average_unit_cost' => '60.000000',
        'inventory_value' => '1200.000000',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    foreach ([DocumentType::SalesInvoice, DocumentType::SalesReturn] as $type) {
        DocumentSequence::query()->create([
            'company_id' => $company->getKey(),
            'document_type' => $type,
            'series_code' => 'default',
            'prefix' => $type === DocumentType::SalesInvoice ? 'INV-' : 'RMA-',
            'padding' => 4,
            'next_value' => 1,
            'is_active' => true,
        ]);
    }
    PostingPeriod::query()->create([
        'company_id' => $company->getKey(),
        'code' => '2026-08',
        'name' => 'Ağustos 2026',
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-31',
        'status' => PostingPeriodStatus::Open,
        'closed_at' => null,
    ]);

    return [$company, $account, $product, $billing, $warehouse, $location, m12Actor($company, $code)];
}

function m12FinalizedInvoice(
    TestCase $test,
    Company $company,
    User $manager,
    Account $account,
    Product $product,
    AccountAddress $billing,
    Warehouse $warehouse,
    WarehouseLocation $location,
    string $quantity,
): SalesInvoice {
    $test->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-invoices', [
            'series_code' => 'default',
            'mode' => 'direct',
            'account_id' => $account->getKey(),
            'source_billing_address_id' => $billing->getKey(),
            'invoice_date' => '2026-08-27',
            'document_discount_rate' => '0',
            'lines' => [[
                'product_id' => $product->getKey(),
                'quantity' => $quantity,
                'allocation_key' => $warehouse->getKey().':'.$location->getKey(),
                'unit_price' => '100',
                'price_basis' => 'net',
                'line_discount_rate' => '0',
            ]],
        ])->assertRedirect();
    $invoice = SalesInvoice::query()->where('company_id', $company->getKey())->latest('id')->firstOrFail();
    $test->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('sales-invoices.finalize', $invoice->getKey()))
        ->assertRedirect(route('sales-invoices.show', $invoice->getKey()));

    return $invoice->refresh()->load('lines');
}

function m12CreateReturn(SalesInvoice $invoice, int $invoiceLineId, string $quantity): SalesReturn
{
    return app(CreateSalesReturn::class)->handle(new SalesReturnDraftData(
        salesInvoiceId: (int) $invoice->getKey(),
        returnDate: '2026-08-28',
        note: null,
        lines: [new SalesReturnLineData($invoiceLineId, $quantity, 'customer_return')],
    ));
}

function m12Actor(Company $company, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'M12 '.$suffix,
        'email' => strtolower($suffix).'@m12.test',
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
        'code' => 'm12',
        'name' => 'M12 Returns',
        'is_active' => true,
    ]);
    foreach ([PermissionKey::SalesInvoiceView, PermissionKey::SalesInvoiceManage, PermissionKey::SalesReturnView, PermissionKey::SalesReturnManage] as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}
