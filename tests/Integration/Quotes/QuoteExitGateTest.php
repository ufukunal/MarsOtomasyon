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
use App\Modules\SalesOrders\Models\SalesOrder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
    Storage::fake('local');
});

it('keeps calculator revision approval PDF and order lineage on one immutable commercial authority', function (): void {
    [$company, $account, $product] = m56Fixture();
    $actor = m56Actor($company);
    $session = ['active_company_id' => $company->getKey()];

    $createPayload = [
        'series_code' => 'default',
        'account_id' => $account->getKey(),
        'quote_date' => '2026-08-26',
        'valid_until' => '2026-09-02',
        'currency_code' => 'TRY',
        'document_discount_rate' => '5',
        'net_total' => '0.000001',
        'tax_total' => '0.000001',
        'gross_total' => '0.000002',
        'lines' => [[
            'product_id' => $product->getKey(),
            'description' => 'Exit gate gross line',
            'quantity' => '2',
            'unit_price' => '120',
            'price_basis' => 'gross',
            'line_discount_rate' => '10',
            'tax_zero_reason_id' => null,
            'net_total' => '999999.000000',
            'tax_total' => '999999.000000',
            'gross_total' => '999999.000000',
        ]],
    ];

    $this->actingAs($actor)->withSession($session)->post('/quotes', $createPayload)->assertRedirect();
    $quote = Quote::query()->where('company_id', $company->getKey())->firstOrFail();
    $line = $quote->lines()->firstOrFail();

    expect($quote->number)->toBe('Q-0001')
        ->and((string) $quote->base_net_total)->toBe('200.000000')
        ->and((string) $quote->line_discount_total)->toBe('20.000000')
        ->and((string) $quote->document_discount_total)->toBe('9.000000')
        ->and((string) $quote->net_total)->toBe('171.000000')
        ->and((string) $quote->tax_total)->toBe('34.200000')
        ->and((string) $quote->gross_total)->toBe('205.200000')
        ->and((string) $line->net_total)->toBe('171.000000')
        ->and((string) $line->tax_total)->toBe('34.200000')
        ->and((string) $line->gross_total)->toBe('205.200000');

    $snapshotUrl = '/quotes/'.$quote->getKey().'/revisions';
    $this->actingAs($actor)->withSession($session)->post($snapshotUrl)->assertRedirect('/quotes/'.$quote->getKey().'/revisions/1');
    $this->actingAs($actor)->withSession($session)->post($snapshotUrl)->assertRedirect('/quotes/'.$quote->getKey().'/revisions/1');

    $r1 = QuoteRevision::query()->where('quote_id', $quote->getKey())->where('revision_number', 1)->firstOrFail();
    expect(QuoteRevision::query()->where('quote_id', $quote->getKey())->count())->toBe(1)
        ->and((string) $r1->net_total)->toBe('171.000000')
        ->and((string) $r1->gross_total)->toBe('205.200000');

    $updatePayload = [
        'account_id' => $account->getKey(),
        'quote_date' => '2026-08-26',
        'valid_until' => '2026-09-02',
        'currency_code' => 'TRY',
        'document_discount_rate' => '0',
        'note' => 'Mutable draft changed after R1',
        'lines' => [[
            'product_id' => $product->getKey(),
            'description' => 'Later R2 draft',
            'quantity' => '1',
            'unit_price' => '300',
            'price_basis' => 'net',
            'line_discount_rate' => '0',
            'tax_zero_reason_id' => null,
        ]],
    ];

    $this->actingAs($actor)->withSession($session)
        ->put('/quotes/'.$quote->getKey(), $updatePayload)
        ->assertRedirect('/quotes/'.$quote->getKey());
    $this->actingAs($actor)->withSession($session)->post($snapshotUrl)->assertRedirect('/quotes/'.$quote->getKey().'/revisions/2');

    $r2 = QuoteRevision::query()->where('quote_id', $quote->getKey())->where('revision_number', 2)->firstOrFail();
    expect((string) $r1->fresh()->net_total)->toBe('171.000000')
        ->and((string) $r2->net_total)->toBe('300.000000')
        ->and((string) $r2->gross_total)->toBe('360.000000')
        ->and(fn () => DB::table('quote_revisions')->where('id', $r1->getKey())->update(['net_total' => '1.000000']))
        ->toThrow(QueryException::class);

    $this->actingAs($actor)->withSession($session)
        ->post('/quotes/'.$quote->getKey().'/revisions/'.$r1->getKey().'/approve', ['decision_note' => 'R1 commercial authority'])
        ->assertRedirect('/quotes/'.$quote->getKey());

    $quote->refresh();
    expect($quote->statusEnum())->toBe(QuoteStatus::Approved)
        ->and((int) $quote->selected_revision_id)->toBe((int) $r1->getKey());

    $this->actingAs($actor)->withSession($session)
        ->get('/quotes/'.$quote->getKey().'/finalized')
        ->assertOk()
        ->assertSee('Immutable R1')
        ->assertSee('171.000000')
        ->assertSee('205.200000')
        ->assertDontSee('300.000000')
        ->assertDontSee('360.000000');

    $firstPdf = $this->actingAs($actor)->withSession($session)
        ->get('/quotes/'.$quote->getKey().'/finalized.pdf')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('x-document-version', QuoteFinalizedDocumentService::RENDERER_VERSION);
    $firstBytes = $firstPdf->getContent();
    expect($firstBytes)->toBeString()->and(str_starts_with($firstBytes, '%PDF-'))->toBeTrue();

    $document = QuoteFinalizedDocument::query()->with('fileAsset')->firstOrFail();
    $asset = $document->fileAsset;
    expect($asset)->toBeInstanceOf(FileAsset::class)
        ->and((int) $document->quote_revision_id)->toBe((int) $r1->getKey())
        ->and((string) $document->pdf_sha256)->toBe(hash('sha256', $firstBytes))
        ->and((string) $asset->sha256)->toBe((string) $document->pdf_sha256);

    $convertUrl = '/quotes/'.$quote->getKey().'/convert';
    $this->actingAs($actor)->withSession($session)->post($convertUrl)->assertRedirect('/quotes/'.$quote->getKey());
    $this->actingAs($actor)->withSession($session)->post($convertUrl)->assertRedirect('/quotes/'.$quote->getKey());

    $order = SalesOrder::query()->where('source_quote_id', $quote->getKey())->firstOrFail();
    $orderLine = $order->lines()->firstOrFail();
    expect(Quote::query()->findOrFail($quote->getKey())->statusEnum())->toBe(QuoteStatus::Converted)
        ->and(SalesOrder::query()->where('source_quote_id', $quote->getKey())->count())->toBe(1)
        ->and($order->number)->toBe('SO-0001')
        ->and((int) $order->source_quote_revision_id)->toBe((int) $r1->getKey())
        ->and((string) $order->net_total)->toBe('171.000000')
        ->and((string) $order->tax_total)->toBe('34.200000')
        ->and((string) $order->gross_total)->toBe('205.200000')
        ->and((string) $orderLine->quantity)->toBe('2.000000')
        ->and((string) $orderLine->unit_price)->toBe('120.000000')
        ->and((string) $orderLine->net_total)->toBe('171.000000')
        ->and((int) DocumentSequence::query()
            ->where('company_id', $company->getKey())
            ->where('document_type', DocumentType::SalesOrder->value)
            ->value('next_value'))->toBe(2);

    $secondPdf = $this->actingAs($actor)->withSession($session)
        ->get('/quotes/'.$quote->getKey().'/finalized.pdf')->assertOk();
    $secondBytes = $secondPdf->getContent();

    expect($secondBytes)->toBe($firstBytes)
        ->and(QuoteFinalizedDocument::query()->where('quote_id', $quote->getKey())->count())->toBe(1)
        ->and((int) QuoteFinalizedDocument::query()->where('quote_id', $quote->getKey())->value('id'))->toBe((int) $document->getKey())
        ->and((string) QuoteFinalizedDocument::query()->where('quote_id', $quote->getKey())->value('pdf_sha256'))->toBe(hash('sha256', $firstBytes));
});

/** @return array{Company, Account, Product} */
function m56Fixture(): array
{
    $company = Company::query()->create(['code' => 'M56', 'name' => 'M5.6 Exit Gate Company']);
    $account = Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'CUST',
        'type' => AccountType::Customer,
        'status' => AccountStatus::Active,
        'legal_name' => 'M5.6 Customer',
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
        'code' => 'SKU-M56',
        'status' => ProductStatus::Active,
        'name' => 'M5.6 Product',
        'category_id' => $category->getKey(),
        'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(),
        'sale_price_net' => '100.000000',
        'purchase_price_net' => '60.000000',
    ]);

    DocumentSequence::query()->create([
        'company_id' => $company->getKey(),
        'document_type' => DocumentType::Quote,
        'series_code' => 'default',
        'prefix' => 'Q-',
        'padding' => 4,
        'next_value' => 1,
        'is_active' => true,
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(),
        'document_type' => DocumentType::SalesOrder,
        'series_code' => 'default',
        'prefix' => 'SO-',
        'padding' => 4,
        'next_value' => 1,
        'is_active' => true,
    ]);

    return [$company, $account, $product];
}

function m56Actor(Company $company): User
{
    $user = User::query()->create([
        'name' => 'M5.6 Actor',
        'email' => 'm56@example.test',
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
        'code' => 'm56-exit-gate',
        'name' => 'M5.6 Exit Gate',
        'is_active' => true,
    ]);

    foreach ([PermissionKey::QuoteView, PermissionKey::QuoteManage, PermissionKey::QuoteApprove] as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}
