<?php

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AttachmentTargetType;
use App\Modules\Core\Files\PrivateAttachmentManager;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\FileAsset;
use App\Modules\Products\Files\ProductFamilyMediaManager;
use App\Modules\Products\Models\ProductFile;
use App\Modules\Products\Variants\ProductVariantService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(DatabaseMigrations::class);

it('reuses private attachments for same-company family media and excludes quarantined assets', function (): void {
    $company = m25SchemaCompany('M25-MEDIA');
    m25MediaActorAndContext($company);
    $family = app(ProductVariantService::class)->createFamily((int) $company->getKey(), 'MEDIA', 'Media');
    $asset = m25MediaAsset((int) $company->getKey(), 'family.jpg');
    $manager = app(ProductFamilyMediaManager::class);

    $attachment = $manager->linkExistingAsset((int) $family->getKey(), (int) $asset->getKey(), 'Family image');
    $manager->setHero((int) $family->getKey(), (int) $attachment->getKey());
    expect($manager->hero((int) $family->getKey())?->getKey())->toBe($attachment->getKey());

    app(PrivateAttachmentManager::class)->quarantine((int) $asset->getKey(), 'test quarantine');
    expect($manager->all((int) $family->getKey()))->toHaveCount(0)
        ->and($manager->hero((int) $family->getKey()))->toBeNull();
});

it('blocks cross-company family media linkage', function (): void {
    $company = m25SchemaCompany('M25-MEDIA-A');
    $foreign = m25SchemaCompany('M25-MEDIA-B');
    m25MediaActorAndContext($company);
    $family = app(ProductVariantService::class)->createFamily((int) $company->getKey(), 'MEDIA-A', 'Media A');
    $foreignAsset = m25MediaAsset((int) $foreign->getKey(), 'foreign.jpg');

    expect(fn () => app(ProductFamilyMediaManager::class)->linkExistingAsset((int) $family->getKey(), (int) $foreignAsset->getKey()))
        ->toThrow(ModelNotFoundException::class);
});

it('falls back deterministically to active child product media and then placeholder', function (): void {
    $company = m25SchemaCompany('M25-MEDIA-FB');
    m25MediaActorAndContext($company);
    $product = m25SchemaProduct($company, 'SKU-MEDIA-FB');
    $variants = app(ProductVariantService::class);
    $family = $variants->createFamily((int) $company->getKey(), 'MEDIA-FB', 'Media Fallback');
    $dimension = $variants->addDimension((int) $company->getKey(), (int) $family->getKey(), 'color', 'Color');
    $value = $variants->addValue((int) $company->getKey(), (int) $family->getKey(), (int) $dimension->getKey(), 'red', 'Red');
    $variants->assignProduct((int) $company->getKey(), (int) $family->getKey(), (int) $product->getKey(), [(int) $dimension->getKey() => (int) $value->getKey()]);
    $asset = m25MediaAsset((int) $company->getKey(), 'product.jpg');
    $attachmentId = (int) DB::table('attachments')->insertGetId([
        'company_id' => $company->getKey(),
        'file_asset_id' => $asset->getKey(),
        'attachable_type' => AttachmentTargetType::Product->value,
        'attachable_id' => $product->getKey(),
        'attached_by_user_id' => Auth::id(),
        'attached_at' => now(),
    ]);
    ProductFile::query()->create([
        'company_id' => $company->getKey(),
        'product_id' => $product->getKey(),
        'attachment_id' => $attachmentId,
        'kind' => 'media',
        'position' => 0,
        'is_main' => true,
    ]);

    $manager = app(ProductFamilyMediaManager::class);
    expect($manager->hero((int) $family->getKey())?->getKey())->toBe($attachmentId);
    app(PrivateAttachmentManager::class)->quarantine((int) $asset->getKey(), 'blocked');
    expect($manager->hero((int) $family->getKey()))->toBeNull();
});

function m25MediaActorAndContext(Company $company): void
{
    app(ActiveCompanyContext::class)->set($company);
    $actorId = (int) DB::table('users')->insertGetId([
        'name' => 'M25 Media Actor '.Str::random(6),
        'email' => Str::lower(Str::random(12)).'@example.test',
        'password' => 'not-used',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    Auth::loginUsingId($actorId);
}

function m25MediaAsset(int $companyId, string $name): FileAsset
{
    return FileAsset::query()->create([
        'company_id' => $companyId,
        'uploaded_by_user_id' => Auth::id(),
        'storage_disk' => 'local',
        'storage_key' => 'companies/'.$companyId.'/files/'.Str::ulid(),
        'original_name' => $name,
        'mime_type' => 'image/jpeg',
        'client_extension' => 'jpg',
        'size_bytes' => 128,
        'sha256' => hash('sha256', $name.Str::random(8)),
    ]);
}
