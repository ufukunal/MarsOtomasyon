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
use App\Modules\Core\Models\TaxZeroReason;
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

it('zeroes a taxable order line through the existing calculator and snapshots the override reason', function (): void {
    [$company, $account, $product, $reason, $manager] = m66TaxFixture('M66-ZERO', '20.000000');

    $response = $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-orders', m66TaxPayload($account, $product, [
            'tax_is_zeroed' => '1',
            'tax_zero_reason_id' => $reason->getKey(),
            'quantity' => '2',
        ]));

    $order = SalesOrder::query()->where('company_id', $company->getKey())->firstOrFail();
    $line = $order->lines()->firstOrFail();
    $created = AuditEntry::query()
        ->where('company_id', $company->getKey())
        ->where('action', AuditAction::SalesOrderCreated->value)
        ->where('target_id', (string) $order->getKey())
        ->firstOrFail();

    $response->assertRedirect('/sales-orders/'.$order->getKey());
    expect($line->tax_is_zeroed)->toBeTrue()
        ->and((string) $line->tax_code)->toBe('KDV20')
        ->and((string) $line->tax_rate)->toBe('0.000000')
        ->and((int) $line->tax_zero_reason_id)->toBe((int) $reason->getKey())
        ->and((string) $line->tax_zero_reason_code)->toBe('ISTISNA')
        ->and((string) $line->net_total)->toBe('200.000000')
        ->and((string) $line->tax_total)->toBe('0.000000')
        ->and((string) $line->gross_total)->toBe('200.000000')
        ->and((string) $order->tax_total)->toBe('0.000000')
        ->and($created->after_state['lines'][0]['tax_is_zeroed'])->toBeTrue()
        ->and($created->after_state['lines'][0]['tax_rate'])->toBe('0.000000')
        ->and($created->after_state['lines'][0]['tax_zero_reason_code'])->toBe('ISTISNA');

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->get('/sales-orders/'.$order->getKey())
        ->assertOk()
        ->assertSee('KDV Sıfırlandı')
        ->assertSee('Neden: ISTISNA');
});

it('requires an active same-company reason for an explicit tax zero override', function (): void {
    [$company, $account, $product, $reason, $manager] = m66TaxFixture('M66-REASON', '20.000000');

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-orders', m66TaxPayload($account, $product, ['tax_is_zeroed' => '1']))
        ->assertSessionHasErrors('lines.0.tax_zero_reason_id');

    $reason->forceFill(['is_active' => false])->save();
    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-orders', m66TaxPayload($account, $product, [
            'tax_is_zeroed' => '1', 'tax_zero_reason_id' => $reason->getKey(),
        ]))->assertSessionHasErrors('lines.0.tax_zero_reason_id');

    $foreign = Company::query()->create(['code' => 'M66-FOREIGN', 'name' => 'Foreign reason company']);
    $foreignReason = TaxZeroReason::query()->create([
        'company_id' => $foreign->getKey(), 'code' => 'FOREIGN', 'name' => 'Foreign reason', 'is_active' => true,
    ]);
    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-orders', m66TaxPayload($account, $product, [
            'tax_is_zeroed' => '1', 'tax_zero_reason_id' => $foreignReason->getKey(),
        ]))->assertSessionHasErrors('lines.0.tax_zero_reason_id');

    expect(SalesOrder::query()->where('company_id', $company->getKey())->count())->toBe(0);
});

it('forbids a zero reason on a taxable line when the override is not selected', function (): void {
    [$company, $account, $product, $reason, $manager] = m66TaxFixture('M66-TAXABLE', '20.000000');

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-orders', m66TaxPayload($account, $product, [
            'tax_zero_reason_id' => $reason->getKey(),
        ]))->assertSessionHasErrors('lines.0.tax_zero_reason_id');

    expect(SalesOrder::query()->where('company_id', $company->getKey())->count())->toBe(0);
});

it('keeps natural zero tax distinct from an explicit zero override', function (): void {
    [$company, $account, $product, $reason, $manager] = m66TaxFixture('M66-NATURAL', '0.000000');

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-orders', m66TaxPayload($account, $product, [
            'tax_zero_reason_id' => $reason->getKey(),
        ]))->assertRedirect();

    $line = SalesOrder::query()->where('company_id', $company->getKey())->firstOrFail()->lines()->firstOrFail();
    expect($line->tax_is_zeroed)->toBeFalse()
        ->and((string) $line->tax_rate)->toBe('0.000000')
        ->and((string) $line->tax_zero_reason_code)->toBe('ISTISNA');

    $secondPayload = m66TaxPayload($account, $product, [
        'tax_is_zeroed' => '1', 'tax_zero_reason_id' => $reason->getKey(),
    ]);
    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-orders', $secondPayload)
        ->assertSessionHasErrors('lines.0.tax_is_zeroed');
});

it('preserves zero override intent on edit and recalculates from product tax when the override is cleared', function (): void {
    [$company, $account, $product, $reason, $manager, $tax] = m66TaxFixture('M66-EDIT', '20.000000');

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-orders', m66TaxPayload($account, $product, [
            'tax_is_zeroed' => '1', 'tax_zero_reason_id' => $reason->getKey(),
        ]))->assertRedirect();

    $order = SalesOrder::query()->where('company_id', $company->getKey())->firstOrFail();
    $line = $order->lines()->firstOrFail();
    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->get('/sales-orders/'.$order->getKey().'/edit')
        ->assertOk()
        ->assertSee('KDV Sıfırla')
        ->assertSee('ISTISNA');

    $tax->forceFill(['rate' => '10.000000'])->save();
    $payload = m66TaxPayload($account, $product, [
        'logical_line_key' => $line->logical_line_key,
        'tax_zero_reason_id' => null,
    ]);
    unset($payload['series_code']);

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->put('/sales-orders/'.$order->getKey(), $payload)
        ->assertRedirect('/sales-orders/'.$order->getKey());

    $line = $order->refresh()->lines()->firstOrFail();
    expect($line->tax_is_zeroed)->toBeFalse()
        ->and((string) $line->tax_rate)->toBe('10.000000')
        ->and($line->tax_zero_reason_id)->toBeNull()
        ->and((string) $line->tax_total)->toBe('10.000000')
        ->and((string) $line->gross_total)->toBe('110.000000');
});

it('enforces the zero override snapshot shape at the PostgreSQL boundary', function (): void {
    [$company, $account, $product, , $manager] = m66TaxFixture('M66-DB', '20.000000');

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-orders', m66TaxPayload($account, $product))->assertRedirect();

    $line = SalesOrder::query()->where('company_id', $company->getKey())->firstOrFail()->lines()->firstOrFail();
    expect(fn () => DB::table('sales_order_lines')->where('id', $line->getKey())->update(['tax_is_zeroed' => true]))
        ->toThrow(QueryException::class);
});

/** @return array{Company, Account, Product, TaxZeroReason, User, Tax} */
function m66TaxFixture(string $code, string $taxRate): array
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
    $tax = Tax::query()->create([
        'company_id' => $company->getKey(), 'code' => $taxRate === '0.000000' ? 'KDV0' : 'KDV20',
        'name' => 'KDV', 'rate' => $taxRate, 'is_active' => true,
    ]);
    $reason = TaxZeroReason::query()->create([
        'company_id' => $company->getKey(), 'code' => 'ISTISNA', 'name' => 'İstisna', 'is_active' => true,
    ]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(), 'code' => 'SKU-'.$code, 'status' => ProductStatus::Active, 'name' => 'Ürün '.$code,
        'category_id' => $category->getKey(), 'unit_id' => $unit->getKey(), 'tax_id' => $tax->getKey(),
        'sale_price_net' => '100.000000', 'purchase_price_net' => '60.000000',
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(), 'document_type' => DocumentType::SalesOrder->value,
        'series_code' => 'default', 'prefix' => 'SO-', 'padding' => 4, 'next_value' => 1, 'is_active' => true,
    ]);
    $user = User::query()->create([
        'name' => 'M66 Manager', 'email' => strtolower($code).'@m66.test', 'password' => 'correct-password', 'status' => UserStatus::Active,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(), 'user_id' => $user->getKey(), 'is_active' => true, 'joined_at' => now(),
    ]);
    $role = Role::query()->create([
        'company_id' => $company->getKey(), 'code' => 'ORDER-MANAGER', 'name' => 'Sipariş Yöneticisi', 'is_active' => true,
    ]);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::SalesOrderView);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::SalesOrderManage);
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return [$company, $account, $product, $reason, $user, $tax];
}

/** @param array<string, mixed> $lineOverrides
 *  @return array<string, mixed>
 */
function m66TaxPayload(Account $account, Product $product, array $lineOverrides = []): array
{
    $line = array_merge([
        'logical_line_key' => null,
        'product_id' => $product->getKey(),
        'description' => 'M6.6 tax line',
        'quantity' => '1',
        'unit_price' => '100',
        'price_basis' => 'net',
        'line_discount_rate' => '0',
        'tax_zero_reason_id' => null,
    ], $lineOverrides);

    return [
        'series_code' => 'default',
        'account_id' => $account->getKey(),
        'order_date' => '2026-08-26',
        'currency_code' => 'TRY',
        'document_discount_rate' => '0',
        'note' => null,
        'lines' => [$line],
    ];
}
