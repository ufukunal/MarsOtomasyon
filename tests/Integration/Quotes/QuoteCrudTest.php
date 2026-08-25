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
use App\Modules\Quotes\Enums\QuoteStatus;
use App\Modules\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('creates a numbered quote and persists only server calculated totals', function (): void {
    [$company, $account, $product] = quote52Fixture('Q52-A');
    $manager = quote52Actor($company, [PermissionKey::QuoteView, PermissionKey::QuoteManage], 'manager');

    $response = $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/quotes', [
            'series_code' => 'default',
            'account_id' => $account->getKey(),
            'quote_date' => '2026-08-25',
            'valid_until' => '2026-09-01',
            'currency_code' => 'TRY',
            'document_discount_rate' => '10',
            'net_total' => '0.000001',
            'tax_total' => '0.000001',
            'gross_total' => '0.000002',
            'lines' => [[
                'product_id' => $product->getKey(),
                'description' => 'Sunucu hesaplı satır',
                'quantity' => '2',
                'unit_price' => '100',
                'price_basis' => 'net',
                'line_discount_rate' => '0',
                'tax_zero_reason_id' => null,
                'net_total' => '0.000001',
            ]],
        ]);

    $quote = Quote::query()->where('company_id', $company->getKey())->firstOrFail();
    $response->assertRedirect('/quotes/'.$quote->getKey());

    expect($quote->number)->toBe('Q-0001')
        ->and((string) $quote->base_net_total)->toBe('200.000000')
        ->and((string) $quote->document_discount_total)->toBe('20.000000')
        ->and((string) $quote->net_total)->toBe('180.000000')
        ->and((string) $quote->tax_total)->toBe('36.000000')
        ->and((string) $quote->gross_total)->toBe('216.000000')
        ->and($quote->lines()->count())->toBe(1)
        ->and((string) $quote->lines()->firstOrFail()->net_total)->toBe('180.000000');
});

it('recalculates draft edits without changing document identity and blocks edits after cancellation', function (): void {
    [$company, $account, $product] = quote52Fixture('Q52-B');
    $manager = quote52Actor($company, [PermissionKey::QuoteView, PermissionKey::QuoteManage], 'manager');

    $payload = [
        'series_code' => 'default', 'account_id' => $account->getKey(), 'quote_date' => '2026-08-25',
        'valid_until' => null, 'currency_code' => 'TRY', 'document_discount_rate' => '0', 'note' => null,
        'lines' => [[
            'product_id' => $product->getKey(), 'description' => null, 'quantity' => '1', 'unit_price' => '100',
            'price_basis' => 'net', 'line_discount_rate' => '0', 'tax_zero_reason_id' => null,
        ]],
    ];

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])->post('/quotes', $payload)->assertRedirect();
    $quote = Quote::query()->firstOrFail();
    $number = (string) $quote->number;

    $payload['lines'][0]['quantity'] = '3';
    unset($payload['series_code']);
    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->put('/quotes/'.$quote->getKey(), $payload)->assertRedirect('/quotes/'.$quote->getKey());

    $quote->refresh();
    expect($quote->number)->toBe($number)
        ->and((string) $quote->net_total)->toBe('300.000000')
        ->and((string) $quote->gross_total)->toBe('360.000000');

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post('/quotes/'.$quote->getKey().'/cancel')->assertRedirect('/quotes/'.$quote->getKey());

    expect($quote->refresh()->statusEnum())->toBe(QuoteStatus::Cancelled);
    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->get('/quotes/'.$quote->getKey().'/edit')->assertStatus(409);
});

it('keeps quote viewing separate from management and does not expose another company quote', function (): void {
    [$companyA] = quote52Fixture('Q52-C-A');
    [$companyB, $accountB, $productB] = quote52Fixture('Q52-C-B');
    $viewer = quote52Actor($companyA, [PermissionKey::QuoteView], 'viewer');
    $managerB = quote52Actor($companyB, [PermissionKey::QuoteView, PermissionKey::QuoteManage], 'manager-b');

    $this->actingAs($managerB)->withSession(['active_company_id' => $companyB->getKey()])->post('/quotes', [
        'series_code' => 'default', 'account_id' => $accountB->getKey(), 'quote_date' => '2026-08-25',
        'valid_until' => null, 'currency_code' => 'TRY', 'document_discount_rate' => '0',
        'lines' => [[
            'product_id' => $productB->getKey(), 'quantity' => '1', 'unit_price' => '100', 'price_basis' => 'net',
            'line_discount_rate' => '0', 'tax_zero_reason_id' => null,
        ]],
    ])->assertRedirect();
    $foreign = Quote::query()->where('company_id', $companyB->getKey())->firstOrFail();

    $this->actingAs($viewer)->withSession(['active_company_id' => $companyA->getKey()])->get('/quotes')->assertOk()->assertDontSee($foreign->number);
    $this->actingAs($viewer)->withSession(['active_company_id' => $companyA->getKey()])->get('/quotes/create')->assertForbidden();
    $this->actingAs($viewer)->withSession(['active_company_id' => $companyA->getKey()])->get('/quotes/'.$foreign->getKey())->assertNotFound();
});

/** @return array{Company, Account, Product} */
function quote52Fixture(string $code): array
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
        'company_id' => $company->getKey(), 'document_type' => DocumentType::Quote, 'series_code' => 'default',
        'prefix' => 'Q-', 'padding' => 4, 'next_value' => 1, 'is_active' => true,
    ]);

    return [$company, $account, $product];
}

/** @param list<PermissionKey> $permissions */
function quote52Actor(Company $company, array $permissions, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'Quote '.$suffix, 'email' => strtolower((string) $company->code).'-'.$suffix.'@quotes.test',
        'password' => 'correct-password', 'status' => UserStatus::Active,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(), 'user_id' => $user->getKey(), 'is_active' => true, 'joined_at' => now(),
    ]);
    $role = Role::query()->create([
        'company_id' => $company->getKey(), 'code' => 'quote-'.$suffix, 'name' => 'Quote '.$suffix, 'is_active' => true,
    ]);
    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}
