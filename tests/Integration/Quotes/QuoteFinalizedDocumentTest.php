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
use App\Modules\Core\Models\FileAsset;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\User;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Quotes\Documents\QuoteFinalizedDocumentService;
use App\Modules\Quotes\Enums\QuoteStatus;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Quotes\Models\QuoteFinalizedDocument;
use App\Modules\Quotes\Models\QuoteRevision;
use App\Modules\Quotes\Pricing\PriceBasis;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
    Storage::fake('local');
});

it('rejects finalized detail and PDF access while the quote is still draft', function (): void {
    [$company, , , , $quote] = m55Fixture('M55-A');
    $viewer = m55Actor($company, [PermissionKey::QuoteView], 'viewer');

    $this->actingAs($viewer)->withSession(['active_company_id' => $company->getKey()])
        ->get('/quotes/'.$quote->getKey().'/finalized')->assertStatus(409);
    $this->actingAs($viewer)->withSession(['active_company_id' => $company->getKey()])
        ->get('/quotes/'.$quote->getKey().'/finalized.pdf')->assertStatus(409);

    expect(QuoteFinalizedDocument::query()->count())->toBe(0)
        ->and(FileAsset::query()->count())->toBe(0);
});

it('renders only the selected older revision and freezes one PDF across later order conversion', function (): void {
    [$company, $account, $product, , $quote] = m55Fixture('M55-B');
    $manager = m55Actor($company, [PermissionKey::QuoteView, PermissionKey::QuoteManage], 'manager');
    $approver = m55Actor($company, [PermissionKey::QuoteView, PermissionKey::QuoteApprove], 'approver');

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post('/quotes/'.$quote->getKey().'/revisions');
    $r1 = QuoteRevision::query()->where('quote_id', $quote->getKey())->where('revision_number', 1)->firstOrFail();

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->put('/quotes/'.$quote->getKey(), m55DraftPayload($account, $product, '2'))
        ->assertRedirect('/quotes/'.$quote->getKey());
    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post('/quotes/'.$quote->getKey().'/revisions');

    $this->actingAs($approver)->withSession(['active_company_id' => $company->getKey()])
        ->post('/quotes/'.$quote->getKey().'/revisions/'.$r1->getKey().'/approve')
        ->assertRedirect('/quotes/'.$quote->getKey());

    $this->actingAs($approver)->withSession(['active_company_id' => $company->getKey()])
        ->get('/quotes/'.$quote->getKey().'/finalized')
        ->assertOk()->assertSee('Immutable R1')->assertSee('100.000000')->assertSee('1.000000')->assertDontSee('200.000000');

    $first = $this->actingAs($approver)->withSession(['active_company_id' => $company->getKey()])
        ->get('/quotes/'.$quote->getKey().'/finalized.pdf')
        ->assertOk()->assertHeader('content-type', 'application/pdf')
        ->assertHeader('x-document-version', QuoteFinalizedDocumentService::RENDERER_VERSION);

    $firstBytes = $first->getContent();
    expect($firstBytes)->toBeString()->and(str_starts_with($firstBytes, '%PDF-'))->toBeTrue();

    $document = QuoteFinalizedDocument::query()->with('fileAsset')->firstOrFail();
    $asset = $document->fileAsset;
    expect($asset)->toBeInstanceOf(FileAsset::class)
        ->and((int) $document->quote_revision_id)->toBe((int) $r1->getKey())
        ->and($document->renderer_version)->toBe(QuoteFinalizedDocumentService::RENDERER_VERSION)
        ->and($document->pdf_sha256)->toBe(hash('sha256', $firstBytes))
        ->and($asset->sha256)->toBe($document->pdf_sha256)
        ->and($asset->mime_type)->toBe('application/pdf')
        ->and($asset->client_extension)->toBe('pdf');
    Storage::disk('local')->assertExists((string) $asset->storage_key);

    m55SalesOrderSequence($company);
    $this->actingAs($approver)->withSession(['active_company_id' => $company->getKey()])
        ->post('/quotes/'.$quote->getKey().'/convert')->assertRedirect('/quotes/'.$quote->getKey());

    $second = $this->actingAs($approver)->withSession(['active_company_id' => $company->getKey()])
        ->get('/quotes/'.$quote->getKey().'/finalized.pdf')->assertOk();
    $secondBytes = $second->getContent();

    expect($secondBytes)->toBe($firstBytes)
        ->and(QuoteFinalizedDocument::query()->count())->toBe(1)
        ->and(FileAsset::query()->count())->toBe(1)
        ->and((int) QuoteFinalizedDocument::query()->value('id'))->toBe((int) $document->getKey());
});

it('protects finalized document metadata and detects storage byte tampering', function (): void {
    [$company, , , , $quote] = m55Fixture('M55-C');
    $manager = m55Actor($company, [PermissionKey::QuoteView, PermissionKey::QuoteManage], 'manager');
    $approver = m55Actor($company, [PermissionKey::QuoteView, PermissionKey::QuoteApprove], 'approver');

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])->post('/quotes/'.$quote->getKey().'/revisions');
    $revision = QuoteRevision::query()->where('quote_id', $quote->getKey())->firstOrFail();
    $this->actingAs($approver)->withSession(['active_company_id' => $company->getKey()])->post('/quotes/'.$quote->getKey().'/revisions/'.$revision->getKey().'/approve');
    $this->actingAs($approver)->withSession(['active_company_id' => $company->getKey()])->get('/quotes/'.$quote->getKey().'/finalized.pdf')->assertOk();

    $document = QuoteFinalizedDocument::query()->with('fileAsset')->firstOrFail();
    $asset = $document->fileAsset;
    expect($asset)->toBeInstanceOf(FileAsset::class)
        ->and(fn () => DB::table('quote_finalized_documents')->where('id', $document->getKey())->update(['renderer_version' => 'quote-pdf.v2']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('quote_finalized_documents')->where('id', $document->getKey())->delete())->toThrow(QueryException::class)
        ->and(fn () => DB::table('file_assets')->where('id', $asset->getKey())->update(['original_name' => 'tampered.pdf']))->toThrow(QueryException::class);

    Storage::disk('local')->put((string) $asset->storage_key, '%PDF-tampered');
    $this->actingAs($approver)->withSession(['active_company_id' => $company->getKey()])
        ->get('/quotes/'.$quote->getKey().'/finalized.pdf')->assertStatus(410);
});

it('keeps finalized routes tenant scoped and supports rejected historical PDFs', function (): void {
    [$companyA, , , , $quoteA] = m55Fixture('M55-D-A');
    [$companyB, , , , $quoteB] = m55Fixture('M55-D-B');
    $managerA = m55Actor($companyA, [PermissionKey::QuoteView, PermissionKey::QuoteManage], 'manager');
    $approverA = m55Actor($companyA, [PermissionKey::QuoteView, PermissionKey::QuoteApprove], 'approver');
    $viewerB = m55Actor($companyB, [PermissionKey::QuoteView], 'viewer');

    $this->actingAs($managerA)->withSession(['active_company_id' => $companyA->getKey()])->post('/quotes/'.$quoteA->getKey().'/revisions');
    $revisionA = QuoteRevision::query()->where('company_id', $companyA->getKey())->firstOrFail();
    $this->actingAs($approverA)->withSession(['active_company_id' => $companyA->getKey()])
        ->post('/quotes/'.$quoteA->getKey().'/revisions/'.$revisionA->getKey().'/reject', ['decision_note' => 'Arşiv reddi']);
    $this->actingAs($approverA)->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/quotes/'.$quoteA->getKey().'/finalized.pdf')->assertOk()->assertHeader('content-type', 'application/pdf');

    $this->actingAs($viewerB)->withSession(['active_company_id' => $companyB->getKey()])
        ->get('/quotes/'.$quoteA->getKey().'/finalized')->assertNotFound();
    $this->actingAs($viewerB)->withSession(['active_company_id' => $companyB->getKey()])
        ->get('/quotes/'.$quoteA->getKey().'/finalized.pdf')->assertNotFound();

    expect(Quote::query()->findOrFail($quoteB->getKey())->statusEnum())->toBe(QuoteStatus::Draft);
});

/** @return array{Company, Account, Product, Tax, Quote} */
function m55Fixture(string $code): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $account = Account::query()->create([
        'company_id' => $company->getKey(), 'code' => 'CUST', 'type' => AccountType::Customer,
        'status' => AccountStatus::Active, 'legal_name' => 'Müşteri '.$code, 'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None, 'tax_number' => null, 'tax_office' => null,
        'book_currency_code' => 'TRY', 'due_days' => 0, 'discount_rate' => '0.000000', 'risk_limit' => '0.000000',
    ]);
    $category = Category::query()->create(['company_id' => $company->getKey(), 'code' => 'CAT', 'name' => 'Kategori', 'is_active' => true]);
    $unit = Unit::query()->create(['company_id' => $company->getKey(), 'code' => 'ADET', 'name' => 'Adet', 'is_active' => true]);
    $tax = Tax::query()->create(['company_id' => $company->getKey(), 'code' => 'KDV20', 'name' => 'KDV %20', 'rate' => '20.000000', 'is_active' => true]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(), 'code' => 'SKU', 'status' => ProductStatus::Active, 'name' => 'Ürün '.$code,
        'category_id' => $category->getKey(), 'unit_id' => $unit->getKey(), 'tax_id' => $tax->getKey(),
        'sale_price_net' => '100.000000', 'purchase_price_net' => '60.000000',
    ]);
    $quote = Quote::query()->create([
        'company_id' => $company->getKey(), 'account_id' => $account->getKey(), 'number' => 'Q-0001',
        'series_code' => 'default', 'sequence_value' => 1, 'status' => QuoteStatus::Draft->value,
        'quote_date' => '2026-08-26', 'valid_until' => '2026-09-02', 'currency_code' => 'TRY',
        'document_discount_rate' => '0.000000', 'base_net_total' => '100.000000', 'line_discount_total' => '0.000000',
        'document_discount_total' => '0.000000', 'net_total' => '100.000000', 'tax_total' => '20.000000',
        'gross_total' => '120.000000', 'note' => 'İlk snapshot',
    ]);
    $quote->lines()->create([
        'company_id' => $company->getKey(), 'position' => 1, 'product_id' => $product->getKey(), 'product_code' => 'SKU',
        'description' => 'Snapshot ürünü', 'quantity' => '1.000000', 'price_basis' => PriceBasis::Net,
        'unit_price' => '100.000000', 'line_discount_rate' => '0.000000', 'tax_id' => $tax->getKey(),
        'tax_rate' => '20.000000', 'tax_zero_reason_id' => null, 'tax_zero_reason_code' => null,
        'base_net' => '100.000000', 'line_discount_net' => '0.000000', 'document_discount_net' => '0.000000',
        'net_total' => '100.000000', 'tax_total' => '20.000000', 'gross_total' => '120.000000',
    ]);

    return [$company, $account, $product, $tax, $quote];
}

/** @return array<string, mixed> */
function m55DraftPayload(Account $account, Product $product, string $quantity): array
{
    return [
        'account_id' => $account->getKey(), 'quote_date' => '2026-08-26', 'valid_until' => '2026-09-02',
        'currency_code' => 'TRY', 'document_discount_rate' => '0', 'note' => 'Güncellenmiş teklif',
        'lines' => [[
            'product_id' => $product->getKey(), 'description' => 'Snapshot ürünü', 'quantity' => $quantity,
            'unit_price' => '100', 'price_basis' => 'net', 'line_discount_rate' => '0', 'tax_zero_reason_id' => null,
        ]],
    ];
}

function m55SalesOrderSequence(Company $company): DocumentSequence
{
    return DocumentSequence::query()->create([
        'company_id' => $company->getKey(), 'document_type' => DocumentType::SalesOrder->value,
        'series_code' => 'default', 'prefix' => 'SO-', 'padding' => 4, 'next_value' => 1, 'is_active' => true,
    ]);
}

/** @param list<PermissionKey> $permissions */
function m55Actor(Company $company, array $permissions, string $suffix): User
{
    $random = str()->lower(str()->random(6));
    $user = User::query()->create([
        'name' => 'M5.5 '.$suffix,
        'email' => strtolower((string) $company->code).'-'.$suffix.'-'.$random.'@m55.test',
        'password' => 'correct-password', 'status' => UserStatus::Active,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(), 'user_id' => $user->getKey(), 'is_active' => true, 'joined_at' => now(),
    ]);
    $role = Role::query()->create([
        'company_id' => $company->getKey(), 'code' => 'm55-'.$suffix.'-'.$random, 'name' => 'M5.5 '.$suffix, 'is_active' => true,
    ]);
    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}
