<?php

use App\Foundation\Correlation\CorrelationContext;
use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\AuditEntry;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\User;
use App\Modules\Products\Enums\ProductFileKind;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Files\ProductFileManager;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductFile;
use App\Modules\Products\Models\Unit;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
    Storage::fake('local');
    app(CorrelationContext::class)->set('00000000-0000-4000-8000-000000000021');
});

it('exposes the M21 image workspace to viewers while keeping mutations behind product management', function (): void {
    $company = m21HttpCompany('M21-HTTP-A');
    $product = m21HttpProduct($company, 'M21-A');
    $viewer = m21HttpActor($company, [PermissionKey::ProductView], 'viewer');
    $manager = m21HttpActor($company, [PermissionKey::ProductView, PermissionKey::ProductManage], 'manager');

    app(ActiveCompanyContext::class)->set($company);
    test()->actingAs($manager);
    $first = m21HttpImageUpload($product, 'front.png');
    $second = m21HttpImageUpload($product, 'side.png');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/inventory/products/'.$product->getKey().'/resources')
        ->assertOk()
        ->assertSee('Ürün Görselleri')
        ->assertSee('Ana')
        ->assertDontSee('Hedefleri Kaydet');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/inventory/products/'.$product->getKey().'/resources/media/'.$second->getKey().'/main')
        ->assertForbidden();

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/inventory/products/'.$product->getKey().'/resources/media/'.$second->getKey().'/main')
        ->assertRedirect();

    expect(ProductFile::query()->where('product_id', $product->getKey())->where('is_main', true)->value('id'))
        ->toBe($second->getKey())
        ->and(ProductFile::query()->whereKey($first->getKey())->value('is_main'))->toBeFalse()
        ->and(m21HttpAuditOperations((int) $company->getKey(), (int) $product->getKey()))->toContain('set_main');
});

it('executes destination transform validation order copy and quarantine lifecycle through M21 routes', function (): void {
    $company = m21HttpCompany('M21-HTTP-B');
    $manager = m21HttpActor($company, [PermissionKey::ProductView, PermissionKey::ProductManage], 'manager');
    $source = m21HttpProduct($company, 'M21-B-SRC');
    $target = m21HttpProduct($company, 'M21-B-DST');
    $foreignCompany = m21HttpCompany('M21-HTTP-X');
    $foreignTarget = m21HttpProduct($foreignCompany, 'M21-X-DST');

    app(ActiveCompanyContext::class)->set($company);
    test()->actingAs($manager);
    $first = m21HttpImageUpload($source, 'first.png');
    $second = m21HttpImageUpload($source, 'second.png');

    $session = ['active_company_id' => $company->getKey()];

    $this->actingAs($manager)
        ->withSession($session)
        ->put('/inventory/products/'.$source->getKey().'/resources/media/'.$first->getKey().'/destinations', [
            'destinations' => 'site, trendyol amazon trendyol',
        ])
        ->assertRedirect();

    $this->actingAs($manager)
        ->withSession($session)
        ->put('/inventory/products/'.$source->getKey().'/resources/media/'.$first->getKey().'/transform', [
            'crop_x' => 5,
            'crop_y' => 7,
            'crop_width' => 800,
            'crop_height' => 800,
            'rotate' => 90,
            'flip_present' => 1,
            'flip_horizontal' => 1,
            'resize_width' => 1200,
            'resize_height' => 1200,
            'resize_mode' => 'contain',
        ])
        ->assertRedirect();

    $this->actingAs($manager)
        ->withSession($session)
        ->put('/inventory/products/'.$source->getKey().'/resources/media/'.$first->getKey().'/provider-validation', [
            'provider' => 'trendyol',
            'status' => 'warning',
            'messages' => "Minimum çözünürlük sınırına yakın.\nArka plan kontrol edilmeli.",
        ])
        ->assertRedirect();

    $this->actingAs($manager)
        ->withSession($session)
        ->put('/inventory/products/'.$source->getKey().'/resources/media-order', [
            'positions' => [
                $first->getKey() => 1,
                $second->getKey() => 0,
            ],
        ])
        ->assertRedirect();

    $first->refresh();
    expect($first->destinations)->toBe(['amazon', 'site', 'trendyol'])
        ->and($first->transform_metadata['rotate'])->toBe(90)
        ->and($first->transform_metadata['flip']['horizontal'])->toBeTrue()
        ->and($first->provider_validation['provider'])->toBe('trendyol')
        ->and($first->provider_validation['status'])->toBe('warning')
        ->and(ProductFile::query()->where('product_id', $source->getKey())->orderBy('position')->pluck('id')->all())
        ->toBe([$second->getKey(), $first->getKey()]);

    $this->actingAs($manager)
        ->withSession($session)
        ->post('/inventory/products/'.$source->getKey().'/resources/media/'.$first->getKey().'/copy', [
            'target_product_id' => $target->getKey(),
        ])
        ->assertRedirect();

    $copy = ProductFile::query()->where('product_id', $target->getKey())->where('kind', ProductFileKind::Media->value)->firstOrFail();
    expect($copy->attachment->file_asset_id)->toBe($first->attachment->file_asset_id)
        ->and(m21HttpAuditOperations((int) $company->getKey(), (int) $target->getKey()))->toContain('copy_in');

    $this->actingAs($manager)
        ->withSession($session)
        ->post('/inventory/products/'.$source->getKey().'/resources/media/'.$first->getKey().'/copy', [
            'target_product_id' => $foreignTarget->getKey(),
        ])
        ->assertNotFound();

    $this->actingAs($manager)
        ->withSession($session)
        ->post('/inventory/products/'.$source->getKey().'/resources/media/'.$first->getKey().'/quarantine', [
            'reason' => 'Security review',
        ])
        ->assertRedirect();

    $this->actingAs($manager)
        ->withSession($session)
        ->get('/inventory/products/'.$source->getKey().'/resources/files/'.$first->getKey().'/download')
        ->assertNotFound();

    $this->actingAs($manager)
        ->withSession($session)
        ->put('/inventory/products/'.$source->getKey().'/resources/media/'.$first->getKey().'/destinations', [
            'destinations' => 'site',
        ])
        ->assertSessionHasErrors('file');

    $this->actingAs($manager)
        ->withSession($session)
        ->post('/inventory/products/'.$source->getKey().'/resources/media/'.$first->getKey().'/release-quarantine')
        ->assertRedirect();

    $this->actingAs($manager)
        ->withSession($session)
        ->put('/inventory/products/'.$source->getKey().'/resources/media/'.$first->getKey().'/destinations', [
            'destinations' => 'site',
        ])
        ->assertRedirect();

    $sourceOperations = m21HttpAuditOperations((int) $company->getKey(), (int) $source->getKey());
    expect(ProductFile::query()->findOrFail($first->getKey())->destinations)->toBe(['site'])
        ->and($sourceOperations)->toContain('destinations')
        ->and($sourceOperations)->toContain('transform')
        ->and($sourceOperations)->toContain('provider_validation')
        ->and($sourceOperations)->toContain('reorder')
        ->and($sourceOperations)->toContain('quarantine')
        ->and($sourceOperations)->toContain('release_quarantine');
});

/** @return list<string> */
function m21HttpAuditOperations(int $companyId, int $productId): array
{
    return AuditEntry::query()
        ->where('company_id', $companyId)
        ->where('action', AuditAction::ProductMediaUpdated->value)
        ->where('target_id', (string) $productId)
        ->orderBy('id')
        ->get()
        ->map(static fn (AuditEntry $entry): string => (string) ($entry->metadata['operation'] ?? ''))
        ->filter(static fn (string $operation): bool => $operation !== '')
        ->values()
        ->all();
}

function m21HttpCompany(string $code): Company
{
    return Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
}

/** @param list<PermissionKey> $permissions */
function m21HttpActor(Company $company, array $permissions, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'M21 '.$suffix,
        'email' => strtolower((string) $company->code).'-'.$suffix.'@m21-http.test',
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
        'code' => 'm21-'.$suffix,
        'name' => 'M21 '.$suffix,
        'is_active' => true,
    ]);
    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}

function m21HttpProduct(Company $company, string $code): Product
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

function m21HttpImageUpload(Product $product, string $name): ProductFile
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z2S8AAAAASUVORK5CYII=', true);
    if (! is_string($png)) {
        throw new RuntimeException('PNG fixture decode failed.');
    }

    return app(ProductFileManager::class)->upload(
        (int) $product->getKey(),
        ProductFileKind::Media,
        UploadedFile::fake()->createWithContent($name, $png),
    );
}
