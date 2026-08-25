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
use App\Modules\Quotes\Models\QuoteRevision;
use App\Modules\Quotes\Pricing\PriceBasis;
use App\Modules\SalesOrders\Models\SalesOrder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('separates draft management from commercial approval and freezes finalized quote state', function (): void {
    [$company, , , , $quote] = m54Fixture('M54-A');
    $manager = m54Actor($company, [PermissionKey::QuoteView, PermissionKey::QuoteManage], 'manager');
    $approver = m54Actor($company, [PermissionKey::QuoteView, PermissionKey::QuoteApprove], 'approver');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/quotes/'.$quote->getKey().'/revisions')
        ->assertRedirect();
    $revision = QuoteRevision::query()->where('quote_id', $quote->getKey())->firstOrFail();

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/quotes/'.$quote->getKey().'/revisions/'.$revision->getKey().'/approve')
        ->assertForbidden();

    $this->actingAs($approver)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/quotes/'.$quote->getKey().'/revisions/'.$revision->getKey().'/approve', ['decision_note' => 'Ticari onay'])
        ->assertRedirect('/quotes/'.$quote->getKey());

    $quote->refresh();
    expect($quote->statusEnum())->toBe(QuoteStatus::Approved)
        ->and((int) $quote->selected_revision_id)->toBe((int) $revision->getKey())
        ->and((string) $quote->decision_note)->toBe('Ticari onay');

    expect(fn () => DB::table('quotes')->where('id', $quote->getKey())->update(['note' => 'tampered']))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('quote_lines')->where('quote_id', $quote->getKey())->update(['quantity' => '9.000000']))
        ->toThrow(QueryException::class);

    $this->actingAs($approver)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/quotes/'.$quote->getKey())
        ->assertOk()
        ->assertSee('Ticari Otorite')
        ->assertSee('R1');
});

it('makes rejection terminal and replay-safe while refusing conversion', function (): void {
    [$company, , , , $quote] = m54Fixture('M54-B');
    $manager = m54Actor($company, [PermissionKey::QuoteView, PermissionKey::QuoteManage], 'manager');
    $approver = m54Actor($company, [PermissionKey::QuoteView, PermissionKey::QuoteApprove], 'approver');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/quotes/'.$quote->getKey().'/revisions');
    $revision = QuoteRevision::query()->where('quote_id', $quote->getKey())->firstOrFail();

    $decisionUrl = '/quotes/'.$quote->getKey().'/revisions/'.$revision->getKey().'/reject';
    $this->actingAs($approver)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post($decisionUrl, ['decision_note' => 'Şartlar uygun değil'])
        ->assertRedirect('/quotes/'.$quote->getKey());
    $this->actingAs($approver)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post($decisionUrl)
        ->assertRedirect('/quotes/'.$quote->getKey());

    $quote->refresh();
    expect($quote->statusEnum())->toBe(QuoteStatus::Rejected)
        ->and(QuoteRevision::query()->where('quote_id', $quote->getKey())->count())->toBe(1);

    $this->actingAs($approver)
        ->withSession(['active_company_id' => $company->getKey()])
        ->from('/quotes/'.$quote->getKey())
        ->post('/quotes/'.$quote->getKey().'/convert')
        ->assertRedirect('/quotes/'.$quote->getKey())
        ->assertSessionHasErrors('quote');

    expect(SalesOrder::query()->where('source_quote_id', $quote->getKey())->count())->toBe(0);
});

it('converts the explicitly approved older revision exactly once with immutable lineage', function (): void {
    [$company, $account, $product, , $quote] = m54Fixture('M54-C');
    $manager = m54Actor($company, [PermissionKey::QuoteView, PermissionKey::QuoteManage], 'manager');
    $approver = m54Actor($company, [PermissionKey::QuoteView, PermissionKey::QuoteApprove], 'approver');
    m54SalesOrderSequence($company);

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/quotes/'.$quote->getKey().'/revisions');
    $r1 = QuoteRevision::query()->where('quote_id', $quote->getKey())->firstOrFail();

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->put('/quotes/'.$quote->getKey(), m54DraftPayload($account, $product, '2'))
        ->assertRedirect('/quotes/'.$quote->getKey());
    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/quotes/'.$quote->getKey().'/revisions');
    $r2 = QuoteRevision::query()->where('quote_id', $quote->getKey())->where('revision_number', 2)->firstOrFail();

    expect((string) $r1->net_total)->toBe('100.000000')
        ->and((string) $r2->net_total)->toBe('200.000000');

    $this->actingAs($approver)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/quotes/'.$quote->getKey().'/revisions/'.$r1->getKey().'/approve')
        ->assertRedirect('/quotes/'.$quote->getKey());

    $this->actingAs($approver)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/quotes/'.$quote->getKey().'/convert')
        ->assertRedirect('/quotes/'.$quote->getKey());

    $order = SalesOrder::query()->where('source_quote_id', $quote->getKey())->firstOrFail();
    $line = $order->lines()->firstOrFail();
    expect($order->number)->toBe('SO-0001')
        ->and((int) $order->source_quote_revision_id)->toBe((int) $r1->getKey())
        ->and((string) $order->net_total)->toBe('100.000000')
        ->and((string) $order->gross_total)->toBe('120.000000')
        ->and((string) $line->quantity)->toBe('1.000000')
        ->and((int) $line->source_quote_revision_line_id)->toBe((int) $r1->lines()->firstOrFail()->getKey());

    $quote->refresh();
    expect($quote->statusEnum())->toBe(QuoteStatus::Converted)
        ->and((int) $quote->selected_revision_id)->toBe((int) $r1->getKey());

    $this->actingAs($approver)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/quotes/'.$quote->getKey().'/convert')
        ->assertRedirect('/quotes/'.$quote->getKey());

    expect(SalesOrder::query()->where('source_quote_id', $quote->getKey())->count())->toBe(1)
        ->and((int) DocumentSequence::query()->where('company_id', $company->getKey())->where('document_type', DocumentType::SalesOrder->value)->value('next_value'))->toBe(2)
        ->and(fn () => DB::table('sales_orders')->where('id', $order->getKey())->update(['note' => 'tampered']))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('sales_order_lines')->where('id', $line->getKey())->delete())
        ->toThrow(QueryException::class);

    $this->actingAs($approver)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/quotes/'.$quote->getKey())
        ->assertOk()
        ->assertSee('SO-0001')
        ->assertSee('100.000000');
});

it('rejects cross-tenant revision decisions and mismatched order-line lineage', function (): void {
    [$companyA, , , , $quoteA] = m54Fixture('M54-D-A');
    [$companyB, , , , $quoteB] = m54Fixture('M54-D-B');
    $managerA = m54Actor($companyA, [PermissionKey::QuoteView, PermissionKey::QuoteManage], 'manager');
    $managerB = m54Actor($companyB, [PermissionKey::QuoteView, PermissionKey::QuoteManage], 'manager');
    $approverA = m54Actor($companyA, [PermissionKey::QuoteView, PermissionKey::QuoteApprove], 'approver');
    m54SalesOrderSequence($companyA);

    $this->actingAs($managerA)->withSession(['active_company_id' => $companyA->getKey()])->post('/quotes/'.$quoteA->getKey().'/revisions');
    $this->actingAs($managerB)->withSession(['active_company_id' => $companyB->getKey()])->post('/quotes/'.$quoteB->getKey().'/revisions');
    $r1A = QuoteRevision::query()->where('company_id', $companyA->getKey())->firstOrFail();
    $r1B = QuoteRevision::query()->where('company_id', $companyB->getKey())->firstOrFail();

    $this->actingAs($approverA)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->from('/quotes/'.$quoteA->getKey())
        ->post('/quotes/'.$quoteA->getKey().'/revisions/'.$r1B->getKey().'/approve')
        ->assertRedirect('/quotes/'.$quoteA->getKey())
        ->assertSessionHasErrors('revision');

    $this->actingAs($approverA)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->post('/quotes/'.$quoteA->getKey().'/revisions/'.$r1A->getKey().'/approve');
    $this->actingAs($approverA)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->post('/quotes/'.$quoteA->getKey().'/convert');

    $order = SalesOrder::query()->where('source_quote_id', $quoteA->getKey())->firstOrFail();
    $foreignLine = $r1B->lines()->firstOrFail();

    expect(fn () => DB::table('sales_order_lines')->insert([
        'company_id' => $companyA->getKey(),
        'sales_order_id' => $order->getKey(),
        'source_quote_revision_line_id' => $foreignLine->getKey(),
        'position' => 99,
        'product_id' => $foreignLine->product_id,
        'product_code' => $foreignLine->product_code,
        'product_name' => $foreignLine->product_name,
        'description' => $foreignLine->description,
        'quantity' => $foreignLine->quantity,
        'price_basis' => 'net',
        'unit_price' => $foreignLine->unit_price,
        'line_discount_rate' => $foreignLine->line_discount_rate,
        'tax_id' => $foreignLine->tax_id,
        'tax_code' => $foreignLine->tax_code,
        'tax_rate' => $foreignLine->tax_rate,
        'tax_zero_reason_id' => null,
        'tax_zero_reason_code' => null,
        'base_net' => $foreignLine->base_net,
        'line_discount_net' => $foreignLine->line_discount_net,
        'document_discount_net' => $foreignLine->document_discount_net,
        'net_total' => $foreignLine->net_total,
        'tax_total' => $foreignLine->tax_total,
        'gross_total' => $foreignLine->gross_total,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

/** @return array{Company, Account, Product, Tax, Quote} */
function m54Fixture(string $code): array
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
        'status' => QuoteStatus::Draft->value,
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
function m54DraftPayload(Account $account, Product $product, string $quantity): array
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

function m54SalesOrderSequence(Company $company): DocumentSequence
{
    return DocumentSequence::query()->create([
        'company_id' => $company->getKey(),
        'document_type' => DocumentType::SalesOrder->value,
        'series_code' => 'default',
        'prefix' => 'SO-',
        'padding' => 4,
        'next_value' => 1,
        'is_active' => true,
    ]);
}

/** @param list<PermissionKey> $permissions */
function m54Actor(Company $company, array $permissions, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'M5.4 '.$suffix,
        'email' => strtolower((string) $company->code).'-'.$suffix.'-'.str()->lower(str()->random(6)).'@m54.test',
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
        'code' => 'm54-'.$suffix.'-'.str()->lower(str()->random(6)),
        'name' => 'M5.4 '.$suffix,
        'is_active' => true,
    ]);

    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}
