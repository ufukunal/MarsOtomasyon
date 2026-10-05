<?php

namespace App\Support\Integrity\Checks;

use App\Models\DocumentPrintSnapshot;
use App\Models\DocumentTemplate;
use App\Models\PrintJob;
use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
use App\Support\Period\PeriodContext;
use Illuminate\Support\Facades\Schema;

final class PrintProvenanceIntegrityCheck implements IntegrityCheck
{
    public function name(): string
    {
        return 'print-provenance';
    }

    public function run(): IntegrityResult
    {
        $started = hrtime(true);

        if (
            ! Schema::connection('period')->hasTable('document_print_snapshots')
            || ! Schema::connection('master')->hasTable('print_jobs')
            || ! Schema::connection('master')->hasTable('document_templates')
        ) {
            return new IntegrityResult(
                checked: 0,
                mismatches: [],
                durationMs: $this->elapsed($started),
                meta: ['status' => 'not_applicable'],
            );
        }

        PeriodContext::ensure();
        $companyId = PeriodContext::companyId();
        $snapshots = DocumentPrintSnapshot::query()->orderBy('document_id')->get();
        $mismatches = [];

        foreach ($snapshots as $snapshot) {
            $template = DocumentTemplate::query()
                ->where('company_id', $companyId)
                ->where('template_key', $snapshot->template_key)
                ->where('revision_no', $snapshot->template_revision_no)
                ->first();

            if (! $template) {
                $mismatches[] = [
                    'document_id' => $snapshot->document_id,
                    'reason' => 'template_revision_missing',
                    'template_key' => $snapshot->template_key,
                    'template_revision_no' => $snapshot->template_revision_no,
                ];

                continue;
            }

            if (! $snapshot->output_hash) {
                continue;
            }

            $job = PrintJob::query()
                ->where('company_id', $companyId)
                ->where('source_type', 'document')
                ->where('source_id', $snapshot->document_id)
                ->where('status', 'done')
                ->where('result_metadata->output_hash', $snapshot->output_hash)
                ->latest('id')
                ->first();

            if (! $job) {
                $mismatches[] = [
                    'document_id' => $snapshot->document_id,
                    'reason' => 'print_job_provenance_missing',
                    'output_hash' => $snapshot->output_hash,
                ];

                continue;
            }

            if (
                (int) $job->template_id !== (int) $template->id
                || (int) $job->template_revision_no !== (int) $snapshot->template_revision_no
                || ($job->result_metadata['template_key'] ?? null) !== $snapshot->template_key
            ) {
                $mismatches[] = [
                    'document_id' => $snapshot->document_id,
                    'reason' => 'print_job_revision_mismatch',
                    'snapshot_template_key' => $snapshot->template_key,
                    'snapshot_revision_no' => $snapshot->template_revision_no,
                    'job_id' => $job->id,
                    'job_template_id' => $job->template_id,
                    'job_template_key' => $job->result_metadata['template_key'] ?? null,
                    'job_revision_no' => $job->template_revision_no,
                ];
            }
        }

        return new IntegrityResult(
            checked: $snapshots->count(),
            mismatches: $mismatches,
            durationMs: $this->elapsed($started),
        );
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
