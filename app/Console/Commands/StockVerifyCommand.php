<?php

namespace App\Console\Commands;

use App\Models\Period;
use App\Support\Integrity\Checks\StockBalanceCheck;
use App\Support\Integrity\IntegrityRunner;
use App\Support\Period\PeriodContext;
use Illuminate\Console\Command;
use Throwable;

class StockVerifyCommand extends Command
{
    protected $signature = 'stock:verify';

    protected $description = 'Aktif period DB stok hareket toplamları ile bakiyeleri karşılaştırır';

    public function handle(IntegrityRunner $runner): int
    {
        $oldCompanyId = PeriodContext::companyId();
        $oldPeriodId = PeriodContext::periodId();
        $failed = 0;
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
                        $result = $runner->run(app(StockBalanceCheck::class));
                        $mismatchCount += $result->mismatchCount();

                        $this->line(sprintf(
                            '  stock checked=%d mismatch=%d',
                            $result->checked,
                            $result->mismatchCount(),
                        ));
                    } catch (Throwable $exception) {
                        $failed++;
                        $this->error("  {$exception->getMessage()}");
                    }
                });
        } finally {
            PeriodContext::clear();

            if ($oldCompanyId && $oldPeriodId) {
                PeriodContext::useSystem($oldCompanyId, $oldPeriodId);
            }
        }

        if ($failed > 0 || $mismatchCount > 0) {
            $this->error(sprintf(
                'Stok doğrulaması başarısız: %d hata, %d mismatch.',
                $failed,
                $mismatchCount,
            ));

            return self::FAILURE;
        }

        $this->info('Stok doğrulaması tamamlandı; fark bulunmadı.');

        return self::SUCCESS;
    }
}
