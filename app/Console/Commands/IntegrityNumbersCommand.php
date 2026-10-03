<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsIntegrityCheck;
use App\Support\Integrity\Checks\NumberSeriesCheck;
use Illuminate\Console\Command;

class IntegrityNumbersCommand extends Command
{
    use RunsIntegrityCheck;

    protected $signature = 'integrity:numbers';

    protected $description = 'Belge numarası ve sayaç bütünlüğünü kontrol eder';

    public function handle(NumberSeriesCheck $check): int
    {
        return $this->runCheck($check);
    }
}
