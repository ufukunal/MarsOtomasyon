<?php

use App\Modules\Core\Enums\AttachmentTargetType;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Attachment;
use App\Modules\Core\Models\CadDerivativeJob;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\FileAsset;
use App\Modules\Core\Models\User;
use App\Modules\Core\Preview\CadDerivativeProvider;
use App\Modules\Core\Preview\CadDerivativeProviderRegistry;
use App\Modules\Core\Preview\CadDerivativeResult;
use App\Modules\Core\Preview\CadPreviewService;
use App\Modules\Core\Preview\LocalBrowserCadProvider;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('keeps the original source immutable and maps local derivatives deterministically', function (): void {
    [$company, , $asset, $attachment] = m32CadFixture('M32LOCAL', 'dxf');
    $registry = (new CadDerivativeProviderRegistry)->register(new LocalBrowserCadProvider);
    $service = new CadPreviewService($registry);

    $first = $service->requestPreview((int) $company->getKey(), (int) $attachment->getKey(), 'local');
    $replay = $service->requestPreview((int) $company->getKey(), (int) $attachment->getKey(), 'local');

    expect($first->status)->toBe('ready')
        ->and($first->preview_kind)->toBe('cad_2d')
        ->and($first->source_sha256)->toBe((string) $asset->sha256)
        ->and($replay->getKey())->toBe($first->getKey())
        ->and(CadDerivativeJob::query()->count())->toBe(1)
        ->and(FileAsset::query()->findOrFail($asset->getKey())->sha256)->toBe($asset->sha256)
        ->and(Attachment::query()->findOrFail($attachment->getKey())->file_asset_id)->toBe($asset->getKey());

    $rebuilt = $service->rebuildPreview((int) $company->getKey(), (int) $first->getKey());
    expect($rebuilt->getKey())->toBe($first->getKey())
        ->and($rebuilt->derivative_sha256)->toBe($first->derivative_sha256)
        ->and(FileAsset::query()->findOrFail($asset->getKey())->sha256)->toBe($asset->sha256);
});

it('enforces cloud opt-in, company scope, format policy and normalized provider failures', function (): void {
    [$company, , , $attachment] = m32CadFixture('M32CLOUD', 'dwg');
    $provider = new M32CloudFixtureProvider;
    $registry = (new CadDerivativeProviderRegistry)->register($provider);
    $service = new CadPreviewService($registry);

    expect(fn () => $service->requestPreview((int) $company->getKey(), (int) $attachment->getKey(), 'aps-test'))
        ->toThrow(DomainException::class, 'Cloud CAD upload is disabled');

    $service->configurePolicy((int) $company->getKey(), 'aps-test', true);
    $job = $service->requestPreview((int) $company->getKey(), (int) $attachment->getKey(), 'aps-test');
    expect($job->status)->toBe('processing')->and($provider->starts)->toBe(1);

    $ready = $service->refreshPreview((int) $company->getKey(), (int) $job->getKey());
    expect($ready->status)->toBe('ready')
        ->and($ready->preview_kind)->toBe('cad_2d')
        ->and($provider->refreshes)->toBe(1);

    expect(fn () => $service->requestPreview((int) $company->getKey() + 999, (int) $attachment->getKey(), 'aps-test'))
        ->toThrow(DomainException::class, 'not found for company');

    [, , , $maxAttachment] = m32CadFixture('M32MAX', 'max');
    expect(fn () => $service->requestPreview((int) $maxAttachment->company_id, (int) $maxAttachment->getKey(), 'aps-test'))
        ->toThrow(DomainException::class, 'does not implement a native .max parser');

    [, , , $failedAttachment] = m32CadFixture('M32FAIL', 'dwg');
    $service->configurePolicy((int) $failedAttachment->company_id, 'aps-test', true);
    $provider->failNext = true;
    $failed = $service->requestPreview((int) $failedAttachment->company_id, (int) $failedAttachment->getKey(), 'aps-test');

    expect($failed->status)->toBe('failed')
        ->and($failed->failure_code)->toBe('provider_failure')
        ->and($failed->failure_message)->toBe('CAD derivative provider request failed.')
        ->and($failed->failure_message)->not->toContain('secret');
});

/** @return array{Company, User, FileAsset, Attachment} */
function m32CadFixture(string $code, string $extension): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $user = User::query()->create([
        'name' => 'CAD User '.$code,
        'email' => strtolower($code).'@cad.example.test',
        'password' => 'password',
        'status' => UserStatus::Active,
    ]);
    $asset = FileAsset::query()->create([
        'company_id' => $company->getKey(),
        'uploaded_by_user_id' => $user->getKey(),
        'storage_disk' => 'local',
        'storage_key' => 'cad/'.$code.'.'.$extension,
        'original_name' => $code.'.'.$extension,
        'mime_type' => 'application/octet-stream',
        'client_extension' => $extension,
        'size_bytes' => 4096,
        'sha256' => hash('sha256', $code.'-'.$extension),
    ]);
    $attachment = Attachment::query()->create([
        'company_id' => $company->getKey(),
        'file_asset_id' => $asset->getKey(),
        'attachable_type' => AttachmentTargetType::Company,
        'attachable_id' => $company->getKey(),
        'label' => 'CAD Source',
        'attached_by_user_id' => $user->getKey(),
        'attached_at' => now(),
    ]);

    return [$company, $user, $asset, $attachment];
}

final class M32CloudFixtureProvider implements CadDerivativeProvider
{
    public int $starts = 0;
    public int $refreshes = 0;
    public bool $failNext = false;

    public function provider(): string
    {
        return 'aps-test';
    }

    public function version(): string
    {
        return '1';
    }

    public function isCloud(): bool
    {
        return true;
    }

    public function supportedExtensions(): array
    {
        return ['dwg', 'dxf', 'obj'];
    }

    public function start(Attachment $attachment, FileAsset $asset): CadDerivativeResult
    {
        $this->starts++;
        if ($this->failNext) {
            $this->failNext = false;
            throw new RuntimeException('secret=must-not-persist');
        }

        return new CadDerivativeResult(
            'processing',
            'urn-fixture',
            null,
            ['urn' => 'urn-fixture'],
            null,
            null,
        );
    }

    public function refresh(CadDerivativeJob $job): CadDerivativeResult
    {
        $this->refreshes++;

        return new CadDerivativeResult(
            'ready',
            'urn-fixture',
            'cad_2d',
            ['urn' => 'urn-fixture', 'renderer' => 'fixture'],
            hash('sha256', (string) $job->source_sha256.'|fixture'),
            new DateTimeImmutable('+1 day'),
        );
    }

    public function viewerToken(CadDerivativeJob $job): ?array
    {
        return ['access_token' => 'viewer-fixture', 'expires_in' => 300];
    }
}
