<?php

namespace App\Support\Printing;

use App\Enums\PrintType;
use App\Models\DocumentPrintSnapshot;
use App\Models\DocumentTemplate;
use App\Models\PrintJob;
use App\Models\PrintProfile;
use App\Support\Period\PeriodContext;
use Illuminate\Support\Arr;
use Throwable;

final class PrintJobTracker
{
    /** @param array<string, mixed> $context */
    public function start(
        PrintType $type,
        ?DocumentTemplate $template,
        ?PrintProfile $profile,
        array $context = [],
        ?string $contentHash = null,
    ): PrintJob {
        PeriodContext::ensure();

        $quantity = max(1, (int) ($context['quantity'] ?? 1));
        $sourceType = $this->nullableString($context['source_type'] ?? null);
        $sourceId = isset($context['source_id']) ? (int) $context['source_id'] : null;
        $machineKey = $this->nullableString($context['machine_key'] ?? $this->machineKey());

        $parameters = [
            'company_id' => PeriodContext::companyId(),
            'user_id' => auth()->id(),
            'machine_key' => $machineKey,
            'print_type' => $type->value,
            'template_id' => $template?->id,
            'template_revision_no' => $template?->revision_no,
            'profile_id' => $profile?->id,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'quantity' => $quantity,
            'content_hash' => $contentHash,
            'revision_selection' => $this->nullableString($context['revision_selection'] ?? null),
        ];

        return PrintJob::query()->create([
            'company_id' => PeriodContext::companyId(),
            'user_id' => auth()->id(),
            'machine_key' => $machineKey,
            'print_type' => $type->value,
            'template_id' => $template?->id,
            'template_revision_no' => $template?->revision_no,
            'profile_id' => $profile?->id,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'quantity' => $quantity,
            'status' => 'processing',
            'result_metadata' => array_filter([
                'template_key' => $template?->template_key,
                'revision_selection' => $this->nullableString($context['revision_selection'] ?? null),
            ], static fn (mixed $value): bool => $value !== null),
            'parameters_hash' => hash('sha256', json_encode($parameters, JSON_THROW_ON_ERROR)),
        ]);
    }

    /** @param array<string, mixed> $context */
    public function complete(
        PrintJob $job,
        PrintResult $result,
        ?DocumentTemplate $template,
        array $context = [],
    ): void {
        $outputHash = hash('sha256', $result->content);

        if ($template && $job->source_type === 'document' && $job->source_id) {
            DocumentPrintSnapshot::query()->updateOrCreate(
                ['document_id' => $job->source_id],
                [
                    'template_key' => $template->template_key,
                    'template_revision_no' => $template->revision_no,
                    'rendered_at' => now(),
                    'rendered_by' => auth()->id(),
                    'output_hash' => $outputHash,
                ],
            );
        }

        $job->forceFill([
            'status' => 'done',
            'result_metadata' => array_filter(array_merge(
                $job->result_metadata ?? [],
                [
                    'mime_type' => $result->mimeType,
                    'filename' => basename($result->filename),
                    'output_hash' => $outputHash,
                    'output_size' => strlen($result->content),
                    'revision_selection' => $this->nullableString(
                        $context['revision_selection'] ?? Arr::get($job->result_metadata, 'revision_selection')
                    ),
                ],
            ), static fn (mixed $value): bool => $value !== null),
        ])->save();
    }

    public function fail(PrintJob $job, Throwable $exception): void
    {
        $job->forceFill([
            'status' => 'failed',
            'result_metadata' => array_merge(
                $job->result_metadata ?? [],
                [
                    'error_class' => class_basename($exception),
                    'error_summary' => 'Print operation failed.',
                ],
            ),
        ])->save();
    }

    private function machineKey(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        return $this->nullableString(request()->cookie('machine_key'));
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
