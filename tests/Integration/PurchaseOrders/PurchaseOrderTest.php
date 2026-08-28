<?php

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
use App\Modules\Core\Models\User;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\PurchaseOrders\Actions\PurchaseOrderLifecycle;
use App\Modules\PurchaseOrders\Models\PurchaseOrder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('creates a supplier order from server-authoritative totals and initializes independent remaining projections', function (): void {
    [$company, $supplier, , $product] = purchaseOrder91Fixture('PO91-A');
    $manager = purchaseOrder91Actor($company, [PermissionKey::PurchaseOrderView, PermissionKey::PurchaseOrderManage], 'manager-a');

    $response = $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/purchase-orders', [
            'series_code' => 'default',
            'account_id' => $supplier->getKey(),
            'order_date' => '2026-08-27',
            'currency_code' => 'TRY',
            'document_discount_rate' => '10',
            'net_total' => '0.000001',
            'tax_total' => '0.000001',
            'gross_total' => '0.000002',
            'lines' => [[
                'product_id' => $product->getKey(),
                'description' => 'Tedarik satırı',
                'quantity' => '5',
                'unit_price' => '100',
                'price_basis' => 'net',
                'line_discount_rate' => '0',
                'tax_zero_reason_id' => null,
                'gross_total' => '0.000001',
            ]],
        ]);

    $order = PurchaseOrder::query()->where('company_id', $company->getKey())->firstOrFail();
    $line = $order->lines()->firstOrFail();
    $progress = DB::table('purchase_order_line_progress')
        ->where('company_id', $company->getKey())
        ->where('purchase_order_line_id', $line->getKey())
        ->first();

    $response->assertRedirect('/purchase-orders/'.$order->getKey());
    expect($order->number)->toBe('PO-0001')
        ->and((string) $order->base_net_total)->toBe('500.000000')
        ->and((string) $order->document_discount_total)->toBe('50.000000')
        ->and((string) $order->net_total)->toBe('450.000000')
        ->and((string) $order->tax_total)->toBe('90.000000')
        ->and((string) $order->gross_total)->toBe('540.000000')
        ->and((string) $line->product_code)->toBe('SKU')
        ->and((string) $line->product_name)->toBe('Ürün PO91-A')
        ->and((string) $progress->ordered_quantity)->toBe('5.000000')
        ->and((string) $progress->net_received_quantity)->toBe('0.000000')
        ->and((string) $progress->net_invoiced_quantity)->toBe('0.000000')
        ->and((string) $progress->receive_remaining_quantity)->toBe('5.000000')
        ->and((string) $progress->invoice_remaining_quantity)->toBe('5.000000');
});

it('requires an active supplier or mixed account at both application and PostgreSQL boundaries', function (): void {
    [$company, $supplier, $customer, $product] = purchaseOrder91Fixture('PO91-B');
    $manager = purchaseOrder91Actor($company, [PermissionKey::PurchaseOrderView, PermissionKey::PurchaseOrderManage], 'manager-b');

    $payload = purchaseOrder91Payload($customer, $product);
    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/purchase-orders', $payload)
        ->assertSessionHasErrors('account_id');
    expect(PurchaseOrder::query()->count())->toBe(0);

    $payload['account_id'] = $supplier->getKey();
    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/purchase-orders', $payload)
        ->assertRedirect();

    $order = PurchaseOrder::query()->firstOrFail();
    expect(fn () => DB::table('purchase_orders')->where('id', $order->getKey())->update(['account_id' => $customer->getKey()]))
        ->toThrow(QueryException::class);
});

it('tracks receipt and invoice remaining independently and blocks over-progress at PostgreSQL', function (): void {
    [$company, $supplier, , $product] = purchaseOrder91Fixture('PO91-C');
    $manager = purchaseOrder91Actor($company, [PermissionKey::PurchaseOrderView, PermissionKey::PurchaseOrderManage], 'manager-c');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/purchase-orders', purchaseOrder91Payload($supplier, $product, '5'))
        ->assertRedirect();

    $order = PurchaseOrder::query()->firstOrFail();
    $line = $order->lines()->firstOrFail();
    app(PurchaseOrderLifecycle::class)->open((int) $company->getKey(), (int) $order->getKey(), (int) $manager->getKey());
    $order->refresh();

    purchaseOrder91Progress($order, (int) $line->getKey(), 'received', '2.000000', 'goods_receipt_line', 'gr-1', 'progress.received', 'a');
    purchaseOrder91Progress($order, (int) $line->getKey(), 'invoiced', '3.000000', 'supplier_invoice_line', 'si-1', 'progress.invoiced', 'b');

    $progress = DB::table('purchase_order_line_progress')->where('purchase_order_line_id', $line->getKey())->first();
    expect((string) $progress->net_received_quantity)->toBe('2.000000')
        ->and((string) $progress->net_invoiced_quantity)->toBe('3.000000')
        ->and((string) $progress->receive_remaining_quantity)->toBe('3.000000')
        ->and((string) $progress->invoice_remaining_quantity)->toBe('2.000000');

    expect(fn () => purchaseOrder91Progress($order, (int) $line->getKey(), 'received', '4.000000', 'goods_receipt_line', 'gr-2', 'progress.received', 'c'))
        ->toThrow(QueryException::class);
    expect(fn () => purchaseOrder91Progress($order, (int) $line->getKey(), 'invoiced', '3.000000', 'supplier_invoice_line', 'si-2', 'progress.invoiced', 'd'))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('purchase_order_line_progress_effects')->where('source_id', 'gr-1')->update(['quantity_delta' => '1.000000']))
        ->toThrow(QueryException::class);
});

it('freezes header and line mutation after the first progress effect', function (): void {
    [$company, $supplier, , $product] = purchaseOrder91Fixture('PO91-D');
    $manager = purchaseOrder91Actor($company, [PermissionKey::PurchaseOrderView, PermissionKey::PurchaseOrderManage], 'manager-d');

    $payload = purchaseOrder91Payload($supplier, $product, '5');
    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/purchase-orders', $payload)
        ->assertRedirect();

    $order = PurchaseOrder::query()->firstOrFail();
    $line = $order->lines()->firstOrFail();
    app(PurchaseOrderLifecycle::class)->open((int) $company->getKey(), (int) $order->getKey(), (int) $manager->getKey());
    $order->refresh();
    purchaseOrder91Progress($order, (int) $line->getKey(), 'received', '1.000000', 'goods_receipt_line', 'gr-lock', 'progress.received', 'e');

    expect(fn () => DB::table('purchase_orders')->where('id', $order->getKey())->update(['note' => 'raw tamper']))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('purchase_order_lines')->where('id', $line->getKey())->update(['quantity' => '6.000000']))
        ->toThrow(QueryException::class);

    unset($payload['series_code']);
    $payload['note'] = 'application tamper';
    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->put('/purchase-orders/'.$order->getKey(), $payload)
        ->assertSessionHasErrors('purchase_order');
});

it('returns purchase price from product search and keeps view permission separate from management', function (): void {
    [$company, , , $product] = purchaseOrder91Fixture('PO91-E');
    $manager = purchaseOrder91Actor($company, [PermissionKey::PurchaseOrderView, PermissionKey::PurchaseOrderManage], 'manager-e');
    $viewer = purchaseOrder91Actor($company, [PermissionKey::PurchaseOrderView], 'viewer-e');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->getJson('/purchase-orders/product-search?q=SKU')
        ->assertOk()
        ->assertJsonPath('data.0.id', (int) $product->getKey())
        ->assertJsonPath('data.0.purchase_price_net', '60.000000');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/purchase-orders')->assertOk();
    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/purchase-orders/create')->assertForbidden();
});

/** @return array{Company, Account, Account, Product, Tax} */
function purchaseOrder91Fixture(string $code): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $supplier = purchaseOrder91Account($company, 'SUP', AccountType::Supplier, 'Tedarikçi '.$code);
    $customer = purchaseOrder91Account($company, 'CUST', AccountType::Customer, 'Müşteri '.$code);
    $category = Category::query()->create(['company_id' => $company->getKey(), 'code' => 'CAT', 'name' => 'Kategori', 'is_active' => true]);
    $unit = Unit::query()->create(['company_id' => $company->getKey(), 'code' => 'ADET', 'name' => 'Adet', 'is_active' => true]);
    $tax = Tax::query()->create(['company_id' => $company->getKey(), 'code' => 'KDV20', 'name' => 'KDV %20', 'rate' => '20.000000', 'is_active' => true]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(), 'code' => 'SKU', 'status' => ProductStatus::Active, 'name' => 'Ürün '.$code,
        'category_id' => $category->getKey(), 'unit_id' => $unit->getKey(), 'tax_id' => $tax->getKey(),
        'sale_price_net' => '100.000000', 'purchase_price_net' => '60.000000',
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(), 'document_type' => DocumentType::PurchaseOrder, 'series_code' => 'default',
        'prefix' => 'PO-', 'padding' => 4, 'next_value' => 1, 'is_active' => true,
    ]);

    return [$company, $supplier, $customer, $product, $tax];
}

function purchaseOrder91Account(Company $company, string $code, AccountType $type, string $name): Account
{
    return Account::query()->create([
        'company_id' => $company->getKey(), 'code' => $code, 'type' => $type, 'status' => AccountStatus::Active,
        'legal_name' => $name, 'trade_name' => null, 'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null, 'tax_office' => null, 'book_currency_code' => 'TRY', 'due_days' => 0,
        'discount_rate' => '0.000000', 'risk_limit' => '0.000000',
    ]);
}

/** @return array<string, mixed> */
function purchaseOrder91Payload(Account $supplier, Product $product, string $quantity = '1'): array
{
    return [
        'series_code' => 'default',
        'account_id' => $supplier->getKey(),
        'order_date' => '2026-08-27',
        'currency_code' => 'TRY',
        'document_discount_rate' => '0',
        'note' => null,
        'lines' => [[
            'product_id' => $product->getKey(),
            'description' => null,
            'quantity' => $quantity,
            'unit_price' => '100',
            'price_basis' => 'net',
            'line_discount_rate' => '0',
            'tax_zero_reason_id' => null,
        ]],
    ];
}

function purchaseOrder91Progress(
    PurchaseOrder $order,
    int $lineId,
    string $type,
    string $quantity,
    string $sourceType,
    string $sourceId,
    string $effectType,
    string $keySeed,
): void {
    DB::table('purchase_order_line_progress_effects')->insert([
        'company_id' => $order->company_id,
        'purchase_order_id' => $order->getKey(),
        'purchase_order_line_id' => $lineId,
        'progress_type' => $type,
        'quantity_delta' => $quantity,
        'operation_key' => str_repeat($keySeed, 64),
        'request_fingerprint' => hash('sha256', implode('|', [$sourceType, $sourceId, $effectType, $quantity])),
        'source_type' => $sourceType,
        'source_id' => $sourceId,
        'effect_type' => $effectType,
        'occurred_at' => now(),
        'created_at' => now(),
    ]);
}

/** @param list<PermissionKey> $permissions */
function purchaseOrder91Actor(Company $company, array $permissions, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'Purchase Order '.$suffix,
        'email' => strtolower((string) $company->code).'-'.$suffix.'@purchase.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(), 'user_id' => $user->getKey(), 'is_active' => true, 'joined_at' => now(),
    ]);
    $role = Role::query()->create([
        'company_id' => $company->getKey(), 'code' => 'purchase-'.$suffix, 'name' => 'Purchase '.$suffix, 'is_active' => true,
    ]);
    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}
