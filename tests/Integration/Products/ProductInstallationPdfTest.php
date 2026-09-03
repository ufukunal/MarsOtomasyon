<?php

use App\Foundation\Correlation\CorrelationContext;
use App\Foundation\Correlation\CorrelationIdFactory;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\User;
use App\Modules\Products\Documents\ProductInstallationDocumentService;
use App\Modules\Products\Enums\ProductFileKind;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Files\ProductFileManager;
use App\Modules\Products\Files\ProductImageOperations;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    Storage::fake('local');
    app(CorrelationContext::class)->set(app(CorrelationIdFactory::class)->resolve(null));
});

it('publishes immutable byte-stable installation PDFs and versions changed drafts', function (): void {
    [$product] = m22InstallationContext('M22-A');
    $image = m22InstallationImage($product, 'install-a.png');
    $service = app(ProductInstallationDocumentService::class);

    $service->saveDraft(
        $product->getKey(),
        'Pompa Kurulum Rehberi',
        'Kurulumdan önce enerji izolasyonunu doğrulayın.',
        ['Tabanı terazileyin.', 'Pompayı ankrajlara sabitleyin.'],
        ['Enerji vermeden önce kaplin muhafazasını takın.'],
        ['Tork anahtarı', 'Su terazisi'],
        ['M12 ankraj', 'Titreşim takozu'],
        [$image->getKey()],
    );

    $v1 = $service->publish($product->getKey());
    $v1Bytes = $service->verifiedBytes($v1);
    $replay = $service->publish($product->getKey());

    expect($v1->version)->toBe(1)
        ->and($v1->renderer_version)->toBe(ProductInstallationDocumentService::RENDERER_VERSION)
        ->and($v1->snapshot['guide']['steps'])->toBe(['Tabanı terazileyin.', 'Pompayı ankrajlara sabitleyin.'])
        ->and($v1->snapshot['guide']['warnings'])->toHaveCount(1)
        ->and($v1->snapshot['guide']['tools'])->toHaveCount(2)
        ->and($v1->snapshot['guide']['parts'])->toHaveCount(2)
        ->and($v1->snapshot['guide']['images'])->toHaveCount(1)
        ->and($v1Bytes)->toStartWith('%PDF-')
        ->and(hash('sha256', $v1Bytes))->toBe($v1->pdf_sha256)
        ->and($replay->getKey())->toBe($v1->getKey())
        ->and($service->verifiedBytes($replay))->toBe($v1Bytes);

    $service->saveDraft(
        $product->getKey(),
        'Pompa Kurulum Rehberi',
        'Revize kurulum metni.',
        ['Tabanı terazileyin.', 'Pompayı sabitleyin.', 'Elektrik bağlantısını kontrol edin.'],
        ['LOTO prosedürünü uygulayın.'],
        ['Tork anahtarı'],
        ['M12 ankraj'],
        [$image->getKey()],
    );
    $v2 = $service->publish($product->getKey());

    expect($v2->version)->toBe(2)
        ->and($v2->getKey())->not->toBe($v1->getKey())
        ->and($v2->source_fingerprint)->not->toBe($v1->source_fingerprint)
        ->and($service->verifiedBytes($v1))->toBe($v1Bytes);

    expect(fn () => DB::table('product_installation_documents')->where('id', $v1->getKey())->update(['version' => 9]))
        ->toThrow(Throwable::class);
});

it('rejects foreign or quarantined media from installation guides', function (): void {
    [$product, $company] = m22InstallationContext('M22-B');
    $otherProduct = m22InstallationProduct($company, 'M22-B-OTHER');
    $foreignImage = m22InstallationImage($otherProduct, 'foreign.png');
    $ownImage = m22InstallationImage($product, 'own.png');
    $service = app(ProductInstallationDocumentService::class);

    expect(fn () => $service->saveDraft(
        $product->getKey(), 'Kurulum', null, ['Adım'], [], [], [], [$foreignImage->getKey()],
    ))->toThrow(ValidationException::class);

    app(ProductImageOperations::class)->quarantine($product->getKey(), $ownImage->getKey(), 'M22 safety test');

    expect(fn () => $service->saveDraft(
        $product->getKey(), 'Kurulum', null, ['Adım'], [], [], [], [$ownImage->getKey()],
    ))->toThrow(ValidationException::class);
});

it('renders an A4 preview payload with all installation content dimensions', function (): void {
    [$product] = m22InstallationContext('M22-C');
    $service = app(ProductInstallationDocumentService::class);
    $service->saveDraft(
        $product->getKey(), 'Montaj', 'Giriş', ['Adım 1'], ['Uyarı 1'], ['Alet 1'], ['Parça 1'], [],
    );

    $payload = $service->previewPayload($product->getKey());
    $html = view('products.installation.document', [
        ...$payload,
        'version' => null,
        'rendererVersion' => ProductInstallationDocumentService::RENDERER_VERSION,
        'sourceFingerprint' => null,
        'isPreview' => true,
    ])->render();

    expect($html)->toContain('@page { size: A4')
        ->toContain('Adım 1')
        ->toContain('Uyarı 1')
        ->toContain('Alet 1')
        ->toContain('Parça 1')
        ->toContain('A4 önizleme');
});

/** @return array{Product, Company, User} */
function m22InstallationContext(string $code): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $user = User::query()->create([
        'name' => 'M22 User',
        'email' => strtolower($code).'@m22.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);
    $product = m22InstallationProduct($company, $code.'-PRODUCT');
    app(ActiveCompanyContext::class)->set($company);
    test()->actingAs($user);

    return [$product, $company, $user];
}

function m22InstallationProduct(Company $company, string $code): Product
{
    $category = Category::query()->create([
        'company_id' => $company->getKey(), 'code' => $code.'-CAT', 'name' => 'Category '.$code, 'is_active' => true,
    ]);
    $unit = Unit::query()->create([
        'company_id' => $company->getKey(), 'code' => mb_substr($code.'-UNIT', 0, 32), 'name' => 'Unit '.$code, 'is_active' => true,
    ]);
    $tax = Tax::query()->create([
        'company_id' => $company->getKey(), 'code' => mb_substr($code.'-TAX', 0, 64), 'name' => 'Tax '.$code, 'rate' => '20.000000', 'is_active' => true,
    ]);

    return Product::query()->create([
        'company_id' => $company->getKey(), 'code' => $code, 'status' => ProductStatus::Active,
        'name' => 'Product '.$code, 'category_id' => $category->getKey(), 'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(), 'sale_price_net' => '100.000000', 'purchase_price_net' => '50.000000',
    ]);
}

function m22InstallationImage(Product $product, string $name): mixed
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z2S8AAAAASUVORK5CYII=', true);
    if (! is_string($png)) {
        throw new RuntimeException('PNG fixture decode failed.');
    }

    return app(ProductFileManager::class)->upload(
        $product->getKey(), ProductFileKind::Media, UploadedFile::fake()->createWithContent($name, $png),
    );
}
