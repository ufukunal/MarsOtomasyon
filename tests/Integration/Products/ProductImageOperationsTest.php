<?php

use App\Foundation\Correlation\CorrelationContext;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Attachment;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\FileAsset;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\User;
use App\Modules\Products\Enums\ProductFileKind;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Files\ProductFileManager;
use App\Modules\Products\Files\ProductImageOperations;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductFile;
use App\Modules\Products\Models\Unit;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    Storage::fake('local');
    app(CorrelationContext::class)->set('00000000-0000-4000-8000-000000000021');
});

it('manages main gallery order destinations transforms and provider validation', function (): void {
    [$company, $actor, $product] = m21ImageContext('M21-OPS-A', 'M21-A');
    $first = m21ImageUpload($product, 'first.png');
    $second = m21ImageUpload($product, 'second.png');
    $third = m21ImageUpload($product, 'third.png');
    $operations = app(ProductImageOperations::class);

    expect($first->is_main)->toBeTrue()
        ->and($second->is_main)->toBeFalse()
        ->and($third->is_main)->toBeFalse();

    $operations->setMain($product->getKey(), $second->getKey());
    $operations->reorder($product->getKey(), [$third->getKey(), $second->getKey(), $first->getKey()]);
    $updated = $operations->updateDestinations($product->getKey(), $second->getKey(), ['trendyol', 'site', 'trendyol']);
    $updated = $operations->updateTransformMetadata($product->getKey(), $updated->getKey(), [
        'crop' => ['x' => 10, 'y' => 20, 'width' => 600, 'height' => 600],
        'rotate' => 90,
        'flip' => ['horizontal' => true, 'vertical' => false],
        'resize' => ['width' => 1200, 'height' => 1200, 'mode' => 'contain'],
    ]);
    $updated = $operations->recordProviderValidation(
        $product->getKey(),
        $updated->getKey(),
        'trendyol',
        'warning',
        ['Minimum çözünürlük sınırına yakın.'],
        ['min_width' => 1200],
    );

    expect(ProductFile::query()->where('product_id', $product->getKey())->where('is_main', true)->value('id'))
        ->toBe($second->getKey())
        ->and(ProductFile::query()->where('product_id', $product->getKey())->orderBy('position')->pluck('id')->all())
        ->toBe([$third->getKey(), $second->getKey(), $first->getKey()])
        ->and($updated->destinations)->toBe(['site', 'trendyol'])
        ->and($updated->transform_metadata['rotate'])->toBe(90)
        ->and($updated->provider_validation['status'])->toBe('warning');

    expect(fn () => $operations->reorder($product->getKey(), [$first->getKey(), $second->getKey()]))
        ->toThrow(ValidationException::class);
    expect(fn () => $operations->updateTransformMetadata($product->getKey(), $first->getKey(), ['rotate' => 45]))
        ->toThrow(ValidationException::class);

    test()->actingAs($actor);
    app(ActiveCompanyContext::class)->set($company);
});

it('copies and moves media by relinking the same file asset without duplicating bytes', function (): void {
    [$company, , $sourceProduct] = m21ImageContext('M21-OPS-B', 'M21-B-SRC');
    $targetProduct = m21ImageProduct($company, 'M21-B-DST');
    $source = m21ImageUpload($sourceProduct, 'shared.png');
    $moveSource = m21ImageUpload($sourceProduct, 'move.png');
    $operations = app(ProductImageOperations::class);

    $copy = $operations->copy($sourceProduct->getKey(), $source->getKey(), $targetProduct->getKey());
    $sourceAttachment = Attachment::query()->findOrFail($source->attachment_id);
    $copyAttachment = Attachment::query()->findOrFail($copy->attachment_id);

    expect($copyAttachment->getKey())->not->toBe($sourceAttachment->getKey())
        ->and($copyAttachment->file_asset_id)->toBe($sourceAttachment->file_asset_id)
        ->and(FileAsset::query()->count())->toBe(2);

    $moved = $operations->move($sourceProduct->getKey(), $moveSource->getKey(), $targetProduct->getKey());
    $moveSourceAttachment = Attachment::query()->findOrFail($moveSource->attachment_id);
    $movedAttachment = Attachment::query()->findOrFail($moved->attachment_id);

    expect($moveSourceAttachment->detached_at)->not->toBeNull()
        ->and($movedAttachment->file_asset_id)->toBe($moveSourceAttachment->file_asset_id)
        ->and(FileAsset::query()->count())->toBe(2)
        ->and(ProductFile::query()->where('product_id', $sourceProduct->getKey())->where('is_main', true)->value('id'))
        ->toBe($source->getKey());
});

it('quarantines the file asset globally and blocks image operations until released', function (): void {
    [, , $product] = m21ImageContext('M21-OPS-C', 'M21-C');
    $media = m21ImageUpload($product, 'quarantine.png');
    $operations = app(ProductImageOperations::class);

    $asset = $operations->quarantine($product->getKey(), $media->getKey(), 'Provider malware scan review');
    expect($asset->quarantined_at)->not->toBeNull()
        ->and($asset->quarantine_reason)->toBe('Provider malware scan review');

    expect(fn () => $operations->updateDestinations($product->getKey(), $media->getKey(), ['site']))
        ->toThrow(ValidationException::class);

    $released = $operations->releaseQuarantine($product->getKey(), $media->getKey());
    expect($released->quarantined_at)->toBeNull()
        ->and($released->quarantine_reason)->toBeNull();

    expect($operations->updateDestinations($product->getKey(), $media->getKey(), ['site'])->destinations)
        ->toBe(['site']);
});

/** @return array{Company, User, Product} */
function m21ImageContext(string $companyCode, string $productCode): array
{
    $company = Company::query()->create(['code' => $companyCode, 'name' => 'Company '.$companyCode]);
    $actor = User::query()->create([
        'name' => 'M21 Image User',
        'email' => strtolower($companyCode).'@m21-image.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);
    $product = m21ImageProduct($company, $productCode);
    app(ActiveCompanyContext::class)->set($company);
    test()->actingAs($actor);

    return [$company, $actor, $product];
}

function m21ImageProduct(Company $company, string $code): Product
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

function m21ImageUpload(Product $product, string $name): ProductFile
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
