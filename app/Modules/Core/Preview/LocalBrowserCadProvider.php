<?php

namespace App\Modules\Core\Preview;

use App\Modules\Core\Models\Attachment;
use App\Modules\Core\Models\CadDerivativeJob;
use App\Modules\Core\Models\FileAsset;
use DateTimeImmutable;

final class LocalBrowserCadProvider implements CadDerivativeProvider
{
    public function provider(): string
    {
        return 'local';
    }

    public function version(): string
    {
        return 'mars-browser-v1';
    }

    public function isCloud(): bool
    {
        return false;
    }

    public function supportedExtensions(): array
    {
        return ['dxf', 'obj'];
    }

    public function start(Attachment $attachment, FileAsset $asset): CadDerivativeResult
    {
        $extension = strtolower(ltrim((string) $asset->client_extension, '.'));
        $previewKind = $extension === 'obj' ? 'model_3d' : 'cad_2d';
        $sourceHash = (string) $asset->sha256;

        return new CadDerivativeResult(
            status: 'ready',
            providerJobId: null,
            previewKind: $previewKind,
            manifest: [
                'renderer' => 'mars-local-readonly',
                'format' => $extension,
                'capabilities' => $extension === 'obj'
                    ? ['orbit', 'pan', 'zoom', 'fit']
                    : ['pan', 'zoom', 'fit', 'layers'],
            ],
            derivativeSha256: hash('sha256', $sourceHash.'|'.$this->provider().'|'.$this->version()),
            expiresAt: null,
        );
    }

    public function refresh(CadDerivativeJob $job): CadDerivativeResult
    {
        return new CadDerivativeResult(
            status: 'ready',
            providerJobId: $job->provider_job_id === null ? null : (string) $job->provider_job_id,
            previewKind: $job->preview_kind === null ? null : (string) $job->preview_kind,
            manifest: is_array($job->manifest) ? $job->manifest : [],
            derivativeSha256: $job->derivative_sha256 === null ? null : (string) $job->derivative_sha256,
            expiresAt: $job->expires_at === null
                ? null
                : new DateTimeImmutable($job->expires_at->toIso8601String()),
        );
    }

    public function viewerToken(CadDerivativeJob $job): ?array
    {
        return null;
    }
}
