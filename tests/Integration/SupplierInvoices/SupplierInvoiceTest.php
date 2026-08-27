<?php

use App\Foundation\Clock\Clock;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountTransaction;
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
use App\Modules\PurchaseOrders\Enums\PurchaseOrderStatus;
use App\Modules\PurchaseOrders\Models\PurchaseOrder;
use App\Modules\PurchaseOrders\Models\PurchaseOrderLine;
use App\Modules\Quotes\Pricing\PriceBasis;
use App\Modules\SupplierInvoices\Enums\SupplierInvoiceStatus;
use App\Modules\SupplierInvoices\Models\SupplierInvoice;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

it('finalizes a partial supplier invoice into exactly one creditor effect and purchase order invoice progress without stock', function (): void {
    [$company, $supplier, $product, $tax, $warehouse, $location, $manager] = supplierInvoice94Fixture('PINV94-A');
    $order = supplierInvoice94Order($company, $supplier, $product, $tax, $warehouse, $location, '5');
    $line = $order->lines()->firstOrFail();

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('supplier-invoices.store'), supplierInvoice94Payload($order, $line, '2'))
        ->assertRedirect();

    $invoice = SupplierInvoice::query()->where('company_id', $company->getKey())->firstOrFail();
    $invoiceLine = $invoice->lines()->firstOrFail();

    expect($invoice->statusEnum())->toBe(SupplierInvoiceStatus::Draft)
        ->and((string) $invoice->gross_total)->toBe('240.000000')
        ->and(AccountTransaction::query()->count())->toBe(0)
        ->and(DB::table('purchase_order_line_progress_effects')->count())->toBe(0)
        ->and(DB::table('stock_movements')->count())->toBe(0);

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('supplier-invoices.finalize', $invoice->getKey()))
        ->assertRedirect(route('supplier-invoices.show', $invoice->getKey()));

    $invoice->refresh();
    $accountEffect = AccountTransaction::query()
        ->where('company_id', $company->getKey())
        ->where('source_type', 'supplier_invoice')
        ->where('source_id', (string) $invoice->getKey())
        ->where('effect_type', 'account.supplier_invoice')
        ->firstOrFail();
    $progress = DB::table('purchase_order_line_progress')
        ->where('company_id', $company->getKey())
        ->where('purchase_order_line_id', $line->getKey())
        ->first();

    expect($invoice->statusEnum())->toBe(SupplierInvoiceStatus::Finalized)
        ->and((string) $accountEffect->signed_amount)->toBe('-240.000000')
        ->and((int) $accountEffect->account_id)->toBe((int) $supplier->getKey())
        ->and($accountEffect->posting_date->format('Y-m-d'))->toBe('2026-08-27')
        ->and((string) $progress->net_invoiced_quantity)->toBe('2.000000')
        ->and((string) $progress->invoice_remaining_quantity)->toBe('3.000000')
        ->and(DB::table('purchase_order_line_progress_effects')
            ->where('source_type', 'supplier_invoice_line')
            ->where('source_id', (string) $invoiceLine->getKey())
            ->where('effect_type', 'progress.invoice')
            ->count())->toBe(1)
        ->and(DB::table('stock_movements')->count())->toBe(0);

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('supplier-invoices.finalize', $invoice->getKey()))
        ->assertRedirect();

    expect(AccountTransaction::query()->count())->toBe(1)
        ->and(DB::table('purchase_order_line_progress_effects')->count())->toBe(1)
        ->and(DB::table('stock_movements')->count())->toBe(0);
});

it('blocks over invoicing on finalization and rolls the supplier account effect back atomically', function (): void {
    [$company, $supplier, $product, $tax, $warehouse, $location, $manager] = supplierInvoice94Fixture('PINV94-B');
    $order = supplierInvoice94Order($company, $supplier, $product, $tax, $warehouse, $location, '5');
    $line = $order->lines()->firstOrFail();

    foreach ([1, 2] as $index) {
        $payload = supplierInvoice94Payload($order, $line, '4');
        $payload['note'] = 'supplier invoice '.$index;
        $this->actingAs($manager)
            ->withSession(['active_company_id' => $company->getKey()])
            ->post(route('supplier-invoices.store'), $payload)
            ->assertRedirect();
    }

    $invoices = SupplierInvoice::query()->orderBy('id')->get();
    expect($invoices)->toHaveCount(2);

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('supplier-invoices.finalize', $invoices[0]->getKey()))
        ->assertRedirect();

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('supplier-invoices.finalize', $invoices[1]->getKey()))
        ->assertSessionHasErrors('quantity_delta');

    $progress = DB::table('purchase_order_line_progress')
        ->where('company_id', $company->getKey())
        ->where('purchase_order_line_id', $line->getKey())
        ->first();

    expect($invoices[0]->refresh()->statusEnum())->toBe(SupplierInvoiceStatus::Finalized)
        ->and($invoices[1]->refresh()->statusEnum())->toBe(SupplierInvoiceStatus::Draft)
        ->and(AccountTransaction::query()->count())->toBe(1)
        ->and(DB::table('purchase_order_line_progress_effects')->count())->toBe(1)
        ->and((string) $progress->net_invoiced_quantity)->toBe('4.000000')
        ->and((string) $progress->invoice_remaining_quantity)->toBe('1.000000')
        ->and(DB::table('stock_movements')->count())->toBe(0);
});

it('rejects raw supplier invoice finalization without exact account and purchase order progress effects', function (): void {
    [$company, $supplier, $product, $tax, $warehouse, $location, $manager] = supplierInvoice94Fixture('PINV94-C');
    $order = supplierInvoice94Order($company, $supplier, $product, $tax, $warehouse, $location, '5');
    $line = $order->lines()->firstOrFail();

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('supplier-invoices.store'), supplierInvoice94Payload($order, $line, '2'))
        ->assertRedirect();

    $invoice = SupplierInvoice::query()->firstOrFail();

    expect(fn () => DB::table('supplier_invoices')->where('id', $invoice->getKey())->update([
        'status' => 'finalized',
        'finalized_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect($invoice->refresh()->statusEnum())->toBe(SupplierInvoiceStatus::Draft)
        ->and(AccountTransaction::query()->count())->toBe(0)
        ->and(DB::table('purchase_order_line_progress_effects')->count())->toBe(0)
        ->and(DB::table('stock_movements')->count())->toBe(0);
});

it('freezes finalized supplier invoice header and lines at the database boundary', function (): void {
    [$company, $supplier, $product, $tax, $warehouse, $location, $manager] = supplierInvoice94Fixture('PINV94-D');
    $order = supplierInvoice94Order($company, $supplier, $product, $tax, $warehouse, $location, '5');
    $line = $order->lines()->firstOrFail();

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('supplier-invoices.store'), supplierInvoice94Payload($order, $line, '2'))
        ->assertRedirect();
    $invoice = SupplierInvoice::query()->firstOrFail();
    $invoiceLine = $invoice->lines()->firstOrFail();

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('supplier-invoices.finalize', $invoice->getKey()))
        ->assertRedirect();

    expect(fn () => DB::table('supplier_invoices')->where('id', $invoice->getKey())->update(['note' => 'raw tamper']))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('supplier_invoice_lines')->where('id', $invoiceLine->getKey())->update(['quantity' => '1.000000']))
        ->toThrow(QueryException::class);
});

/** @return array{Company,Account,Product,Tax,Warehouse,WarehouseLocation,User} */
function supplierInvoice94Fixture(string $code): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $supplier = Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'SUP',
        'type' => AccountType::Supplier,
        'status' => AccountStatus::Active,
        'legal_name' => 'Tedarikçi '.$code,
        'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null,
        'tax_office' => null,
        'book_currency_code' => 'TRY',
        'due_days' => 0,
        'discount_rate' => '0.000000',
        'risk_limit' => '0.000000',
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
        'sale_price_net' => '120.000000',
        'purchase_price_net' => '100.000000',
    ]);
    $warehouse = Warehouse::query()->create([
        'company_id' => $company->getKey(), 'code' => 'WH', 'name' => 'Ana Depo', 'is_active' => true,
    ]);
    $location = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(), 'warehouse_id' => $warehouse->getKey(),
        'code' => 'A1', 'name' => 'A1', 'is_active' => true,
    ]);

    DocumentSequence::query()->create([
        'company_id' => $company->getKey(),
        'document_type' => DocumentType::SupplierInvoice,
        'series_code' => 'default',
        'prefix' => 'PINV-',
        'padding' => 4,
        'next_value' => 1,
        'is_active' => true,
    ]);
    PostingPeriod::query()->create([
        'company_id' => $company->getKey(),
        'code' => '2026-08',
        'name' => 'Ağustos 2026',
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-31',
        'status' => PostingPeriodStatus::Open,
        'closed_at' => null,
    ]);

    return [$company, $supplier, $product, $tax, $warehouse, $location, supplierInvoice94Actor($company, $code)];
}

function supplierInvoice94Order(
    Company $company,
    Account $supplier,
    Product $product,
    Tax $tax,
    Warehouse $warehouse,
    WarehouseLocation $location,
    string $quantity,
): PurchaseOrder {
    $totals = DB::selectOne(
        'SELECT CAST(CAST(? AS numeric) * 100 AS numeric(20,6))::text AS base, '
        .'CAST(CAST(? AS numeric) * 20 AS numeric(20,6))::text AS tax, '
        .'CAST(CAST(? AS numeric) * 120 AS numeric(20,6))::text AS gross',
        [$quantity, $quantity, $quantity],
    );
    if ($totals === null) {
        throw new RuntimeException('Purchase order fixture totals could not be calculated.');
    }

    $order = PurchaseOrder::query()->create([
        'company_id' => $company->getKey(),
        'account_id' => $supplier->getKey(),
        'number' => 'PO-'.$company->code,
        'series_code' => 'default',
        'sequence_value' => 1,
        'status' => PurchaseOrderStatus::Draft,
        'order_date' => '2026-08-27',
        'currency_code' => 'TRY',
        'document_discount_rate' => '0.000000',
        'base_net_total' => (string) $totals->base,
        'line_discount_total' => '0.000000',
        'document_discount_total' => '0.000000',
        'net_total' => (string) $totals->base,
        'tax_total' => (string) $totals->tax,
        'gross_total' => (string) $totals->gross,
        'note' => null,
    ]);

    $order->lines()->create([
        'company_id' => $company->getKey(),
        'logical_line_key' => (string) Str::uuid(),
        'position' => 1,
        'product_id' => $product->getKey(),
        'warehouse_id' => $warehouse->getKey(),
        'location_id' => $location->getKey(),
        'product_code' => $product->code,
        'product_name' => $product->name,
        'description' => 'Satınalma satırı',
        'quantity' => $quantity,
        'price_basis' => PriceBasis::Net,
        'unit_price' => '100.000000',
        'line_discount_rate' => '0.000000',
        'tax_id' => $tax->getKey(),
        'tax_code' => $tax->code,
        'tax_rate' => '20.000000',
        'tax_is_zeroed' => false,
        'tax_zero_reason_id' => null,
        'tax_zero_reason_code' => null,
        'base_net' => (string) $totals->base,
        'line_discount_net' => '0.000000',
        'document_discount_net' => '0.000000',
        'net_total' => (string) $totals->base,
        'tax_total' => (string) $totals->tax,
        'gross_total' => (string) $totals->gross,
    ]);

    return $order->load('lines.progress');
}

/** @return array<string, mixed> */
function supplierInvoice94Payload(PurchaseOrder $order, PurchaseOrderLine $line, string $quantity): array
{
    return [
        'series_code' => 'default',
        'purchase_order_id' => $order->getKey(),
        'invoice_date' => '2026-08-27',
        'note' => null,
        'lines' => [[
            'purchase_order_line_id' => $line->getKey(),
            'quantity' => $quantity,
        ]],
    ];
}

function supplierInvoice94Actor(Company $company, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'Supplier Invoice '.$suffix,
        'email' => strtolower((string) $company->code).'-'.strtolower($suffix).'@supplier-invoice.test',
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
        'code' => 'supplier-invoice-'.strtolower($suffix),
        'name' => 'Supplier Invoice '.$suffix,
        'is_active' => true,
    ]);

    foreach ([PermissionKey::SupplierInvoiceView, PermissionKey::SupplierInvoiceManage, PermissionKey::PurchaseOrderView] as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}
