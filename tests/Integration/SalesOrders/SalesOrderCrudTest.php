<?php

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\DocumentType;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\AuditEntry;
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
use App\Modules\SalesOrders\Models\SalesOrder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('creates a manual numbered order from server-authoritative calculation and freezes product tax snapshots', function (): void {
    [$company, $account, $product, $tax] = salesOrder61Fixture('SO61-A');
    $manager = salesOrder61Actor($company, [PermissionKey::SalesOrderView, PermissionKey::SalesOrderManage], 'manager');

    $response = $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-orders', [
            'series_code' => 'default',
            'account_id' => $account->getKey(),
            'order_date' => '2026-08-26',
            'currency_code' => 'TRY',
            'document_discount_rate' => '10',
            'net_total' => '0.000001',
            'tax_total' => '0.000001',
            'gross_total' => '0.000002',
            'lines' => [[
                'product_id' => $product->getKey(),
                'description' => 'Server order line',
                'quantity' => '2',
                'unit_price' => '100',
                'price_basis' => 'net',
                'line_discount_rate' => '0',
                'tax_zero_reason_id' => null,
                'gross_total' => '0.000001',
            ]],
        ]);

    $order = SalesOrder::query()->where('company_id', $company->getKey())->firstOrFail();
    $line = $order->lines()->firstOrFail();
    $response->assertRedirect('/sales-orders/'.$order->getKey());

    expect($order->number)->toBe('SO-0001')
        ->and($order->isManual())->toBeTrue()
        ->and((string) $order->base_net_total)->toBe('200.000000')
        ->and((string) $order->document_discount_total)->toBe('20.000000')
        ->and((string) $order->net_total)->toBe('180.000000')
        ->and((string) $order->tax_total)->toBe('36.000000')
        ->and((string) $order->gross_total)->toBe('216.000000')
        ->and((string) $line->product_code)->toBe('SKU')
        ->and((string) $line->product_name)->toBe('Ürün SO61-A')
        ->and((string) $line->tax_code)->toBe('KDV20')
        ->and((string) $line->tax_rate)->toBe('20.000000');

    $product->forceFill(['name' => 'Yeni Ürün Adı'])->save();
    $tax->forceFill(['code' => 'KDV-YENI'])->save();
    $line->refresh();

    expect((string) $line->product_name)->toBe('Ürün SO61-A')
        ->and((string) $line->tax_code)->toBe('KDV20');
});

it('recalculates manual draft edits without changing document identity and protects lineage at PostgreSQL', function (): void {
    [$company, $account, $product] = salesOrder61Fixture('SO61-B');
    $manager = salesOrder61Actor($company, [PermissionKey::SalesOrderView, PermissionKey::SalesOrderManage], 'manager');

    $payload = [
        'series_code' => 'default', 'account_id' => $account->getKey(), 'order_date' => '2026-08-26',
        'currency_code' => 'TRY', 'document_discount_rate' => '0', 'note' => null,
        'lines' => [[
            'product_id' => $product->getKey(), 'description' => null, 'quantity' => '1', 'unit_price' => '100',
            'price_basis' => 'net', 'line_discount_rate' => '0', 'tax_zero_reason_id' => null,
        ]],
    ];

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-orders', $payload)->assertRedirect();
    $order = SalesOrder::query()->firstOrFail();
    $number = (string) $order->number;
    $sequence = (int) $order->sequence_value;

    $payload['lines'][0]['quantity'] = '3';
    unset($payload['series_code']);
    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->put('/sales-orders/'.$order->getKey(), $payload)->assertRedirect('/sales-orders/'.$order->getKey());

    $order->refresh();
    expect($order->number)->toBe($number)
        ->and((int) $order->sequence_value)->toBe($sequence)
        ->and((string) $order->net_total)->toBe('300.000000')
        ->and((string) $order->gross_total)->toBe('360.000000')
        ->and($order->lines()->count())->toBe(1);

    expect(fn () => DB::table('sales_orders')->where('id', $order->getKey())->update(['number' => 'HACK-1']))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('sales_orders')->where('id', $order->getKey())->update(['source_quote_id' => 999, 'source_quote_revision_id' => 999]))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('sales_order_lines')->where('sales_order_id', $order->getKey())->update(['source_quote_revision_line_id' => 999]))
        ->toThrow(QueryException::class);
});

it('keeps immutable full draft snapshots across create and update audit history', function (): void {
    [$company, $account, $product] = salesOrder61Fixture('SO61-HISTORY');
    $manager = salesOrder61Actor($company, [PermissionKey::SalesOrderView, PermissionKey::SalesOrderManage], 'history-manager');

    $payload = [
        'series_code' => 'default',
        'account_id' => $account->getKey(),
        'order_date' => '2026-08-26',
        'currency_code' => 'TRY',
        'document_discount_rate' => '10',
        'note' => 'İlk not',
        'lines' => [[
            'product_id' => $product->getKey(),
            'description' => 'İlk satır',
            'quantity' => '2',
            'unit_price' => '100',
            'price_basis' => 'net',
            'line_discount_rate' => '0',
            'tax_zero_reason_id' => null,
        ]],
    ];

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-orders', $payload)->assertRedirect();

    $order = SalesOrder::query()->where('company_id', $company->getKey())->firstOrFail();
    $created = AuditEntry::query()
        ->where('company_id', $company->getKey())
        ->where('action', AuditAction::SalesOrderCreated->value)
        ->where('target_id', (string) $order->getKey())
        ->firstOrFail();

    expect($created->before_state)->toBeNull()
        ->and($created->after_state['number'])->toBe('SO-0001')
        ->and($created->after_state['document_discount_rate'])->toBe('10.000000')
        ->and($created->after_state['net_total'])->toBe('180.000000')
        ->and($created->after_state['note'])->toBe('İlk not')
        ->and($created->after_state['lines'])->toHaveCount(1)
        ->and($created->after_state['lines'][0]['product_code'])->toBe('SKU')
        ->and($created->after_state['lines'][0]['description'])->toBe('İlk satır')
        ->and($created->after_state['lines'][0]['quantity'])->toBe('2.000000')
        ->and($created->after_state['lines'][0]['tax_code'])->toBe('KDV20')
        ->and($created->after_state['lines'][0]['gross_total'])->toBe('216.000000');

    $payload['note'] = 'Güncel not';
    $payload['lines'][0]['description'] = 'Güncel satır';
    $payload['lines'][0]['quantity'] = '3';
    unset($payload['series_code']);

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->put('/sales-orders/'.$order->getKey(), $payload)->assertRedirect('/sales-orders/'.$order->getKey());

    $updated = AuditEntry::query()
        ->where('company_id', $company->getKey())
        ->where('action', AuditAction::SalesOrderUpdated->value)
        ->where('target_id', (string) $order->getKey())
        ->firstOrFail();
    $created->refresh();

    expect($created->after_state['lines'][0]['quantity'])->toBe('2.000000')
        ->and($created->after_state['lines'][0]['description'])->toBe('İlk satır')
        ->and($updated->before_state['note'])->toBe('İlk not')
        ->and($updated->before_state['lines'][0]['quantity'])->toBe('2.000000')
        ->and($updated->before_state['lines'][0]['description'])->toBe('İlk satır')
        ->and($updated->after_state['note'])->toBe('Güncel not')
        ->and($updated->after_state['lines'][0]['quantity'])->toBe('3.000000')
        ->and($updated->after_state['lines'][0]['description'])->toBe('Güncel satır')
        ->and($updated->after_state['net_total'])->toBe('270.000000')
        ->and($updated->after_state['gross_total'])->toBe('324.000000');

    expect(fn () => DB::table('audit_entries')->where('id', $created->getKey())->update(['action' => 'tampered']))
        ->toThrow(QueryException::class);
});

it('keeps order viewing separate from management, company scoped, and hides sales navigation without permission', function (): void {
    [$companyA] = salesOrder61Fixture('SO61-C-A');
    [$companyB, $accountB, $productB] = salesOrder61Fixture('SO61-C-B');
    $viewer = salesOrder61Actor($companyA, [PermissionKey::SalesOrderView], 'viewer');
    $noSales = salesOrder61Actor($companyA, [PermissionKey::AccountView], 'no-sales');
    $managerB = salesOrder61Actor($companyB, [PermissionKey::SalesOrderView, PermissionKey::SalesOrderManage], 'manager-b');

    $this->actingAs($managerB)->withSession(['active_company_id' => $companyB->getKey()])->post('/sales-orders', [
        'series_code' => 'default', 'account_id' => $accountB->getKey(), 'order_date' => '2026-08-26',
        'currency_code' => 'TRY', 'document_discount_rate' => '0',
        'lines' => [[
            'product_id' => $productB->getKey(), 'quantity' => '1', 'unit_price' => '100', 'price_basis' => 'net',
            'line_discount_rate' => '0', 'tax_zero_reason_id' => null,
        ]],
    ])->assertRedirect();
    $foreign = SalesOrder::query()->where('company_id', $companyB->getKey())->firstOrFail();

    $this->actingAs($viewer)->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/sales-orders')->assertOk()->assertDontSee((string) $foreign->number);
    $this->actingAs($viewer)->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/sales-orders/create')->assertForbidden();
    $this->actingAs($viewer)->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/sales-orders/'.$foreign->getKey())->assertNotFound();

    $this->actingAs($noSales)->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/workspace')->assertOk()->assertDontSee('Satış');
});

/** @return array{Company, Account, Product, Tax} */
function salesOrder61Fixture(string $code): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $account = Account::query()->create([
        'company_id' => $company->getKey(), 'code' => 'CUST', 'type' => AccountType::Customer, 'status' => AccountStatus::Active,
        'legal_name' => 'Müşteri '.$code, 'trade_name' => null, 'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null, 'tax_office' => null, 'book_currency_code' => 'TRY', 'due_days' => 0,
        'discount_rate' => '0.000000', 'risk_limit' => '0.000000',
    ]);
    $category = Category::query()->create(['company_id' => $company->getKey(), 'code' => 'CAT', 'name' => 'Kategori', 'is_active' => true]);
    $unit = Unit::query()->create(['company_id' => $company->getKey(), 'code' => 'ADET', 'name' => 'Adet', 'is_active' => true]);
    $tax = Tax::query()->create(['company_id' => $company->getKey(), 'code' => 'KDV20', 'name' => 'KDV %20', 'rate' => '20.000000', 'is_active' => true]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(), 'code' => 'SKU', 'status' => ProductStatus::Active, 'name' => 'Ürün '.$code,
        'category_id' => $category->getKey(), 'unit_id' => $unit->getKey(), 'tax_id' => $tax->getKey(),
        'sale_price_net' => '100.000000', 'purchase_price_net' => '60.000000',
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(), 'document_type' => DocumentType::SalesOrder, 'series_code' => 'default',
        'prefix' => 'SO-', 'padding' => 4, 'next_value' => 1, 'is_active' => true,
    ]);

    return [$company, $account, $product, $tax];
}

/** @param list<PermissionKey> $permissions */
function salesOrder61Actor(Company $company, array $permissions, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'Sales Order '.$suffix, 'email' => strtolower((string) $company->code).'-'.$suffix.'@orders.test',
        'password' => 'correct-password', 'status' => UserStatus::Active,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(), 'user_id' => $user->getKey(), 'is_active' => true, 'joined_at' => now(),
    ]);
    $role = Role::query()->create([
        'company_id' => $company->getKey(), 'code' => 'order-'.$suffix, 'name' => 'Order '.$suffix, 'is_active' => true,
    ]);
    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}
