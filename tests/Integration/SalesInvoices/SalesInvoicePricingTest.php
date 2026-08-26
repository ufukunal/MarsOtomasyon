<?php

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
use App\Modules\Core\Models\TaxZeroReason;
use App\Modules\Core\Models\User;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\SalesInvoices\Enums\SalesInvoiceMode;
use App\Modules\SalesInvoices\Models\SalesInvoice;
use App\Modules\SalesOrders\Models\SalesOrder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('reuses the deterministic calculator for direct gross pricing and ignores spoofed totals', function (): void {
    [$company, $account, $product, $billing, $warehouse, $location] = invoice82Fixture('INV82-DIR');
    $actor = invoice82Actor($company, [PermissionKey::SalesInvoiceView, PermissionKey::SalesInvoiceManage], 'direct');

    $this->actingAs($actor)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-invoices', [
            'mode' => SalesInvoiceMode::Direct->value,
            'account_id' => $account->getKey(),
            'source_billing_address_id' => $billing->getKey(),
            'invoice_date' => '2026-08-27',
            'document_discount_rate' => '5',
            'base_net_total' => '999999',
            'net_total' => '999999',
            'tax_total' => '999999',
            'gross_total' => '999999',
            'lines' => [[
                'product_id' => $product->getKey(),
                'quantity' => '2',
                'allocation_key' => $warehouse->getKey().':'.$location->getKey(),
                'unit_price' => '120',
                'price_basis' => 'gross',
                'line_discount_rate' => '10',
            ]],
        ])->assertRedirect();

    $invoice = SalesInvoice::query()->where('company_id', $company->getKey())->firstOrFail();
    $line = $invoice->lines()->firstOrFail();

    expect((string) $invoice->document_discount_rate)->toBe('5.000000')
        ->and((string) $invoice->base_net_total)->toBe('200.000000')
        ->and((string) $invoice->line_discount_total)->toBe('20.000000')
        ->and((string) $invoice->document_discount_total)->toBe('9.000000')
        ->and((string) $invoice->net_total)->toBe('171.000000')
        ->and((string) $invoice->tax_total)->toBe('34.200000')
        ->and((string) $invoice->gross_total)->toBe('205.200000')
        ->and((string) $line->quantity)->toBe('2.000000')
        ->and((string) $line->unit_price)->toBe('120.000000')
        ->and($line->price_basis->value)->toBe('gross')
        ->and((string) $line->line_discount_rate)->toBe('10.000000')
        ->and((string) $line->tax_rate)->toBe('20.000000')
        ->and((string) $line->net_total)->toBe('171.000000')
        ->and((string) $line->tax_total)->toBe('34.200000')
        ->and((string) $line->gross_total)->toBe('205.200000');

    expect(fn () => DB::table('sales_invoices')->where('id', $invoice->getKey())->update([
        'gross_total' => '999.000000',
    ]))->toThrow(QueryException::class);

    $this->actingAs($actor)->withSession(['active_company_id' => $company->getKey()])
        ->get('/sales-invoices/'.$invoice->getKey())
        ->assertOk()
        ->assertSee('205.200000')
        ->assertSee('34.200000');
});

it('preserves Decimal6 half-up rounding at the invoice integration boundary', function (): void {
    [$company, $account, $product, $billing, $warehouse, $location] = invoice82Fixture('INV82-RND');
    $actor = invoice82Actor($company, [PermissionKey::SalesInvoiceView, PermissionKey::SalesInvoiceManage], 'rounding');

    $this->actingAs($actor)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-invoices', [
            'mode' => SalesInvoiceMode::Direct->value,
            'account_id' => $account->getKey(),
            'source_billing_address_id' => $billing->getKey(),
            'invoice_date' => '2026-08-27',
            'document_discount_rate' => '0',
            'lines' => [[
                'product_id' => $product->getKey(),
                'quantity' => '3',
                'allocation_key' => $warehouse->getKey().':'.$location->getKey(),
                'unit_price' => '0.333333',
                'price_basis' => 'net',
                'line_discount_rate' => '0',
            ]],
        ])->assertRedirect();

    $invoice = SalesInvoice::query()->where('company_id', $company->getKey())->firstOrFail();
    expect((string) $invoice->base_net_total)->toBe('0.999999')
        ->and((string) $invoice->net_total)->toBe('0.999999')
        ->and((string) $invoice->tax_total)->toBe('0.200000')
        ->and((string) $invoice->gross_total)->toBe('1.199999');
});

it('requires a canonical active zero reason when direct tax is explicitly zeroed and does not consume numbering on failure', function (): void {
    [$company, $account, $product, $billing, $warehouse, $location] = invoice82Fixture('INV82-ZERO');
    $actor = invoice82Actor($company, [PermissionKey::SalesInvoiceView, PermissionKey::SalesInvoiceManage], 'zero');
    $reason = TaxZeroReason::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'ISTISNA',
        'name' => 'İstisna',
        'is_active' => true,
    ]);

    $payload = [
        'mode' => SalesInvoiceMode::Direct->value,
        'account_id' => $account->getKey(),
        'source_billing_address_id' => $billing->getKey(),
        'invoice_date' => '2026-08-27',
        'lines' => [[
            'product_id' => $product->getKey(),
            'quantity' => '1',
            'allocation_key' => $warehouse->getKey().':'.$location->getKey(),
            'unit_price' => '100',
            'price_basis' => 'net',
            'line_discount_rate' => '0',
            'tax_is_zeroed' => '1',
            'tax_zero_reason_id' => $reason->getKey(),
        ]],
    ];

    $this->actingAs($actor)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-invoices', $payload)->assertRedirect();

    $invoice = SalesInvoice::query()->where('company_id', $company->getKey())->firstOrFail();
    $line = $invoice->lines()->firstOrFail();
    expect((string) $line->tax_rate)->toBe('0.000000')
        ->and((bool) $line->tax_is_zeroed)->toBeTrue()
        ->and((int) $line->tax_zero_reason_id)->toBe((int) $reason->getKey())
        ->and((string) $line->tax_zero_reason_code)->toBe('ISTISNA')
        ->and((string) $invoice->tax_total)->toBe('0.000000')
        ->and((string) $invoice->gross_total)->toBe('100.000000');

    unset($payload['lines'][0]['tax_zero_reason_id']);
    $this->actingAs($actor)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-invoices', $payload)->assertSessionHasErrors('lines.0.tax_zero_reason_id');

    $sequence = DocumentSequence::query()
        ->where('company_id', $company->getKey())
        ->where('document_type', DocumentType::SalesInvoice->value)
        ->firstOrFail();
    expect((int) $sequence->next_value)->toBe(2)
        ->and(SalesInvoice::query()->where('company_id', $company->getKey())->count())->toBe(1);
});

it('inherits linked commercial terms from the source order snapshot and blocks application or raw SQL overrides', function (): void {
    [$company, $account, $product, $billing, $warehouse, $location, $tax] = invoice82Fixture('INV82-LINK', includeTax: true);
    $actor = invoice82Actor($company, [
        PermissionKey::SalesOrderView,
        PermissionKey::SalesOrderManage,
        PermissionKey::SalesInvoiceView,
        PermissionKey::SalesInvoiceManage,
    ], 'linked');

    $this->actingAs($actor)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-orders', [
            'series_code' => 'default',
            'account_id' => $account->getKey(),
            'order_date' => '2026-08-27',
            'currency_code' => 'TRY',
            'document_discount_rate' => '5',
            'lines' => [[
                'product_id' => $product->getKey(),
                'description' => 'Linked commercial snapshot',
                'quantity' => '3',
                'unit_price' => '120',
                'price_basis' => 'gross',
                'line_discount_rate' => '10',
                'tax_zero_reason_id' => null,
            ]],
        ])->assertRedirect();

    $order = SalesOrder::query()->where('company_id', $company->getKey())->firstOrFail();
    $sourceLine = $order->lines()->firstOrFail();

    $tax->forceFill(['rate' => '18.000000'])->save();

    $payload = [
        'mode' => SalesInvoiceMode::OrderLinked->value,
        'sales_order_id' => $order->getKey(),
        'source_billing_address_id' => $billing->getKey(),
        'invoice_date' => '2026-08-27',
        'lines' => [[
            'sales_order_line_id' => $sourceLine->getKey(),
            'quantity' => '1',
            'allocation_key' => $warehouse->getKey().':'.$location->getKey(),
        ]],
    ];

    $this->actingAs($actor)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-invoices', $payload)->assertRedirect();

    $invoice = SalesInvoice::query()->where('company_id', $company->getKey())->firstOrFail();
    $line = $invoice->lines()->firstOrFail();
    expect((string) $invoice->document_discount_rate)->toBe('5.000000')
        ->and((string) $line->unit_price)->toBe('120.000000')
        ->and($line->price_basis->value)->toBe('gross')
        ->and((string) $line->line_discount_rate)->toBe('10.000000')
        ->and((string) $line->tax_rate)->toBe('20.000000')
        ->and((string) $line->base_net)->toBe('100.000000')
        ->and((string) $line->line_discount_net)->toBe('10.000000')
        ->and((string) $line->document_discount_net)->toBe('4.500000')
        ->and((string) $line->net_total)->toBe('85.500000')
        ->and((string) $line->tax_total)->toBe('17.100000')
        ->and((string) $line->gross_total)->toBe('102.600000');

    $payload['lines'][0]['unit_price'] = '999';
    $this->actingAs($actor)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-invoices', $payload)->assertSessionHasErrors('lines.0');

    expect(fn () => DB::table('sales_invoice_lines')->where('id', $line->getKey())->update([
        'unit_price' => '999.000000',
    ]))->toThrow(QueryException::class);
    expect(fn () => DB::table('sales_invoices')->where('id', $invoice->getKey())->update([
        'document_discount_rate' => '6.000000',
    ]))->toThrow(QueryException::class);

    expect(SalesInvoice::query()->where('company_id', $company->getKey())->count())->toBe(1);
});

/**
 * @return array{Company,Account,Product,AccountAddress,Warehouse,WarehouseLocation}|array{Company,Account,Product,AccountAddress,Warehouse,WarehouseLocation,Tax}
 */
function invoice82Fixture(string $code, bool $includeTax = false): array
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
        'line1' => 'Fatura Cad. 82',
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
        'company_id' => $company->getKey(), 'warehouse_id' => $warehouse->getKey(),
        'code' => 'LOC', 'name' => 'Ana Konum', 'is_active' => true,
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

    $base = [$company, $account, $product, $billing, $warehouse, $location];
    if ($includeTax) {
        $base[] = $tax;
    }

    return $base;
}

/** @param list<PermissionKey> $permissions */
function invoice82Actor(Company $company, array $permissions, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'Invoice82 '.$suffix,
        'email' => strtolower((string) $company->code).'-'.$suffix.'@invoice82.test',
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
        'code' => 'invoice82-'.$suffix,
        'name' => 'Invoice82 '.$suffix,
        'is_active' => true,
    ]);
    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}
