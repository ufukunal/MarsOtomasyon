<?php

use App\Modules\Core\Enums\AttachmentTargetType;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Attachment;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\FileAsset;
use App\Modules\Core\Models\User;
use App\Modules\Core\Preview\CadDerivativeProvider;
use App\Modules\Core\Preview\CadDerivativeProviderRegistry;
use App\Modules\Core\Preview\CadPreviewService;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('enforces cloud policy and deterministic checksum lineage without mutating the original attachment', function (): void {
    [$company, $user, $asset, $attachment] = m32CadFixture('M32', 'dwg');
    $provider = new M32FixtureCadProvider;
    $registry = new CadDerivativeProviderRegistry;
    $registry->register($provider);
    $service = new CadPreviewService($registry);

    expect(fn () => $service->requestPreview((int) $company->getKey(), (int) $attachment->getKey(), 'fixture-cloud'))
        ->toThrow(DomainException::class, 'Cloud CAD upload is disabled');

    $service->configurePolicy((int) $company->getKey(), 'fixture-cloud', true, 10_000_000, 120, 7);
    $first = $service->requestPreview((int) $company->getKey(), (int) $attachment->getKey(), 'fixture-cloud');
    $replay = $service->requestPreview((int) $company->getKey(), (int) $attachment->getKey(), 'fixture-cloud');

    expect($first['status'])->toBe('ready')
        ->and($first['preview_kind'])->toBe('cad_2d')
        ->and($first['source_sha256'])->toBe((string) $asset->sha256)
        ->and($replay['id'])->toBe($first['id'])
        ->and($provider->calls)->toBe(1)
        ->and(FileAsset::query()->findOrFail($asset->getKey())->sha256)->toBe($asset->sha256)
        ->and(Attachment::query()->findOrFail($attachment->getKey())->file_asset_id)->toBe($asset->getKey());

    $service->invalidateDerivative((int) $company->getKey(), $first['id']);
    $rebuilt = $service->requestPreview((int) $company->getKey(), (int) $attachment->getKey(), 'fixture-cloud');
    expect($rebuilt['id'])->toBe($first['id'])
        ->and($provider->calls)->toBe(2);

    expect(fn () => $service->requestPreview((int) $company->getKey() + 999, (int) $attachment->getKey(), 'fixture-cloud'))
        ->toThrow(DomainException::class, 'not found for company');

    [, , , $maxAttachment] = m32CadFixture('M32MAX', 'max');
    expect(fn () => $service->requestPreview((int) $maxAttachment->company_id, (int) $maxAttachment->getKey(), 'fixture-cloud'))
        ->toThrow(DomainException::class, 'native parsing is not supported');
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

final class M32FixtureCadProvider implements CadDerivativeProvider
{
    public int $calls = 0;

    public function provider(): string
    {
        return 'fixture-cloud';
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
        return ['dwg', 'dxf'];
    }

    public function translate(Attachment $attachment, FileAsset $asset): array
    {
        $this->calls++;
        $derivativeHash = hash('sha256', (string) $asset->sha256.'|fixture|1');

        return [
            'provider_job_id' => 'JOB-'.$this->calls,
            'preview_kind' => 'cad_2d',
            'manifest' => [
                'viewer' => 'fixture-readonly',
                'source_sha256' => (string) $asset->sha256,
                'capabilities' => ['pan', 'zoom', 'layers'],
            ],
            'derivative_sha256' => $derivativeHash,
            'expires_at' => null,
        ];
    }
}
