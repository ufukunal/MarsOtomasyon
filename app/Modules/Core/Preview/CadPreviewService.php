<?php

namespace App\Modules\Core\Preview;

use App\Modules\Core\Models\Attachment;
use App\Modules\Core\Models\CadDerivativeJob;
use App\Modules\Core\Models\CadViewerPolicy;
use App\Modules\Core\Models\FileAsset;
use DomainException;
use Illuminate\Database\QueryException;
use RuntimeException;
use Throwable;

final readonly class CadPreviewService
{
    private const DEFAULT_MAX_BYTES = 52_428_800;

    public function __construct(private CadDerivativeProviderRegistry $providers) {}

    public function configurePolicy(
        int $companyId,
        string $provider,
        bool $cloudUploadEnabled,
        int $maxFileSizeBytes = self::DEFAULT_MAX_BYTES,
        int $timeoutSeconds = 300,
        int $retentionDays = 1,
    ): CadViewerPolicy {
        $implementation = $this->providers->get($provider);

        return CadViewerPolicy::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'provider' => $implementation->provider(),
            ],
            [
                'cloud_upload_enabled' => $cloudUploadEnabled,
                'max_file_size_bytes' => max(1, min(self::DEFAULT_MAX_BYTES, $maxFileSizeBytes)),
                'timeout_seconds' => max(30, min(900, $timeoutSeconds)),
                'retention_days' => max(1, min(7, $retentionDays)),
            ],
        );
    }

    public function policyFor(int $companyId, string $provider): ?CadViewerPolicy
    {
        $providerKey = $this->providers->get($provider)->provider();

        return CadViewerPolicy::query()
            ->where('company_id', $companyId)
            ->where('provider', $providerKey)
            ->first();
    }

    public function requestPreview(int $companyId, int $attachmentId, string $provider): CadDerivativeJob
    {
        [$attachment, $asset] = $this->source($companyId, $attachmentId);
        $implementation = $this->providers->get($provider);
        $providerKey = strtolower(trim($implementation->provider()));
        $extension = strtolower(ltrim((string) $asset->client_extension, '.'));

        if (! in_array($extension, $implementation->supportedExtensions(), true)) {
            $message = $extension === 'max'
                ? '.max preview requires a verified provider or controlled conversion path; Mars does not implement a native .max parser.'
                : 'CAD preview format is not supported by the selected provider.';
            throw new DomainException($message);
        }

        $this->assertPolicy($companyId, $implementation, $asset);
        $sourceHash = $this->sourceHash($asset);

        try {
            $job = CadDerivativeJob::query()->firstOrCreate(
                [
                    'company_id' => $companyId,
                    'attachment_id' => $attachmentId,
                    'source_sha256' => $sourceHash,
                    'provider' => $providerKey,
                    'provider_version' => $implementation->version(),
                ],
                [
                    'file_asset_id' => $asset->getKey(),
                    'source_extension' => $extension,
                    'status' => 'pending',
                ],
            );
        } catch (QueryException) {
            $job = CadDerivativeJob::query()
                ->where('company_id', $companyId)
                ->where('attachment_id', $attachmentId)
                ->where('source_sha256', $sourceHash)
                ->where('provider', $providerKey)
                ->where('provider_version', $implementation->version())
                ->firstOrFail();
        }

        if ($job->isReady() && ($job->expires_at === null || $job->expires_at->isFuture())) {
            return $job;
        }
        if ($job->isProcessing()) {
            return $job;
        }

        return $this->start($job, $attachment, $asset, $implementation);
    }

    public function refreshPreview(int $companyId, int $jobId): CadDerivativeJob
    {
        $job = CadDerivativeJob::query()->where('company_id', $companyId)->findOrFail($jobId);
        if (! $job->isProcessing()) {
            return $job;
        }

        $provider = $this->providers->get((string) $job->provider);

        try {
            return $this->apply($job, $provider->refresh($job));
        } catch (Throwable $exception) {
            return $this->fail($job, $exception);
        }
    }

    public function rebuildPreview(int $companyId, int $jobId): CadDerivativeJob
    {
        $job = CadDerivativeJob::query()->where('company_id', $companyId)->findOrFail($jobId);
        [$attachment, $asset] = $this->source($companyId, (int) $job->attachment_id);
        $provider = $this->providers->get((string) $job->provider);

        $job->update([
            'status' => 'pending',
            'preview_kind' => null,
            'provider_job_id' => null,
            'manifest' => null,
            'derivative_sha256' => null,
            'failure_code' => null,
            'failure_message' => null,
            'generated_at' => null,
            'expires_at' => null,
        ]);

        return $this->start($job, $attachment, $asset, $provider);
    }

    public function latestForAttachment(int $companyId, int $attachmentId, string $provider): ?CadDerivativeJob
    {
        return CadDerivativeJob::query()
            ->where('company_id', $companyId)
            ->where('attachment_id', $attachmentId)
            ->where('provider', strtolower(trim($provider)))
            ->latest('id')
            ->first();
    }

    /** @return array{access_token:string,expires_in:int}|null */
    public function viewerToken(int $companyId, int $jobId): ?array
    {
        $job = CadDerivativeJob::query()->where('company_id', $companyId)->findOrFail($jobId);
        if (! $job->isReady()) {
            throw new DomainException('CAD derivative is not ready.');
        }

        return $this->providers->get((string) $job->provider)->viewerToken($job);
    }

    /** @return array{Attachment, FileAsset} */
    private function source(int $companyId, int $attachmentId): array
    {
        $attachment = Attachment::query()
            ->where('company_id', $companyId)
            ->whereNull('detached_at')
            ->find($attachmentId);
        if (! $attachment instanceof Attachment) {
            throw new DomainException('Attachment not found for company.');
        }

        $asset = FileAsset::query()
            ->where('company_id', $companyId)
            ->whereNull('archived_at')
            ->whereNull('quarantined_at')
            ->find($attachment->file_asset_id);
        if (! $asset instanceof FileAsset) {
            throw new DomainException('CAD preview source is unavailable.');
        }

        return [$attachment, $asset];
    }

    private function assertPolicy(int $companyId, CadDerivativeProvider $provider, FileAsset $asset): void
    {
        $policy = CadViewerPolicy::query()
            ->where('company_id', $companyId)
            ->where('provider', $provider->provider())
            ->first();

        if ($provider->isCloud() && ($policy === null || ! $policy->cloud_upload_enabled)) {
            throw new DomainException('Cloud CAD upload is disabled by company policy.');
        }

        $maxBytes = $policy?->max_file_size_bytes ?? self::DEFAULT_MAX_BYTES;
        if ((int) $asset->size_bytes > (int) $maxBytes) {
            throw new DomainException('CAD preview source exceeds company file-size policy.');
        }
    }

    private function sourceHash(FileAsset $asset): string
    {
        $hash = strtolower((string) $asset->sha256);
        if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
            throw new DomainException('CAD preview source checksum is required.');
        }

        return $hash;
    }

    private function start(
        CadDerivativeJob $job,
        Attachment $attachment,
        FileAsset $asset,
        CadDerivativeProvider $provider,
    ): CadDerivativeJob {
        $job->update([
            'status' => 'processing',
            'failure_code' => null,
            'failure_message' => null,
        ]);

        try {
            return $this->apply($job, $provider->start($attachment, $asset));
        } catch (Throwable $exception) {
            return $this->fail($job, $exception);
        }
    }

    private function apply(CadDerivativeJob $job, CadDerivativeResult $result): CadDerivativeJob
    {
        if (! in_array($result->status, ['processing', 'ready'], true)) {
            throw new RuntimeException('CAD derivative provider returned invalid status.');
        }
        if ($result->status === 'ready' && ! in_array($result->previewKind, ['cad_2d', 'model_3d'], true)) {
            throw new RuntimeException('CAD derivative provider returned invalid preview kind.');
        }

        $job->update([
            'status' => $result->status,
            'preview_kind' => $result->previewKind,
            'provider_job_id' => $result->providerJobId,
            'manifest' => $result->manifest,
            'derivative_sha256' => $result->derivativeSha256,
            'generated_at' => $result->status === 'ready' ? now() : null,
            'expires_at' => $result->expiresAt,
            'failure_code' => null,
            'failure_message' => null,
        ]);

        return $job->refresh();
    }

    private function fail(CadDerivativeJob $job, Throwable $exception): CadDerivativeJob
    {
        $message = $exception instanceof DomainException
            ? $exception->getMessage()
            : 'CAD derivative provider request failed.';
        $message = (string) preg_replace('/(bearer|token|secret|client_secret)\s*[:=]?\s*\S+/iu', '$1 [redacted]', $message);

        $job->update([
            'status' => 'failed',
            'failure_code' => $exception instanceof DomainException ? 'provider_rejected' : 'provider_failure',
            'failure_message' => mb_substr($message, 0, 500),
            'generated_at' => null,
            'expires_at' => null,
        ]);

        return $job->refresh();
    }
}
