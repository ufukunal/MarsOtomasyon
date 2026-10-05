<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsIntegrityCheck;
use App\Support\Integrity\Checks\ProductionIntegrityCheck;
use Illuminate\Console\Command;

class IntegrityProductionCommand extends Command
{
    use RunsIntegrityCheck;

    protected $signature = 'integrity:production';

    protected $description = 'Üretim/fason completion, stok hareketi, service allocation ve maliyet bütünlüğünü kontrol eder';

    public function handle(ProductionIntegrityCheck $check): int
    {
        return $this->runCheck($check);
    }
}
