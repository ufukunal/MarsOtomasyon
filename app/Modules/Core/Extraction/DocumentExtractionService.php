<?php

namespace App\Modules\Core\Extraction;

use App\Modules\Core\Models\Attachment;
use App\Modules\Core\Models\FileAsset;
use DomainException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

final class DocumentExtractionService
{
    private readonly DocumentExtractionRegistry $providers;

    public function __construct(DocumentExtractionRegistry $providers)
    {
        $this->providers = $providers;
    }

    /** @return array{id:int,status:string,document_type:?string,requires_review:bool} */
    public function extract(int $companyId, int $attachmentId, string $provider, float $confidenceThreshold = 0.85): array
    {
        if ($confidenceThreshold < 0 || $confidenceThreshold > 1) {
            throw new DomainException('Extraction confidence threshold must be between zero and one.');
        }
        $attachment = Attachment::query()->where('company_id', $companyId)->whereNull('detached_at')->find($attachmentId);
        if (! $attachment instanceof Attachment) {
            throw new DomainException('Attachment not found for company.');
        }
        $asset = FileAsset::query()->where('company_id', $companyId)->find($attachment->file_asset_id);
        if (! $asset instanceof FileAsset || $asset->archived_at !== null || $asset->quarantined_at !== null) {
            throw new DomainException('Attachment source is unavailable for extraction.');
        }
        $implementation = $this->providers->get($provider);
        $providerKey = strtolower(trim($implementation->provider()));
        $sourceHash = (string) $asset->sha256;
        if (strlen($sourceHash) !== 64) {
            throw new DomainException('Attachment source checksum is required for extraction.');
        }

        $existing = DB::table('document_extraction_jobs')
            ->where('company_id', $companyId)
            ->where('attachment_id', $attachmentId)
            ->where('source_sha256', $sourceHash)
            ->where('provider', $providerKey)
            ->where('model', $implementation->model())
            ->where('provider_version', $implementation->version())
            ->first();
        if ($existing !== null && in_array((string) $existing->status, ['awaiting_review', 'reviewed'], true)) {
            return $this->jobSummary($existing);
        }

        $jobId = $existing === null
            ? (int) DB::table('document_extraction_jobs')->insertGetId([
                'company_id' => $companyId,
                'attachment_id' => $attachmentId,
                'source_sha256' => $sourceHash,
                'provider' => $providerKey,
                'model' => $implementation->model(),
                'provider_version' => $implementation->version(),
                'status' => 'processing',
                'confidence_threshold' => $confidenceThreshold,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            : (int) $existing->id;

        DB::table('document_extraction_jobs')->where('id', $jobId)->update([
            'status' => 'processing',
            'confidence_threshold' => $confidenceThreshold,
            'last_error' => null,
            'updated_at' => now(),
        ]);

        try {
            $result = $implementation->extract($attachment);
            $requiresReview = false;
            DB::transaction(function () use ($companyId, $jobId, $result, $confidenceThreshold, &$requiresReview): void {
                DB::table('document_extracted_fields')->where('extraction_job_id', $jobId)->delete();
                foreach ($result['fields'] as $field) {
                    $key = trim($field['key']);
                    if ($key === '') {
                        throw new DomainException('Extracted field key is required.');
                    }
                    $confidence = max(0, min(1, $field['confidence']));
                    $fieldRequiresReview = $confidence < $confidenceThreshold;
                    $requiresReview = $requiresReview || $fieldRequiresReview;
                    DB::table('document_extracted_fields')->insert([
                        'company_id' => $companyId,
                        'extraction_job_id' => $jobId,
                        'field_key' => $key,
                        'extracted_value' => json_encode($field['value'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'confidence' => $confidence,
                        'requires_review' => $fieldRequiresReview,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                DB::table('document_extraction_jobs')->where('id', $jobId)->update([
                    'document_type' => mb_substr(trim($result['document_type']), 0, 64),
                    'status' => 'awaiting_review',
                    'updated_at' => now(),
                ]);
            });
        } catch (\Throwable $exception) {
            DB::table('document_extraction_jobs')->where('id', $jobId)->update([
                'status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 4000),
                'updated_at' => now(),
            ]);
            throw $exception;
        }

        $job = DB::table('document_extraction_jobs')->where('id', $jobId)->first();
        if ($job === null) {
            throw new RuntimeException('Extraction job disappeared after processing.');
        }

        return ['id' => $jobId, 'status' => (string) $job->status, 'document_type' => $job->document_type === null ? null : (string) $job->document_type, 'requires_review' => $requiresReview];
    }

    /**
     * @param  array<string, mixed>  $corrections
     * @return array{document_type:string,fields:array<string,mixed>,source_attachment_id:int,extraction_job_id:int}
     */
    public function review(int $companyId, int $jobId, int $reviewedByUserId, array $corrections = []): array
    {
        return DB::transaction(function () use ($companyId, $jobId, $reviewedByUserId, $corrections): array {
            $job = DB::table('document_extraction_jobs')->where('company_id', $companyId)->where('id', $jobId)->lockForUpdate()->first();
            if ($job === null) {
                throw new DomainException('Extraction job not found for company.');
            }
            if ((string) $job->status === 'reviewed') {
                $payload = json_decode((string) $job->reviewed_payload, true, flags: JSON_THROW_ON_ERROR);
                if (! is_array($payload)) {
                    throw new RuntimeException('Reviewed extraction payload is invalid.');
                }

                /** @var array{document_type:string,fields:array<string,mixed>,source_attachment_id:int,extraction_job_id:int} $payload */
                return $payload;
            }
            if ((string) $job->status !== 'awaiting_review') {
                throw new DomainException('Extraction job is not ready for review.');
            }

            $fields = [];
            foreach (DB::table('document_extracted_fields')->where('extraction_job_id', $jobId)->orderBy('id')->get() as $field) {
                $fields[(string) $field->field_key] = json_decode((string) $field->extracted_value, true, flags: JSON_THROW_ON_ERROR);
            }
            foreach ($corrections as $key => $value) {
                if (! array_key_exists($key, $fields)) {
                    throw new DomainException('Correction references an unknown extracted field: '.$key);
                }
                $fields[$key] = $value;
            }

            $payload = [
                'document_type' => (string) $job->document_type,
                'fields' => $fields,
                'source_attachment_id' => (int) $job->attachment_id,
                'extraction_job_id' => (int) $job->id,
            ];
            DB::table('document_extraction_jobs')->where('id', $jobId)->update([
                'status' => 'reviewed',
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $reviewedByUserId,
                'reviewed_payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);

            return $payload;
        });
    }

    /** @return array{id:int,status:string,document_type:?string,requires_review:bool} */
    private function jobSummary(stdClass $job): array
    {
        $requiresReview = DB::table('document_extracted_fields')
            ->where('extraction_job_id', $job->id)
            ->where('requires_review', true)
            ->exists();

        return [
            'id' => (int) $job->id,
            'status' => (string) $job->status,
            'document_type' => $job->document_type === null ? null : (string) $job->document_type,
            'requires_review' => $requiresReview,
        ];
    }
}
