<?php

use App\Foundation\Correlation\CorrelationContext;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Attachment;
use App\Modules\Core\Models\AuditEntry;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\FileAsset;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\User;
use App\Modules\Products\Actions\UpdateProductSuppliers;
use App\Modules\Products\Enums\ProductFileKind;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Files\ProductFileManager;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductFile;
use App\Modules\Products\Models\ProductSupplier;
use App\Modules\Products\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
    Storage::fake('local');
});

it('syncs supplier and mixed accounts with deterministic audit evidence', function (): void {
    [$company, $actor, $product] = m34DomainContext('M34-SUP-A');
    $supplier = m34Account($company, 'SUP-1', AccountType::Supplier, AccountStatus::Active);
    $mixed = m34Account($company, 'MIX-1', AccountType::Mixed, AccountStatus::Active);

    $updated = app(UpdateProductSuppliers::class)->handle(
        $product->getKey(),
        [$mixed->getKey(), $supplier->getKey(), $mixed->getKey()],
    );

    expect($updated->supplierRelations)->toHaveCount(2)
        ->and(ProductSupplier::query()->where('company_id', $company->getKey())->pluck('account_id')->sort()->values()->all())
        ->toBe([$supplier->getKey(), $mixed->getKey()]);

    $audit = AuditEntry::query()
        ->where('action', AuditAction::ProductSuppliersUpdated->value)
        ->where('target_id', $product->getKey())
        ->firstOrFail();

    expect($audit->correlation_id)->toBe('m3-4-product-resources-test')
        ->and($audit->before_state['supplier_account_ids'])->toBe([])
        ->and($audit->after_state['supplier_account_ids'])->toBe([$supplier->getKey(), $mixed->getKey()]);

    app(CorrelationContext::class)->set('m3-4-supplier-remove');
    test()->actingAs($actor);
    app(UpdateProductSuppliers::class)->handle($product->getKey(), [$mixed->getKey()]);

    expect(ProductSupplier::query()->where('product_id', $product->getKey())->pluck('account_id')->all())
        ->toBe([$mixed->getKey()]);
});

it('rejects invalid foreign and inactive new suppliers while preserving an inactive existing relation', function (): void {
    [$company, , $product] = m34DomainContext('M34-SUP-B');
    $customer = m34Account($company, 'CUS-1', AccountType::Customer, AccountStatus::Active);
    $clearing = m34Account($company, 'CLR-1', AccountType::Clearing, AccountStatus::Active);
    $inactive = m34Account($company, 'SUP-OFF', AccountType::Supplier, AccountStatus::Inactive);
    $active = m34Account($company, 'SUP-ON', AccountType::Supplier, AccountStatus::Active);
    $foreignCompany = m34Company('M34-SUP-FOREIGN');
    $foreign = m34Account($foreignCompany, 'SUP-X', AccountType::Supplier, AccountStatus::Active);

    foreach ([$customer, $clearing, $inactive, $foreign] as $invalid) {
        expect(fn () => app(UpdateProductSuppliers::class)->handle($product->getKey(), [$invalid->getKey()]))
            ->toThrow(ValidationException::class);
    }

    app(UpdateProductSuppliers::class)->handle($product->getKey(), [$active->getKey()]);
    $active->update(['status' => AccountStatus::Inactive]);

    app(UpdateProductSuppliers::class)->handle($product->getKey(), [$active->getKey()]);
    expect(ProductSupplier::query()->where('product_id', $product->getKey())->where('account_id', $active->getKey())->exists())
        ->toBeTrue();

    app(UpdateProductSuppliers::class)->handle($product->getKey(), []);
    expect(ProductSupplier::query()->where('product_id', $product->getKey())->exists())->toBeFalse();

    expect(fn () => app(UpdateProductSuppliers::class)->handle($product->getKey(), [$active->getKey()]))
        ->toThrow(ValidationException::class);
});

it('enforces supplier and product file company ownership at PostgreSQL level', function (): void {
    [$companyA, $actorA, $productA] = m34DomainContext('M34-DB-A');
    $companyB = m34Company('M34-DB-B');
    $supplierB = m34Account($companyB, 'SUP-B', AccountType::Supplier, AccountStatus::Active);

    expect(fn () => DB::table('product_suppliers')->insert([
        'company_id' => $companyA->getKey(),
        'product_id' => $productA->getKey(),
        'account_id' => $supplierB->getKey(),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    app(ActiveCompanyContext::class)->set($companyB);
    test()->actingAs(m34SimpleUser('m34-db-b@example.test'));
    $productB = m34Product($companyB, 'SKU-B');
    $attachmentB = m34ProductTechnicalAttachment($productB, 'foreign-tech.txt', 'foreign technical');

    expect(fn () => DB::table('product_files')->insert([
        'company_id' => $companyA->getKey(),
        'product_id' => $productA->getKey(),
        'attachment_id' => $attachmentB->getKey(),
        'kind' => ProductFileKind::Technical->value,
        'position' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    test()->actingAs($actorA);
});

it('keeps product resources viewing separate from management and protects foreign product ids', function (): void {
    $companyA = m34Company('M34-AUTH-A');
    $companyB = m34Company('M34-AUTH-B');
    $productA = m34Product($companyA, 'AUTH-A');
    $productB = m34Product($companyB, 'AUTH-B');
    $viewer = m34Actor($companyA, [PermissionKey::ProductView], 'viewer');
    $manager = m34Actor($companyA, [PermissionKey::ProductView, PermissionKey::ProductManage], 'manager');
    $none = m34Actor($companyA, [], 'none');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/inventory/products/'.$productA->getKey().'/resources')
        ->assertOk()
        ->assertSee('Tedarikçiler')
        ->assertSee('Teknik Dosyalar')
        ->assertSee('Medya')
        ->assertDontSee('Tedarikçileri Kaydet')
        ->assertDontSee('Teknik Dosya Yükle')
        ->assertDontSee('Medya Yükle');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->put('/inventory/products/'.$productA->getKey().'/resources/suppliers', ['supplier_ids' => []])
        ->assertForbidden();

    $this->actingAs($none)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/inventory/products/'.$productA->getKey().'/resources')
        ->assertForbidden();

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/inventory/products/'.$productB->getKey().'/resources')
        ->assertNotFound();
    $this->actingAs($manager)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->put('/inventory/products/'.$productB->getKey().'/resources/suppliers', ['supplier_ids' => []])
        ->assertNotFound();
});

it('stores technical and media resources privately validates media mime and detaches without deleting bytes', function (): void {
    $company = m34Company('M34-FILE-A');
    $manager = m34Actor($company, [PermissionKey::ProductView, PermissionKey::ProductManage], 'files');
    $product = m34Product($company, 'FILE-SKU');

    $this->actingAs($manager)
        ->withHeader('X-Correlation-ID', 'm34-tech-upload')
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/inventory/products/'.$product->getKey().'/resources/files', [
            'kind' => ProductFileKind::Technical->value,
            'file' => UploadedFile::fake()->createWithContent('manual.txt', 'technical data'),
            'label' => 'Montaj Föyü',
        ])
        ->assertRedirect();

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z2S8AAAAASUVORK5CYII=', true);
    expect($png)->toBeString();

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/inventory/products/'.$product->getKey().'/resources/files', [
            'kind' => ProductFileKind::Media->value,
            'file' => UploadedFile::fake()->createWithContent('product.png', $png),
            'label' => 'Ürün Görseli',
        ])
        ->assertRedirect();

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->from('/inventory/products/'.$product->getKey().'/resources')
        ->post('/inventory/products/'.$product->getKey().'/resources/files', [
            'kind' => ProductFileKind::Media->value,
            'file' => UploadedFile::fake()->createWithContent('not-image.txt', 'not an image'),
        ])
        ->assertRedirect('/inventory/products/'.$product->getKey().'/resources')
        ->assertSessionHasErrors('file');

    $technical = ProductFile::query()
        ->where('company_id', $company->getKey())
        ->where('product_id', $product->getKey())
        ->where('kind', ProductFileKind::Technical->value)
        ->firstOrFail();
    $media = ProductFile::query()
        ->where('company_id', $company->getKey())
        ->where('product_id', $product->getKey())
        ->where('kind', ProductFileKind::Media->value)
        ->firstOrFail();

    expect(ProductFile::query()->where('product_id', $product->getKey())->count())->toBe(2)
        ->and($technical->position)->toBe(0)
        ->and($media->position)->toBe(0);

    $attachment = Attachment::query()->findOrFail($technical->attachment_id);
    $asset = FileAsset::query()->findOrFail($attachment->file_asset_id);
    $storageKey = (string) $asset->storage_key;
    Storage::disk('local')->assertExists($storageKey);

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/inventory/products/'.$product->getKey().'/resources/files/'.$technical->getKey().'/download')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->actingAs($manager)
        ->withHeader('X-Correlation-ID', 'm34-tech-detach')
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/inventory/products/'.$product->getKey().'/resources/files/'.$technical->getKey().'/detach')
        ->assertRedirect();

    $attachment->refresh();
    $asset->refresh();
    expect($attachment->detached_at)->not->toBeNull()
        ->and($asset->archived_at)->not->toBeNull();
    Storage::disk('local')->assertExists($storageKey);

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/inventory/products/'.$product->getKey().'/resources/files/'.$technical->getKey().'/download')
        ->assertNotFound();
    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/inventory/products/'.$product->getKey().'/resources')
        ->assertOk()
        ->assertDontSee('manual.txt')
        ->assertSee('product.png');

    expect(AuditEntry::query()->where('action', AuditAction::FileUploaded->value)->count())->toBe(2)
        ->and(AuditEntry::query()->where('action', AuditAction::AttachmentDetached->value)->count())->toBe(1);
});

/** @return array{Company, User, Product} */
function m34DomainContext(string $companyCode): array
{
    $company = m34Company($companyCode);
    $actor = m34SimpleUser(strtolower($companyCode).'@m34-domain.test');
    $product = m34Product($company, 'DOMAIN-SKU');

    app(ActiveCompanyContext::class)->set($company);
    app(CorrelationContext::class)->set('m3-4-product-resources-test');
    test()->actingAs($actor);

    return [$company, $actor, $product];
}

function m34Company(string $code): Company
{
    return Company::query()->create([
        'code' => $code,
        'name' => 'Company '.$code,
    ]);
}

function m34SimpleUser(string $email): User
{
    return User::query()->create([
        'name' => 'M3.4 User',
        'email' => $email,
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);
}

/** @param list<PermissionKey> $permissions */
function m34Actor(Company $company, array $permissions, string $suffix): User
{
    $user = m34SimpleUser(strtolower((string) $company->code).'-'.$suffix.'@m34-auth.test');
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'is_active' => true,
        'joined_at' => now(),
    ]);
    $role = Role::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'm34-'.$suffix,
        'name' => 'M3.4 '.$suffix,
        'is_active' => true,
    ]);

    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}

function m34Account(Company $company, string $code, AccountType $type, AccountStatus $status): Account
{
    return Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => $code,
        'type' => $type,
        'status' => $status,
        'legal_name' => 'Account '.$code,
        'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null,
        'tax_office' => null,
        'book_currency_code' => 'TRY',
        'due_days' => 0,
        'discount_rate' => '0.000000',
        'risk_limit' => '0.000000',
    ]);
}

function m34Product(Company $company, string $code): Product
{
    $category = Category::query()->create([
        'company_id' => $company->getKey(),
        'code' => $code.'-CAT',
        'name' => 'Category '.$code,
        'is_active' => true,
    ]);
    $unit = Unit::query()->create([
        'company_id' => $company->getKey(),
        'code' => mb_substr($code.'-UNIT', 0, 32),
        'name' => 'Unit '.$code,
        'is_active' => true,
    ]);
    $tax = Tax::query()->create([
        'company_id' => $company->getKey(),
        'code' => mb_substr($code.'-TAX', 0, 64),
        'name' => 'Tax '.$code,
        'rate' => '20.000000',
        'is_active' => true,
    ]);

    return Product::query()->create([
        'company_id' => $company->getKey(),
        'code' => $code,
        'status' => ProductStatus::Active,
        'name' => 'Product '.$code,
        'category_id' => $category->getKey(),
        'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(),
        'sale_price_net' => '100.000000',
        'purchase_price_net' => '50.000000',
    ]);
}

function m34ProductTechnicalAttachment(Product $product, string $name, string $content): Attachment
{
    $file = app(ProductFileManager::class)->upload(
        $product->getKey(),
        ProductFileKind::Technical,
        UploadedFile::fake()->createWithContent($name, $content),
    );

    return Attachment::query()->findOrFail($file->attachment_id);
}
