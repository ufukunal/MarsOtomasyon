<?php

namespace App\Console\Commands;

use App\Models\ReportExportJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PruneReportExportsCommand extends Command
{
    protected $signature = 'reports:prune-exports {--days=}';

    protected $description = 'Süresi dolan rapor export dosyalarını private storage alanından temizler';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('reporting.export_retention_days', 30));

        if ($days < 1) {
            $this->error('Retention günü en az 1 olmalıdır.');

            return self::FAILURE;
        }

        $failed = false;
        $cutoff = now()->subDays($days);

        ReportExportJob::query()
            ->where('status', 'done')
            ->whereNotNull('storage_disk')
            ->whereNotNull('storage_path')
            ->where('finished_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($jobs) use (&$failed): void {
                foreach ($jobs as $job) {
                    try {
                        $disk = Storage::disk($job->storage_disk);

                        if ($disk->exists($job->storage_path)) {
                            $disk->delete($job->storage_path);
                        }

                        $job->update([
                            'storage_disk' => null,
                            'storage_path' => null,
                        ]);
                    } catch (Throwable $exception) {
                        $failed = true;
                        $this->error("Export #{$job->id}: {$exception->getMessage()}");
                    }
                }
            });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
