<?php

use App\Foundation\Identity\SourceEffectIdentity;
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
use App\Modules\Core\Enums\PostingPeriodStatus;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\DocumentSequence;
use App\Modules\Core\Models\FileAsset;
use App\Modules\Core\Models\PostingPeriod;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\User;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\SalesInvoices\Documents\SalesInvoiceEDocumentLifecycleService;
use App\Modules\SalesInvoices\Documents\SalesInvoiceFinalizedDocumentService;
use App\Modules\SalesInvoices\Enums\SalesInvoiceEDocumentEventType;
use App\Modules\SalesInvoices\Enums\SalesInvoiceEDocumentType;
use App\Modules\SalesInvoices\Enums\SalesInvoiceMode;
use App\Modules\SalesInvoices\Models\SalesInvoice;
use App\Modules\SalesInvoices\Models\SalesInvoiceEDocumentEvent;
use App\Modules\SalesInvoices\Models\SalesInvoiceFinalizedDocument;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
    Storage::fake('local');
});

it('rejects finalized document routes while invoice is draft', function (): void {
    [$company, $account, $product, $billing, $warehouse, $location, $manager] = m86Fixture('M86-DRAFT');
    m86Opening($company, $product, $warehouse, $location, '10');
    $invoice = m86DirectInvoice($this, $company, $manager, $account, $product, $billing, $warehouse, $location, '2');

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->get(route('sales-invoices.finalized.show', $invoice->getKey()))->assertStatus(409);
    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->get(route('sales-invoices.finalized.pdf', $invoice->getKey()))->assertStatus(409);

    expect(SalesInvoiceFinalizedDocument::query()->count())->toBe(0);
});

it('freezes one verified PDF and keeps it byte-identical after invoice cancellation', function (): void {
    [$company, $account, $product, $billing, $warehouse, $location, $manager] = m86Fixture('M86-PDF');
    m86Opening($company, $product, $warehouse, $location, '10');
    $invoice = m86DirectInvoice($this, $company, $manager, $account, $product, $billing, $warehouse, $location, '2');

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post(route('sales-invoices.finalize', $invoice->getKey()))->assertRedirect();

    $first = $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->get(route('sales-invoices.finalized.pdf', $invoice->getKey()))
        ->assertOk()->assertHeader('content-type', 'application/pdf')
        ->assertHeader('x-document-version', SalesInvoiceFinalizedDocumentService::RENDERER_VERSION);
    $firstBytes = $first->getContent();

    $document = SalesInvoiceFinalizedDocument::query()->with('fileAsset')->firstOrFail();
    $asset = $document->fileAsset;
    expect($firstBytes)->toBeString()->and(str_starts_with($firstBytes, '%PDF-'))->toBeTrue()
        ->and($asset)->toBeInstanceOf(FileAsset::class)
        ->and($document->pdf_sha256)->toBe(hash('sha256', $firstBytes))
        ->and($asset->sha256)->toBe($document->pdf_sha256);

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post(route('sales-invoices.cancel', $invoice->getKey()))->assertRedirect();
    $second = $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->get(route('sales-invoices.finalized.pdf', $invoice->getKey()))->assertOk();

    expect($second->getContent())->toBe($firstBytes)
        ->and(SalesInvoiceFinalizedDocument::query()->count())->toBe(1)
        ->and(fn () => DB::table('sales_invoice_finalized_documents')->where('id', $document->getKey())->update(['renderer_version' => 'sales-invoice-pdf.v2']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('file_assets')->where('id', $asset->getKey())->update(['original_name' => 'tampered.pdf']))->toThrow(QueryException::class);

    Storage::disk('local')->put((string) $asset->storage_key, '%PDF-tampered');
    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->get(route('sales-invoices.finalized.pdf', $invoice->getKey()))->assertStatus(410);
});

it('provides an append-only provider-neutral e-document lifecycle seam', function (): void {
    [$company, $account, $product, $billing, $warehouse, $location, $manager] = m86Fixture('M86-EDOC');
    m86Opening($company, $product, $warehouse, $location, '10');
    $invoice = m86DirectInvoice($this, $company, $manager, $account, $product, $billing, $warehouse, $location, '1');
    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post(route('sales-invoices.finalize', $invoice->getKey()))->assertRedirect();

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()]);
    $service = app(SalesInvoiceEDocumentLifecycleService::class);
    $prepared = $service->append((int) $invoice->getKey(), SalesInvoiceEDocumentType::EInvoice, SalesInvoiceEDocumentEventType::Prepared);
    $submitted = $service->append((int) $invoice->getKey(), SalesInvoiceEDocumentType::EInvoice, SalesInvoiceEDocumentEventType::Submitted, 'stub.provider', null, str_repeat('a', 64));
    $accepted = $service->append((int) $invoice->getKey(), SalesInvoiceEDocumentType::EInvoice, SalesInvoiceEDocumentEventType::Accepted, 'stub.provider', 'EXT-1');

    expect($prepared->eventTypeEnum())->toBe(SalesInvoiceEDocumentEventType::Prepared)
        ->and($submitted->provider_key)->toBe('stub.provider')
        ->and($accepted->eventTypeEnum())->toBe(SalesInvoiceEDocumentEventType::Accepted)
        ->and($service->current((int) $invoice->getKey(), SalesInvoiceEDocumentType::EInvoice)?->eventTypeEnum())->toBe(SalesInvoiceEDocumentEventType::Accepted)
        ->and(fn () => DB::table('sales_invoice_e_document_events')->where('id', $accepted->getKey())->update(['external_document_id' => 'HACK']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('sales_invoice_e_document_events')->where('id', $accepted->getKey())->delete())->toThrow(QueryException::class);

    expect(fn () => $service->append((int) $invoice->getKey(), SalesInvoiceEDocumentType::EInvoice, SalesInvoiceEDocumentEventType::Cancelled, 'stub.provider'))->toThrow(LogicException::class);

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post(route('sales-invoices.cancel', $invoice->getKey()))->assertRedirect();
    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()]);
    $cancelled = $service->append((int) $invoice->getKey(), SalesInvoiceEDocumentType::EInvoice, SalesInvoiceEDocumentEventType::Cancelled, 'stub.provider', 'EXT-1');

    expect($cancelled->eventTypeEnum())->toBe(SalesInvoiceEDocumentEventType::Cancelled)
        ->and(SalesInvoiceEDocumentEvent::query()->count())->toBe(4);
});

/** @return array{Company,Account,Product,AccountAddress,Warehouse,WarehouseLocation,User} */
function m86Fixture(string $code): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $account = Account::query()->create([
        'company_id' => $company->getKey(), 'code' => 'CUST', 'type' => AccountType::Customer,
        'status' => AccountStatus::Active, 'legal_name' => 'Müşteri '.$code, 'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None, 'tax_number' => null, 'tax_office' => null,
        'book_currency_code' => 'TRY', 'due_days' => 0, 'discount_rate' => '0.000000', 'risk_limit' => '0.000000',
    ]);
    $billing = AccountAddress::query()->create([
        'company_id' => $company->getKey(), 'account_id' => $account->getKey(), 'type' => AccountAddressType::Billing,
        'label' => 'Fatura', 'recipient_name' => 'Mars Teslim', 'line1' => 'Mars Cad. 86', 'line2' => null,
        'district' => 'Şişli', 'city' => 'İstanbul', 'postal_code' => '34360', 'country_code' => 'TR', 'is_default' => true,
    ]);
    $category = Category::query()->create(['company_id' => $company->getKey(), 'code' => 'CAT', 'name' => 'Kategori', 'is_active' => true]);
    $unit = Unit::query()->create(['company_id' => $company->getKey(), 'code' => 'ADET', 'name' => 'Adet', 'is_active' => true]);
    $tax = Tax::query()->create(['company_id' => $company->getKey(), 'code' => 'KDV20', 'name' => 'KDV %20', 'rate' => '20.000000', 'is_active' => true]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(), 'code' => 'SKU-'.$code, 'status' => ProductStatus::Active,
        'name' => 'Ürün '.$code, 'category_id' => $category->getKey(), 'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(), 'sale_price_net' => '100.000000', 'purchase_price_net' => '60.000000',
    ]);
    $warehouse = Warehouse::query()->create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Merkez Depo', 'is_active' => true]);
    $location = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(), 'warehouse_id' => $warehouse->getKey(), 'code' => 'A-01', 'name' => 'A Rafı', 'is_active' => true,
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(), 'document_type' => DocumentType::SalesInvoice, 'series_code' => 'default',
        'prefix' => 'INV-', 'padding' => 4, 'next_value' => 1, 'is_active' => true,
    ]);
    PostingPeriod::query()->create([
        'company_id' => $company->getKey(), 'code' => '2026-08', 'name' => 'Ağustos 2026',
        'starts_on' => '2026-08-01', 'ends_on' => '2026-08-31', 'status' => PostingPeriodStatus::Open, 'closed_at' => null,
    ]);

    $user = User::query()->create([
        'name' => 'M86 '.$code, 'email' => strtolower($code).'@m86.test', 'password' => 'correct-password', 'status' => UserStatus::Active,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(), 'user_id' => $user->getKey(), 'is_active' => true, 'joined_at' => now(),
    ]);
    $role = Role::query()->create(['company_id' => $company->getKey(), 'code' => 'm86', 'name' => 'M86', 'is_active' => true]);
    foreach ([PermissionKey::SalesInvoiceView, PermissionKey::SalesInvoiceManage] as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return [$company, $account, $product, $billing, $warehouse, $location, $user];
}

function m86Opening(Company $company, Product $product, Warehouse $warehouse, WarehouseLocation $location, string $quantity): void
{
    DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: new SourceEffectIdentity((int) $company->getKey(), 'sales_invoice.m86', 'opening-'.$company->code, 'inventory.opening'),
        productId: (int) $product->getKey(), warehouseId: (int) $warehouse->getKey(), locationId: (int) $location->getKey(),
        movementType: StockMovementType::OpeningIn, quantity: $quantity, unitCost: '10',
    )));
}

function m86DirectInvoice(
    TestCase $test,
    Company $company,
    User $manager,
    Account $account,
    Product $product,
    AccountAddress $billing,
    Warehouse $warehouse,
    WarehouseLocation $location,
    string $quantity,
): SalesInvoice {
    $test->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])->post('/sales-invoices', [
        'series_code' => 'default', 'mode' => SalesInvoiceMode::Direct->value, 'account_id' => $account->getKey(),
        'source_billing_address_id' => $billing->getKey(), 'invoice_date' => '2026-08-27', 'document_discount_rate' => '0',
        'lines' => [[
            'product_id' => $product->getKey(), 'quantity' => $quantity,
            'allocation_key' => $warehouse->getKey().':'.$location->getKey(), 'unit_price' => '100',
            'price_basis' => 'net', 'line_discount_rate' => '0', 'tax_zero_reason_id' => null,
        ]],
    ])->assertRedirect();

    return SalesInvoice::query()->where('company_id', $company->getKey())->latest('id')->firstOrFail();
}
