<?php

namespace App\Modules\Core\Preview;

use App\Modules\Core\Models\Attachment;
use App\Modules\Core\Models\FileAsset;
use DomainException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CadPreviewService
{
    public function __construct(private readonly CadDerivativeProviderRegistry $providers) {}

    public function configurePolicy(int $companyId, string $provider, bool $cloudUploadEnabled, ?int $maxFileSizeBytes = null, ?int $timeoutSeconds = null, ?int $retentionDays = null): void
    {
        $implementation = $this->providers->get($provider);
        DB::table('cad_viewer_policies')->updateOrInsert(
            ['company_id' => $companyId, 'provider' => strtolower(trim($implementation->provider()))],
            [
                'cloud_upload_enabled' => $cloudUploadEnabled,
                'max_file_size_bytes' => $maxFileSizeBytes,
                'timeout_seconds' => $timeoutSeconds,
                'retention_days' => $retentionDays,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    /** @return array{id:int,status:string,preview_kind:?string,manifest:?array<string,mixed>,source_sha256:string,derivative_sha256:?string} */
    public function requestPreview(int $companyId, int $attachmentId, string $provider): array
    {
        $attachment = Attachment::query()->where('company_id', $companyId)->whereNull('detached_at')->find($attachmentId);
        if (! $attachment instanceof Attachment) {
            throw new DomainException('Attachment not found for company.');
        }
        $asset = FileAsset::query()->where('company_id', $companyId)->find($attachment->file_asset_id);
        if (! $asset instanceof FileAsset || $asset->archived_at !== null || $asset->quarantined_at !== null) {
            throw new DomainException('CAD preview source is unavailable.');
        }
        $sourceHash = (string) $asset->sha256;
        if (strlen($sourceHash) !== 64) {
            throw new DomainException('CAD preview source checksum is required.');
        }

        $implementation = $this->providers->get($provider);
        $providerKey = strtolower(trim($implementation->provider()));
        $extension = strtolower(ltrim((string) $asset->client_extension, '.'));
        if (! in_array($extension, $implementation->supportedExtensions(), true)) {
            $message = $extension === 'max'
                ? 'MAX preview requires a provider or controlled conversion capability; native parsing is not supported.'
                : 'CAD preview format is not supported by provider.';
            throw new DomainException($message);
        }

        $policy = DB::table('cad_viewer_policies')->where('company_id', $companyId)->where('provider', $providerKey)->first();
        if ($implementation->isCloud() && ($policy === null || ! (bool) $policy->cloud_upload_enabled)) {
            throw new DomainException('Cloud CAD upload is disabled by company policy.');
        }
        if ($policy !== null && $policy->max_file_size_bytes !== null && (int) $asset->size_bytes > (int) $policy->max_file_size_bytes) {
            throw new DomainException('CAD preview source exceeds company file-size policy.');
        }

        $existing = DB::table('cad_derivative_jobs')
            ->where('company_id', $companyId)
            ->where('attachment_id', $attachmentId)
            ->where('source_sha256', $sourceHash)
            ->where('provider', $providerKey)
            ->where('provider_version', $implementation->version())
            ->first();
        if ($existing !== null && in_array((string) $existing->status, ['processing', 'ready'], true)) {
            return $this->jobArray($existing);
        }

        $jobId = $existing === null
            ? (int) DB::table('cad_derivative_jobs')->insertGetId([
                'company_id' => $companyId,
                'attachment_id' => $attachmentId,
                'file_asset_id' => $asset->getKey(),
                'source_sha256' => $sourceHash,
                'source_extension' => $extension,
                'provider' => $providerKey,
                'provider_version' => $implementation->version(),
                'status' => 'processing',
                'created_at' => now(),
                'updated_at' => now(),
            ])
            : (int) $existing->id;

        DB::table('cad_derivative_jobs')->where('id', $jobId)->update([
            'status' => 'processing',
            'failure_code' => null,
            'failure_message' => null,
            'updated_at' => now(),
        ]);

        try {
            $result = $implementation->translate($attachment, $asset);
            $kind = trim($result['preview_kind']);
            if (! in_array($kind, ['cad_2d', 'model_3d'], true)) {
                throw new DomainException('CAD derivative provider returned invalid preview kind.');
            }
            DB::table('cad_derivative_jobs')->where('id', $jobId)->update([
                'status' => 'ready',
                'preview_kind' => $kind,
                'provider_job_id' => $result['provider_job_id'],
                'manifest' => json_encode($result['manifest'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'derivative_sha256' => $result['derivative_sha256'],
                'generated_at' => now(),
                'expires_at' => $result['expires_at'],
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            DB::table('cad_derivative_jobs')->where('id', $jobId)->update([
                'status' => 'failed',
                'failure_code' => 'translation_failed',
                'failure_message' => mb_substr($exception->getMessage(), 0, 4000),
                'updated_at' => now(),
            ]);
            throw $exception;
        }

        $job = DB::table('cad_derivative_jobs')->where('id', $jobId)->first();
        if ($job === null) {
            throw new RuntimeException('CAD derivative job disappeared after translation.');
        }

        return $this->jobArray($job);
    }

    public function invalidateDerivative(int $companyId, int $jobId): void
    {
        $job = DB::table('cad_derivative_jobs')->where('company_id', $companyId)->where('id', $jobId)->first();
        if ($job === null) {
            throw new DomainException('CAD derivative job not found for company.');
        }
        DB::table('cad_derivative_jobs')->where('id', $jobId)->update([
            'status' => 'pending',
            'preview_kind' => null,
            'provider_job_id' => null,
            'manifest' => null,
            'derivative_sha256' => null,
            'generated_at' => null,
            'expires_at' => null,
            'failure_code' => null,
            'failure_message' => null,
            'updated_at' => now(),
        ]);
    }

    /** @return array{id:int,status:string,preview_kind:?string,manifest:?array<string,mixed>,source_sha256:string,derivative_sha256:?string} */
    private function jobArray(object $job): array
    {
        $manifest = $job->manifest === null ? null : json_decode((string) $job->manifest, true, flags: JSON_THROW_ON_ERROR);
        if ($manifest !== null && ! is_array($manifest)) {
            throw new RuntimeException('CAD derivative manifest is invalid.');
        }
        /** @var array<string, mixed>|null $manifest */

        return [
            'id' => (int) $job->id,
            'status' => (string) $job->status,
            'preview_kind' => $job->preview_kind === null ? null : (string) $job->preview_kind,
            'manifest' => $manifest,
            'source_sha256' => (string) $job->source_sha256,
            'derivative_sha256' => $job->derivative_sha256 === null ? null : (string) $job->derivative_sha256,
        ];
    }
}
