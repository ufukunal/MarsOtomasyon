<?php

namespace App\Support\Integrity;

use App\Models\Period;
use App\Support\Period\PeriodContext;
use Illuminate\Console\Command;
use Throwable;

abstract class RunIntegrityCheckCommand extends Command
{
    /** @return class-string<IntegrityCheck> */
    abstract protected function checkClass(): string;

    public function handle(IntegrityRunner $runner): int
    {
        $oldCompanyId = PeriodContext::companyId();
        $oldPeriodId = PeriodContext::periodId();
        $failed = [];
        $mismatchCount = 0;

        try {
            Period::query()
                ->where('status', 'active')
                ->orderBy('company_id')
                ->orderBy('year')
                ->each(function (Period $period) use ($runner, &$failed, &$mismatchCount): void {
                    $this->info("→ {$period->database_name}");

                    try {
                        PeriodContext::useSystem($period->company_id, $period->id);

                        /** @var IntegrityCheck $check */
                        $check = app($this->checkClass());
                        $result = $runner->run($check);
                        $mismatchCount += $result->mismatchCount();

                        $this->line(sprintf(
                            '  %s checked=%d mismatch=%d',
                            $check->name(),
                            $result->checked,
                            $result->mismatchCount(),
                        ));
                    } catch (Throwable $exception) {
                        $failed[] = [
                            'database' => $period->database_name,
                            'error' => $exception->getMessage(),
                        ];

                        $this->error("  {$exception->getMessage()}");
                    }
                });
        } finally {
            PeriodContext::clear();

            if ($oldCompanyId && $oldPeriodId) {
                PeriodContext::useSystem($oldCompanyId, $oldPeriodId);
            }
        }

        if ($failed !== [] || $mismatchCount > 0) {
            $this->error(sprintf(
                'Bütünlük kontrolü başarısız: %d hata, %d mismatch.',
                count($failed),
                $mismatchCount,
            ));

            return self::FAILURE;
        }

        $this->info('Bütünlük kontrolü tamamlandı; fark bulunmadı.');

        return self::SUCCESS;
    }
}
