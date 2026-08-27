<?php

use App\Foundation\Clock\Clock;
use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Accounts\Enums\AccountAddressType;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Ledger\AccountTransactionPoster;
use App\Modules\Accounts\Ledger\PostAccountTransactionData;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountAddress;
use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
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
use App\Modules\SalesInvoices\Enums\SalesInvoiceMode;
use App\Modules\SalesInvoices\Enums\SalesInvoiceStatus;
use App\Modules\SalesInvoices\Models\SalesInvoice;
use App\Modules\SalesInvoices\Models\SalesInvoiceOrderLineCapacity;
use App\Modules\SalesOrders\Enums\SalesOrderProgressType;
use App\Modules\SalesOrders\Models\SalesOrder;
use App\Modules\SalesOrders\Models\SalesOrderLine;
use App\Modules\SalesOrders\Models\SalesOrderLineProgressEffect;
use App\Modules\SalesOrders\Progress\SalesOrderProgressService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
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

it('projects net invoiced draft previous and remaining quantities across partial invoices without creating draft effects', function (): void {
    [$company, $account, $product, $billing, $warehouse, $location, $manager] = invoice83Fixture('INV83-PARTIAL');
    $order = invoice83CreateOrder($this, $company, $manager, $account, $product, '10');
    $line = $order->lines()->firstOrFail();
    $progress = app(SalesOrderProgressService::class);

    DB::transaction(fn (): SalesOrderLineProgressEffect => $progress->record(
        invoice83Identity($company, 'existing-invoice', 'progress.invoice'),
        (int) $line->getKey(),
        SalesOrderProgressType::Invoiced,
        '2',
    ));

    $progressBefore = SalesOrderLineProgressEffect::query()->count();
    $stockBefore = DB::table('stock_movements')->count();
    $accountBefore = DB::table('account_transactions')->count();

    invoice83Post($this, $company, $manager, $order, $line, $billing, $warehouse, $location, '4')->assertRedirect();

    $first = SalesInvoiceOrderLineCapacity::query()
        ->where('company_id', $company->getKey())
        ->where('sales_order_line_id', $line->getKey())
        ->firstOrFail();

    expect((string) $first->ordered_quantity)->toBe('10.000000')
        ->and((string) $first->net_invoiced_quantity)->toBe('2.000000')
        ->and((string) $first->cancelled_quantity)->toBe('0.000000')
        ->and((string) $first->draft_quantity)->toBe('4.000000')
        ->and((string) $first->previous_quantity)->toBe('6.000000')
        ->and((string) $first->remaining_quantity)->toBe('4.000000');

    invoice83Post($this, $company, $manager, $order, $line, $billing, $warehouse, $location, '3')->assertRedirect();

    $second = $first->fresh();
    expect((string) $second->net_invoiced_quantity)->toBe('2.000000')
        ->and((string) $second->draft_quantity)->toBe('7.000000')
        ->and((string) $second->previous_quantity)->toBe('9.000000')
        ->and((string) $second->remaining_quantity)->toBe('1.000000')
        ->and(SalesInvoice::query()->where('company_id', $company->getKey())->count())->toBe(2)
        ->and(SalesOrderLineProgressEffect::query()->count())->toBe($progressBefore)
        ->and(DB::table('stock_movements')->count())->toBe($stockBefore)
        ->and(DB::table('account_transactions')->count())->toBe($accountBefore);
});

it('blocks over invoice before numbering and keeps the failed draft transaction atomic', function (): void {
    [$company, $account, $product, $billing, $warehouse, $location, $manager] = invoice83Fixture('INV83-APP-OVER');
    $order = invoice83CreateOrder($this, $company, $manager, $account, $product, '5');
    $line = $order->lines()->firstOrFail();

    invoice83Post($this, $company, $manager, $order, $line, $billing, $warehouse, $location, '4')->assertRedirect();

    expect((int) DocumentSequence::query()
        ->where('company_id', $company->getKey())
        ->where('document_type', DocumentType::SalesInvoice->value)
        ->value('next_value'))->toBe(2);

    invoice83Post($this, $company, $manager, $order, $line, $billing, $warehouse, $location, '2')
        ->assertSessionHasErrors('lines.0.quantity');

    expect(SalesInvoice::query()->where('company_id', $company->getKey())->count())->toBe(1)
        ->and((int) DocumentSequence::query()
            ->where('company_id', $company->getKey())
            ->where('document_type', DocumentType::SalesInvoice->value)
            ->value('next_value'))->toBe(2);

    $capacity = SalesInvoiceOrderLineCapacity::query()
        ->where('sales_order_line_id', $line->getKey())
        ->firstOrFail();
    expect((string) $capacity->draft_quantity)->toBe('4.000000')
        ->and((string) $capacity->remaining_quantity)->toBe('1.000000');
});

it('enforces over invoice at PostgreSQL when application validation is bypassed', function (): void {
    [$company, $account, $product, $billing, $warehouse, $location, $manager] = invoice83Fixture('INV83-DB-OVER');
    $order = invoice83CreateOrder($this, $company, $manager, $account, $product, '5');
    $line = $order->lines()->firstOrFail();

    invoice83Post($this, $company, $manager, $order, $line, $billing, $warehouse, $location, '4')->assertRedirect();
    $invoiceLine = SalesInvoice::query()
        ->where('company_id', $company->getKey())
        ->firstOrFail()
        ->lines()
        ->firstOrFail();

    try {
        DB::table('sales_invoice_lines')->where('id', $invoiceLine->getKey())->update([
            'quantity' => '6.000000',
        ]);
        $this->fail('PostgreSQL over-invoice guard did not reject the quantity update.');
    } catch (QueryException $exception) {
        expect($exception->getMessage())->toContain('sales invoice quantity exceeds order line remaining quantity');
    }

    $capacity = SalesInvoiceOrderLineCapacity::query()
        ->where('sales_order_line_id', $line->getKey())
        ->firstOrFail();
    expect((string) $capacity->draft_quantity)->toBe('4.000000')
        ->and((string) $capacity->remaining_quantity)->toBe('1.000000');
});

it('blocks invoiced or cancelled progress that would invalidate an existing draft invoice commitment', function (): void {
    [$company, $account, $product, $billing, $warehouse, $location, $manager] = invoice83Fixture('INV83-PROGRESS');
    $order = invoice83CreateOrder($this, $company, $manager, $account, $product, '5');
    $line = $order->lines()->firstOrFail();
    $progress = app(SalesOrderProgressService::class);

    invoice83Post($this, $company, $manager, $order, $line, $billing, $warehouse, $location, '4')->assertRedirect();

    expect(fn () => DB::transaction(fn (): SalesOrderLineProgressEffect => $progress->record(
        invoice83Identity($company, 'conflicting-cancel', 'progress.cancel'),
        (int) $line->getKey(),
        SalesOrderProgressType::Cancelled,
        '2',
    )))->toThrow(ValidationException::class);

    expect(fn () => DB::transaction(fn (): SalesOrderLineProgressEffect => $progress->record(
        invoice83Identity($company, 'conflicting-invoice', 'progress.invoice'),
        (int) $line->getKey(),
        SalesOrderProgressType::Invoiced,
        '2',
    )))->toThrow(ValidationException::class);

    expect(SalesOrderLineProgressEffect::query()->count())->toBe(0);

    $capacity = SalesInvoiceOrderLineCapacity::query()
        ->where('sales_order_line_id', $line->getKey())
        ->firstOrFail();
    expect((string) $capacity->draft_quantity)->toBe('4.000000')
        ->and((string) $capacity->remaining_quantity)->toBe('1.000000');
});

it('moves linked draft commitment into invoiced progress on finalize and reverses it on cancel', function (): void {
    [$company, $account, $product, $billing, $warehouse, $location, $manager] = invoice83Fixture('INV83-LIFECYCLE');
    $order = invoice83CreateOrder($this, $company, $manager, $account, $product, '5');
    $line = $order->lines()->firstOrFail();

    invoice83Post($this, $company, $manager, $order, $line, $billing, $warehouse, $location, '3')->assertRedirect();
    $invoice = SalesInvoice::query()->where('company_id', $company->getKey())->latest('id')->firstOrFail();
    $invoiceLine = $invoice->lines()->firstOrFail();

    $draftCapacity = SalesInvoiceOrderLineCapacity::query()
        ->where('sales_order_line_id', $line->getKey())
        ->firstOrFail();
    expect((string) $draftCapacity->net_invoiced_quantity)->toBe('0.000000')
        ->and((string) $draftCapacity->draft_quantity)->toBe('3.000000')
        ->and((string) $draftCapacity->remaining_quantity)->toBe('2.000000');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('sales-invoices.finalize', $invoice->getKey()))
        ->assertRedirect(route('sales-invoices.show', $invoice->getKey()));

    $finalizedCapacity = SalesInvoiceOrderLineCapacity::query()
        ->where('sales_order_line_id', $line->getKey())
        ->firstOrFail();
    $original = SalesOrderLineProgressEffect::query()
        ->where('company_id', $company->getKey())
        ->where('sales_order_line_id', $line->getKey())
        ->where('source_type', 'sales_invoice_line')
        ->where('source_id', (string) $invoiceLine->getKey())
        ->where('effect_type', 'progress.invoice')
        ->firstOrFail();

    expect($invoice->refresh()->statusEnum())->toBe(SalesInvoiceStatus::Finalized)
        ->and((string) $finalizedCapacity->net_invoiced_quantity)->toBe('3.000000')
        ->and((string) $finalizedCapacity->draft_quantity)->toBe('0.000000')
        ->and((string) $finalizedCapacity->remaining_quantity)->toBe('2.000000')
        ->and((string) $original->quantity_delta)->toBe('3.000000')
        ->and(DB::table('stock_movements')->count())->toBe(0);

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('sales-invoices.cancel', $invoice->getKey()))
        ->assertRedirect(route('sales-invoices.show', $invoice->getKey()));

    $cancelledCapacity = SalesInvoiceOrderLineCapacity::query()
        ->where('sales_order_line_id', $line->getKey())
        ->firstOrFail();
    $reversal = SalesOrderLineProgressEffect::query()
        ->where('reversal_of_progress_effect_id', $original->getKey())
        ->firstOrFail();

    expect($invoice->refresh()->statusEnum())->toBe(SalesInvoiceStatus::Cancelled)
        ->and((string) $cancelledCapacity->net_invoiced_quantity)->toBe('0.000000')
        ->and((string) $cancelledCapacity->draft_quantity)->toBe('0.000000')
        ->and((string) $cancelledCapacity->remaining_quantity)->toBe('5.000000')
        ->and((string) $reversal->quantity_delta)->toBe('-3.000000')
        ->and((string) $reversal->source_type)->toBe('sales_invoice_line')
        ->and((string) $reversal->source_id)->toBe((string) $invoiceLine->getKey())
        ->and((string) $reversal->effect_type)->toBe('progress.invoice.reverse')
        ->and(DB::table('stock_movements')->count())->toBe(0);
});

it('rejects linked finalization at commit when exact invoice progress is missing', function (): void {
    [$company, $account, $product, $billing, $warehouse, $location, $manager] = invoice83Fixture('INV83-DB-LIFECYCLE');
    $order = invoice83CreateOrder($this, $company, $manager, $account, $product, '5');
    $line = $order->lines()->firstOrFail();

    invoice83Post($this, $company, $manager, $order, $line, $billing, $warehouse, $location, '3')->assertRedirect();
    $invoice = SalesInvoice::query()->where('company_id', $company->getKey())->latest('id')->firstOrFail();

    expect(fn () => DB::transaction(function () use ($company, $invoice): void {
        app(AccountTransactionPoster::class)->post(new PostAccountTransactionData(
            accountId: (int) $invoice->account_id,
            postingDate: '2026-08-27',
            signedAmount: (string) $invoice->gross_total,
            sourceEffect: new SourceEffectIdentity(
                (int) $company->getKey(),
                'sales_invoice',
                (string) $invoice->getKey(),
                'account.sales_invoice',
            ),
            memo: 'DB lifecycle testi',
        ));

        DB::table('sales_invoices')->where('id', $invoice->getKey())->update([
            'status' => SalesInvoiceStatus::Finalized->value,
            'finalized_at' => '2026-08-27 12:00:00+00',
            'updated_at' => now(),
        ]);
    }))->toThrow(PDOException::class);

    $capacity = SalesInvoiceOrderLineCapacity::query()
        ->where('sales_order_line_id', $line->getKey())
        ->firstOrFail();
    expect($invoice->refresh()->statusEnum())->toBe(SalesInvoiceStatus::Draft)
        ->and((string) $capacity->draft_quantity)->toBe('3.000000')
        ->and((string) $capacity->remaining_quantity)->toBe('2.000000')
        ->and(DB::table('account_transactions')->count())->toBe(0)
        ->and(SalesOrderLineProgressEffect::query()->count())->toBe(0);
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
        'tax_identity_type' => TaxIdentityType::Vkn,
        'tax_number' => '1234567890',
        'tax_office' => 'Mars VD',
        'book_currency_code' => 'TRY',
        'due_days' => 0,
        'discount_rate' => '0.000000',
        'risk_limit' => '0.000000',
    ]);
    $billing = AccountAddress::query()->create([
        'company_id' => $company->getKey(),
        'account_id' => $account->getKey(),
        'type' => AccountAddressType::Billing,
        'label' => 'Ana Fatura',
        'recipient_name' => 'Muhasebe',
        'line1' => 'Fatura Cad. 83',
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
    $warehouse = Warehouse::query()->create([
        'company_id' => $company->getKey(), 'code' => 'WH', 'name' => 'Ana Depo', 'is_active' => true,
    ]);
    $location = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(),
        'warehouse_id' => $warehouse->getKey(),
        'code' => 'LOC',
        'name' => 'Ana Konum',
        'is_active' => true,
    ]);

    foreach ([[DocumentType::SalesOrder, 'SO-'], [DocumentType::SalesInvoice, 'INV-']] as [$type, $prefix]) {
        DocumentSequence::query()->create([
            'company_id' => $company->getKey(),
            'document_type' => $type,
            'series_code' => 'default',
            'prefix' => $prefix,
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

    return [$company, $account, $product, $billing, $warehouse, $location, invoice83Actor($company)];
}

function invoice83CreateOrder(
    TestCase $test,
    Company $company,
    User $actor,
    Account $account,
    Product $product,
    string $quantity,
): SalesOrder {
    $test->actingAs($actor)->withSession(['active_company_id' => $company->getKey()])->post('/sales-orders', [
        'series_code' => 'default',
        'account_id' => $account->getKey(),
        'order_date' => '2026-08-27',
        'currency_code' => 'TRY',
        'document_discount_rate' => '0',
        'note' => null,
        'lines' => [[
            'product_id' => $product->getKey(),
            'description' => 'M8.3 invoice progress',
            'quantity' => $quantity,
            'unit_price' => '100',
            'price_basis' => 'net',
            'line_discount_rate' => '0',
            'tax_zero_reason_id' => null,
        ]],
    ])->assertRedirect();

    return SalesOrder::query()->where('company_id', $company->getKey())->latest('id')->firstOrFail();
}

function invoice83Post(
    TestCase $test,
    Company $company,
    User $manager,
    SalesOrder $order,
    SalesOrderLine $line,
    AccountAddress $billing,
    Warehouse $warehouse,
    WarehouseLocation $location,
    string $quantity,
): TestResponse {
    return $test->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])->post('/sales-invoices', [
        'series_code' => 'default',
        'mode' => SalesInvoiceMode::OrderLinked->value,
        'sales_order_id' => $order->getKey(),
        'source_billing_address_id' => $billing->getKey(),
        'invoice_date' => '2026-08-27',
        'lines' => [[
            'sales_order_line_id' => $line->getKey(),
            'quantity' => $quantity,
            'allocation_key' => $warehouse->getKey().':'.$location->getKey(),
        ]],
    ]);
}

function invoice83Identity(Company $company, string $sourceId, string $effectType): SourceEffectIdentity
{
    return new SourceEffectIdentity(
        companyId: (int) $company->getKey(),
        sourceType: 'sales-invoice.m83-test',
        sourceId: $sourceId,
        effectType: $effectType,
    );
}

function invoice83Actor(Company $company): User
{
    $user = User::query()->create([
        'name' => 'Invoice M8.3',
        'email' => strtolower((string) $company->code).'@invoice83.test',
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
        'code' => 'invoice-m83',
        'name' => 'Invoice M8.3',
        'is_active' => true,
    ]);
    foreach ([
        PermissionKey::SalesOrderView,
        PermissionKey::SalesOrderManage,
        PermissionKey::SalesInvoiceView,
        PermissionKey::SalesInvoiceManage,
    ] as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}
