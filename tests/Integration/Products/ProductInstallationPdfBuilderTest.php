<?php

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\User;
use App\Modules\Products\Enums\ProductFileKind;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Files\ProductFileManager;
use App\Modules\Products\Files\ProductImageOperations;
use App\Modules\Products\Files\ProductInstallationPdfBuilder;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductFile;
use App\Modules\Products\Models\ProductInstallationGuideVersion;
use App\Modules\Products\Models\Unit;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    Storage::fake('local');
});

it('renders an A4 product installation preview and publishes immutable PDF versions', function (): void {
    [$company, , $product] = m22InstallationContext('M22-PDF-A', 'M22-A');
    $media = m22InstallationMedia($product, 'step.png');
    $builder = app(ProductInstallationPdfBuilder::class);
    $draft = [
        'warnings' => ['Enerjiyi kapatın.'],
        'tools' => ['Yıldız tornavida'],
        'parts' => [['name' => 'Montaj vidası', 'quantity' => '4 adet']],
        'steps' => [[
            'title' => 'Gövdeyi sabitleyin',
            'body' => "Ürünü yüzeye hizalayın.\nVidaları sıkın.",
            'image_product_file_id' => $media->getKey(),
        ]],
        'images' => [$media->getKey()],
    ];

    $html = $builder->preview($product->getKey(), $draft, 'Özel Kurulum Kılavuzu');

    expect($html)->toContain('@page { size: A4;')
        ->and($html)->toContain('Özel Kurulum Kılavuzu')
        ->and($html)->toContain('Enerjiyi kapatın.')
        ->and($html)->toContain('Yıldız tornavida')
        ->and($html)->toContain('Montaj vidası')
        ->and($html)->toContain('data:image/');

    $v1 = $builder->publish($product->getKey(), $draft, 'Özel Kurulum Kılavuzu');
    $v2 = $builder->publish($product->getKey(), $draft, 'Özel Kurulum Kılavuzu');

    expect($v1->version_no)->toBe(1)
        ->and($v2->version_no)->toBe(2)
        ->and($v1->getKey())->not->toBe($v2->getKey())
        ->and($v1->pdf_attachment_id)->not->toBeNull()
        ->and($v2->pdf_attachment_id)->not->toBe($v1->pdf_attachment_id)
        ->and(ProductInstallationGuideVersion::query()->count())->toBe(2);

    $asset = $v1->pdfAttachment?->fileAsset;
    expect($asset)->not->toBeNull()
        ->and($asset?->mime_type)->toBe('application/pdf')
        ->and(Storage::disk((string) $asset?->storage_disk)->get((string) $asset?->storage_key))
        ->toStartWith('%PDF');

    app(ActiveCompanyContext::class)->set($company);
});

it('rejects foreign or quarantined product media in installation documents', function (): void {
    [$company, , $product] = m22InstallationContext('M22-PDF-B', 'M22-B');
    $otherProduct = m22InstallationProduct($company, 'M22-B-OTHER');
    $foreignMedia = m22InstallationMedia($otherProduct, 'foreign.png');
    $localMedia = m22InstallationMedia($product, 'local.png');
    $builder = app(ProductInstallationPdfBuilder::class);
    $draft = static fn (int $imageId): array => [
        'steps' => [[
            'title' => 'Montaj',
            'body' => 'Parçayı sabitleyin.',
            'image_product_file_id' => $imageId,
        ]],
    ];

    expect(fn () => $builder->preview($product->getKey(), $draft($foreignMedia->getKey())))
        ->toThrow(ValidationException::class);

    app(ProductImageOperations::class)->quarantine($product->getKey(), $localMedia->getKey(), 'Scan review');

    expect(fn () => $builder->preview($product->getKey(), $draft($localMedia->getKey())))
        ->toThrow(ValidationException::class);
});

/** @return array{Company, User, Product} */
function m22InstallationContext(string $companyCode, string $productCode): array
{
    $company = Company::query()->create(['code' => $companyCode, 'name' => 'Company '.$companyCode]);
    $actor = User::query()->create([
        'name' => 'M22 PDF User',
        'email' => strtolower($companyCode).'@m22-pdf.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);
    $product = m22InstallationProduct($company, $productCode);
    app(ActiveCompanyContext::class)->set($company);
    test()->actingAs($actor);

    return [$company, $actor, $product];
}

function m22InstallationProduct(Company $company, string $code): Product
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

function m22InstallationMedia(Product $product, string $name): ProductFile
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z2S8AAAAASUVORK5CYII=', true);
    if (! is_string($png)) {
        throw new RuntimeException('PNG fixture decode failed.');
    }

    return app(ProductFileManager::class)->upload(
        $product->getKey(),
        ProductFileKind::Media,
        UploadedFile::fake()->createWithContent($name, $png),
    );
}
