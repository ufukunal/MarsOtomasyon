<?php

namespace App\Console\Commands;

use App\Models\Period;
use App\Support\Integrity\Checks\ChannelIntegrityCheck;
use App\Support\Integrity\Checks\ChannelOrderIntegrityCheck;
use App\Support\Integrity\Checks\ContactBalanceCheck;
use App\Support\Integrity\Checks\CostIntegrityCheck;
use App\Support\Integrity\Checks\DocumentTemplateIntegrityCheck;
use App\Support\Integrity\Checks\DocumentTotalCheck;
use App\Support\Integrity\Checks\FilesIntegrityCheck;
use App\Support\Integrity\Checks\FinanceIntegrityCheck;
use App\Support\Integrity\Checks\ImportIntegrityCheck;
use App\Support\Integrity\Checks\NumberSeriesCheck;
use App\Support\Integrity\Checks\PartialDocumentCheck;
use App\Support\Integrity\Checks\ProductionIntegrityCheck;
use App\Support\Integrity\Checks\PrintProvenanceIntegrityCheck;
use App\Support\Integrity\Checks\PurchaseMatchCheck;
use App\Support\Integrity\Checks\RecipeIntegrityCheck;
use App\Support\Integrity\Checks\QuarantineBalanceCheck;
use App\Support\Integrity\Checks\ReportPresetIntegrityCheck;
use App\Support\Integrity\Checks\ReservationBalanceCheck;
use App\Support\Integrity\Checks\ReturnIntegrityCheck;
use App\Support\Integrity\Checks\StockBalanceCheck;
use App\Support\Integrity\Checks\UnitIntegrityCheck;
use App\Support\Integrity\IntegrityRunner;
use App\Support\Period\PeriodContext;
use Illuminate\Console\Command;
use Throwable;

class IntegrityCommand extends Command
{
    protected $signature = 'integrity:all {--include-closed : Closed periodleri read-only olarak doğrula}';

    protected $description = 'Master ve active/opsiyonel closed period DB bütünlük kontrollerini çalıştırır';

    public function handle(IntegrityRunner $runner): int
    {
        $oldCompanyId = PeriodContext::companyId();
        $oldPeriodId = PeriodContext::periodId();

        $failed = [];
        $mismatchCount = 0;

        $checks = [
            StockBalanceCheck::class,
            CostIntegrityCheck::class,
            ReservationBalanceCheck::class,
            QuarantineBalanceCheck::class,
            UnitIntegrityCheck::class,
            DocumentTotalCheck::class,
            PartialDocumentCheck::class,
            PurchaseMatchCheck::class,
            ContactBalanceCheck::class,
            FinanceIntegrityCheck::class,
            ReturnIntegrityCheck::class,
            ImportIntegrityCheck::class,
            RecipeIntegrityCheck::class,
            ProductionIntegrityCheck::class,
            ChannelIntegrityCheck::class,
            ChannelOrderIntegrityCheck::class,
            PrintProvenanceIntegrityCheck::class,
            NumberSeriesCheck::class,
            FilesIntegrityCheck::class,
        ];

        try {
            foreach ([
                'report_presets' => ReportPresetIntegrityCheck::class,
                'templates' => DocumentTemplateIntegrityCheck::class,
            ] as $masterCheckName => $masterCheckClass) {
                try {
                    $masterResult = $runner->run(app($masterCheckClass), false);
                    $mismatchCount += $masterResult->mismatchCount();

                    $this->line(sprintf(
                        '→ master %s checked=%d mismatch=%d',
                        $masterCheckName,
                        $masterResult->checked,
                        $masterResult->mismatchCount(),
                    ));
                } catch (Throwable $exception) {
                    $failed[] = [
                        'database' => 'master',
                        'check' => $masterCheckName,
                        'error' => $exception->getMessage(),
                    ];

                    $this->error("→ master {$masterCheckName}: {$exception->getMessage()}");
                }
            }

            $periodStatuses = $this->option('include-closed')
                ? ['active', 'closed']
                : ['active'];

            Period::query()
                ->whereIn('status', $periodStatuses)
                ->orderBy('company_id')
                ->orderBy('year')
                ->each(function (Period $period) use ($runner, $checks, &$failed, &$mismatchCount): void {
                    $this->info("→ {$period->database_name}");

                    try {
                        PeriodContext::useSystem($period->company_id, $period->id);

                        foreach ($checks as $checkClass) {
                            $check = app($checkClass);
                            $result = $runner->run(
                                $check,
                                (string) $period->status === 'active',
                            );
                            $mismatchCount += $result->mismatchCount();

                            $this->line(sprintf(
                                '  %s checked=%d mismatch=%d',
                                $check->name(),
                                $result->checked,
                                $result->mismatchCount(),
                            ));
                        }
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
                'Bütünlük kontrolü tamamlandı: %d hata, %d mismatch.',
                count($failed),
                $mismatchCount,
            ));

            return self::FAILURE;
        }

        $this->info('Bütünlük kontrolü tamamlandı; fark bulunmadı.');

        return self::SUCCESS;
    }
}
