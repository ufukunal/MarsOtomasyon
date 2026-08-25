<?php

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\User;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Quotes\Enums\QuoteStatus;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Quotes\Models\QuoteRevision;
use App\Modules\Quotes\Pricing\PriceBasis;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('deduplicates the same snapshot and preserves R1 when a later draft edit creates R2', function (): void {
    [$company, $account, $product, $tax, $quote] = m53Fixture('M53-A');
    $manager = m53Actor($company, [PermissionKey::QuoteView, PermissionKey::QuoteManage], 'manager');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/quotes/'.$quote->getKey().'/revisions')
        ->assertRedirect();

    $r1 = QuoteRevision::query()->where('quote_id', $quote->getKey())->firstOrFail();
    expect($r1->revision_number)->toBe(1)
        ->and((string) $r1->net_total)->toBe('100.000000')
        ->and((string) $r1->lines()->firstOrFail()->quantity)->toBe('1.000000');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/quotes/'.$quote->getKey().'/revisions')
        ->assertRedirect('/quotes/'.$quote->getKey().'/revisions/'.$r1->getKey());

    expect(QuoteRevision::query()->where('quote_id', $quote->getKey())->count())->toBe(1);

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->put('/quotes/'.$quote->getKey(), m53DraftPayload($account, $product, '2'))
        ->assertRedirect('/quotes/'.$quote->getKey());

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/quotes/'.$quote->getKey().'/revisions')
        ->assertRedirect();

    $revisions = QuoteRevision::query()
        ->where('quote_id', $quote->getKey())
        ->orderBy('revision_number')
        ->get();

    expect($revisions)->toHaveCount(2)
        ->and($revisions[0]->revision_number)->toBe(1)
        ->and((string) $revisions[0]->net_total)->toBe('100.000000')
        ->and((string) $revisions[0]->lines()->firstOrFail()->quantity)->toBe('1.000000')
        ->and($revisions[1]->revision_number)->toBe(2)
        ->and((string) $revisions[1]->net_total)->toBe('200.000000')
        ->and((string) $revisions[1]->gross_total)->toBe('240.000000')
        ->and((string) $revisions[1]->lines()->firstOrFail()->quantity)->toBe('2.000000')
        ->and($revisions[0]->content_fingerprint)->not->toBe($revisions[1]->content_fingerprint);

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/quotes/'.$quote->getKey())
        ->assertOk()
        ->assertSee('R2')
        ->assertSee('R1');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/quotes/'.$quote->getKey().'/revisions/'.$revisions[0]->getKey())
        ->assertOk()
        ->assertSee('Immutable Revision')
        ->assertSee('100.000000');
});

it('rejects raw mutation and deletion of revision history at the PostgreSQL boundary', function (): void {
    [$company, , , , $quote] = m53Fixture('M53-B');
    $manager = m53Actor($company, [PermissionKey::QuoteView, PermissionKey::QuoteManage], 'manager');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/quotes/'.$quote->getKey().'/revisions')
        ->assertRedirect();

    $revision = QuoteRevision::query()->firstOrFail();
    $lineId = (int) $revision->lines()->firstOrFail()->getKey();

    expect(fn () => DB::table('quote_revisions')
        ->where('id', $revision->getKey())
        ->update(['account_name' => 'tampered']))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('quote_revision_lines')->where('id', $lineId)->delete())
        ->toThrow(QueryException::class);
});

it('enforces revision RBAC, tenant isolation and the draft-only snapshot boundary', function (): void {
    [$companyA, , , , $quoteA] = m53Fixture('M53-C-A');
    [$companyB, , , , $quoteB] = m53Fixture('M53-C-B');
    $viewerA = m53Actor($companyA, [PermissionKey::QuoteView], 'viewer');
    $managerA = m53Actor($companyA, [PermissionKey::QuoteView, PermissionKey::QuoteManage], 'manager');
    $managerB = m53Actor($companyB, [PermissionKey::QuoteView, PermissionKey::QuoteManage], 'manager');

    $this->actingAs($managerB)
        ->withSession(['active_company_id' => $companyB->getKey()])
        ->post('/quotes/'.$quoteB->getKey().'/revisions')
        ->assertRedirect();
    $foreignRevision = QuoteRevision::query()->where('company_id', $companyB->getKey())->firstOrFail();

    $this->actingAs($viewerA)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->post('/quotes/'.$quoteA->getKey().'/revisions')
        ->assertForbidden();

    $this->actingAs($viewerA)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/quotes/'.$quoteB->getKey().'/revisions/'.$foreignRevision->getKey())
        ->assertNotFound();

    $this->actingAs($managerA)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->post('/quotes/'.$quoteA->getKey().'/cancel')
        ->assertRedirect('/quotes/'.$quoteA->getKey());

    $this->actingAs($managerA)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->from('/quotes/'.$quoteA->getKey())
        ->post('/quotes/'.$quoteA->getKey().'/revisions')
        ->assertRedirect('/quotes/'.$quoteA->getKey())
        ->assertSessionHasErrors('quote');
});

/** @return array{Company, Account, Product, Tax, Quote} */
function m53Fixture(string $code): array
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
    $category = Category::query()->create([
        'company_id' => $company->getKey(), 'code' => 'CAT', 'name' => 'Kategori', 'is_active' => true,
    ]);
    $unit = Unit::query()->create([
        'company_id' => $company->getKey(), 'code' => 'ADET', 'name' => 'Adet', 'is_active' => true,
    ]);
    $tax = Tax::query()->create([
        'company_id' => $company->getKey(), 'code' => 'KDV20', 'name' => 'KDV %20',
        'rate' => '20.000000', 'is_active' => true,
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
    $quote = Quote::query()->create([
        'company_id' => $company->getKey(),
        'account_id' => $account->getKey(),
        'number' => 'Q-0001',
        'series_code' => 'default',
        'sequence_value' => 1,
        'status' => QuoteStatus::Draft,
        'quote_date' => '2026-08-26',
        'valid_until' => '2026-09-02',
        'currency_code' => 'TRY',
        'document_discount_rate' => '0.000000',
        'base_net_total' => '100.000000',
        'line_discount_total' => '0.000000',
        'document_discount_total' => '0.000000',
        'net_total' => '100.000000',
        'tax_total' => '20.000000',
        'gross_total' => '120.000000',
        'note' => 'İlk snapshot',
    ]);
    $quote->lines()->create([
        'company_id' => $company->getKey(),
        'position' => 1,
        'product_id' => $product->getKey(),
        'product_code' => 'SKU',
        'description' => 'Snapshot ürünü',
        'quantity' => '1.000000',
        'price_basis' => PriceBasis::Net,
        'unit_price' => '100.000000',
        'line_discount_rate' => '0.000000',
        'tax_id' => $tax->getKey(),
        'tax_rate' => '20.000000',
        'tax_zero_reason_id' => null,
        'tax_zero_reason_code' => null,
        'base_net' => '100.000000',
        'line_discount_net' => '0.000000',
        'document_discount_net' => '0.000000',
        'net_total' => '100.000000',
        'tax_total' => '20.000000',
        'gross_total' => '120.000000',
    ]);

    return [$company, $account, $product, $tax, $quote];
}

/** @return array<string, mixed> */
function m53DraftPayload(Account $account, Product $product, string $quantity): array
{
    return [
        'account_id' => $account->getKey(),
        'quote_date' => '2026-08-26',
        'valid_until' => '2026-09-02',
        'currency_code' => 'TRY',
        'document_discount_rate' => '0',
        'note' => 'Güncellenmiş teklif',
        'lines' => [[
            'product_id' => $product->getKey(),
            'description' => 'Snapshot ürünü',
            'quantity' => $quantity,
            'unit_price' => '100',
            'price_basis' => 'net',
            'line_discount_rate' => '0',
            'tax_zero_reason_id' => null,
        ]],
    ];
}

/** @param list<PermissionKey> $permissions */
function m53Actor(Company $company, array $permissions, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'M5.3 '.$suffix,
        'email' => strtolower((string) $company->code).'-'.$suffix.'@m53.test',
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
        'code' => 'm53-'.$suffix,
        'name' => 'M5.3 '.$suffix,
        'is_active' => true,
    ]);

    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}
