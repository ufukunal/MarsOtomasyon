<?php

use App\Modules\Core\Enums\AttachmentTargetType;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Extraction\DocumentExtractionRegistry;
use App\Modules\Core\Extraction\DocumentExtractionService;
use App\Modules\Core\Models\Attachment;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\FileAsset;
use App\Modules\Core\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Fixtures\Core\M29FixtureExtractor;

uses(DatabaseMigrations::class);

it('deduplicates extraction by source and requires human review before returning draft payload', function (): void {
    $company = Company::query()->create(['code' => 'M29', 'name' => 'M29 Company']);
    $user = User::query()->create([
        'name' => 'Reviewer',
        'email' => 'm29-reviewer@example.test',
        'password' => 'password',
        'status' => UserStatus::Active,
    ]);
    $asset = FileAsset::query()->create([
        'company_id' => $company->getKey(),
        'uploaded_by_user_id' => $user->getKey(),
        'storage_disk' => 'local',
        'storage_key' => 'm29/invoice.pdf',
        'original_name' => 'invoice.pdf',
        'mime_type' => 'application/pdf',
        'client_extension' => 'pdf',
        'size_bytes' => 1200,
        'sha256' => hash('sha256', 'm29-invoice'),
    ]);
    $attachment = Attachment::query()->create([
        'company_id' => $company->getKey(),
        'file_asset_id' => $asset->getKey(),
        'attachable_type' => AttachmentTargetType::Company,
        'attachable_id' => $company->getKey(),
        'label' => 'Invoice',
        'attached_by_user_id' => $user->getKey(),
        'attached_at' => now(),
    ]);
    $provider = new M29FixtureExtractor;
    $registry = new DocumentExtractionRegistry;
    $registry->register($provider);
    $service = new DocumentExtractionService($registry);

    $first = $service->extract((int) $company->getKey(), (int) $attachment->getKey(), 'fixture', 0.85);
    $second = $service->extract((int) $company->getKey(), (int) $attachment->getKey(), 'fixture', 0.85);

    expect($first['id'])->toBe($second['id'])
        ->and($first['status'])->toBe('awaiting_review')
        ->and($first['requires_review'])->toBeTrue()
        ->and($provider->calls)->toBe(1);

    $draft = $service->review((int) $company->getKey(), $first['id'], (int) $user->getKey(), ['total' => '120.00']);
    $replay = $service->review((int) $company->getKey(), $first['id'], (int) $user->getKey());

    expect($draft['document_type'])->toBe('supplier_invoice')
        ->and($draft['fields']['invoice_no'])->toBe('INV-29')
        ->and($draft['fields']['total'])->toBe('120.00')
        ->and($replay)->toBe($draft);

    expect(fn () => $service->extract((int) $company->getKey() + 999, (int) $attachment->getKey(), 'fixture'))
        ->toThrow(DomainException::class, 'not found for company');
});
