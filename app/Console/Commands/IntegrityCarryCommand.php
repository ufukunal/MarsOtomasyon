<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsIntegrityCheck;
use App\Models\Period;
use App\Support\Integrity\Checks\CarryIntegrityCheck;
use App\Support\Period\PeriodContext;
use Illuminate\Console\Command;

final class IntegrityCarryCommand extends Command
{
    use RunsIntegrityCheck;

    protected $signature = 'integrity:carry {--target= : Target period_id}';

    protected $description = 'Dönem devri source kapanış ve target açılış bütünlüğünü kontrol eder';

    public function handle(CarryIntegrityCheck $check): int
    {
        $oldCompanyId = PeriodContext::companyId();
        $oldPeriodId = PeriodContext::periodId();

        try {
            if ($this->option('target')) {
                $period = Period::query()->findOrFail((int) $this->option('target'));
                PeriodContext::useSystem((int) $period->company_id, (int) $period->id);
            } else {
                PeriodContext::ensure();
            }

            return $this->runCheck($check);
        } finally {
            PeriodContext::clear();

            if ($oldCompanyId && $oldPeriodId) {
                PeriodContext::useSystem($oldCompanyId, $oldPeriodId);
            }
        }
    }
}
