<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsIntegrityCheck;
use App\Support\Integrity\Checks\ReportPresetIntegrityCheck;
use Illuminate\Console\Command;

class IntegrityReportPresetsCommand extends Command
{
    use RunsIntegrityCheck;

    protected $signature = 'integrity:report-presets';

    protected $description = 'Rapor preset report/filter/column/sort ve company/user kapsam bütünlüğünü kontrol eder';

    public function handle(ReportPresetIntegrityCheck $check): int
    {
        return $this->runCheck($check, false);
    }
}
