<?php

namespace App\Jobs;

use App\Actions\Reporting\ExportReport;
use App\Models\ReportExportJob;
use App\Models\User;
use App\Support\Period\PeriodContext;
use App\Support\Operations\OperationalErrorSanitizer;
use App\Support\Reporting\ReportRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GenerateReportExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string,mixed>  $filters
     * @param  list<string>  $columns
     * @param  list<array{key:string,direction:string}>  $sort
     */
    public function __construct(
        public readonly int $exportJobId,
        public readonly int $companyId,
        public readonly int $periodId,
        public readonly int $userId,
        public readonly string $reportKey,
        public readonly string $format,
        public readonly array $filters,
        public readonly array $columns,
        public readonly array $sort,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(
        ExportReport $exportReport,
        OperationalErrorSanitizer $errors,
    ): void
    {
        $job = ReportExportJob::query()
            ->whereKey($this->exportJobId)
            ->where('company_id', $this->companyId)
            ->where('user_id', $this->userId)
            ->where('report_key', $this->reportKey)
            ->where('format', $this->format)
            ->firstOrFail();

        if ($job->status === 'done') {
            return;
        }

        $actor = User::query()
            ->whereKey($this->userId)
            ->where('is_active', true)
            ->firstOrFail();

        $hasAccess = DB::connection('master')
            ->table('company_user')
            ->where('company_id', $this->companyId)
            ->where('user_id', $this->userId)
            ->exists()
            && DB::connection('master')
                ->table('period_user_access')
                ->where('period_id', $this->periodId)
                ->where('user_id', $this->userId)
                ->where('is_active', true)
                ->exists();

        if (! $hasAccess) {
            throw new RuntimeException('Export sahibi şirket/dönem erişimini kaybetti.');
        }

        $job->update([
            'status' => 'processing',
            'progress' => 10,
            'started_at' => now(),
            'finished_at' => null,
            'error_summary' => null,
        ]);

        PeriodContext::useSystem($this->companyId, $this->periodId);

        try {
            $job->update(['progress' => 35]);

            $artifact = $exportReport->handle(
                $this->reportKey,
                new ReportRequest(
                    filters: $this->filters,
                    columns: $this->columns,
                    sort: $this->sort,
                    limit: 1,
                    offset: 0,
                ),
                $this->format,
                $actor,
            );

            $job->update(['progress' => 80]);

            $disk = (string) config('reporting.export_disk', 'report_exports');
            $safeKey = Str::slug(str_replace('.', '-', $this->reportKey)) ?: 'report';
            $path = implode('/', [
                'company-'.$this->companyId,
                'period-'.$this->periodId,
                'export-'.$this->exportJobId.'-'.$safeKey.'.'.$this->format,
            ]);

            if (! Storage::disk($disk)->put($path, $artifact->contents)) {
                throw new RuntimeException('Export dosyası storage alanına yazılamadı.');
            }

            $job->update([
                'status' => 'done',
                'progress' => 100,
                'storage_disk' => $disk,
                'storage_path' => $path,
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $job->update([
                'status' => 'failed',
                'progress' => null,
                'finished_at' => now(),
                'error_summary' => $errors->summarize($exception),
            ]);

            throw $exception;
        } finally {
            PeriodContext::clear();
        }
    }
}
