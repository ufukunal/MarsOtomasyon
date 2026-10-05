<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsIntegrityCheck;
use App\Support\Integrity\Checks\PrintProvenanceIntegrityCheck;
use Illuminate\Console\Command;

class IntegrityPrintProvenanceCommand extends Command
{
    use RunsIntegrityCheck;

    protected $signature = 'integrity:print-provenance';

    protected $description = 'Belge çıktı template revision ve print job provenance bütünlüğünü kontrol eder';

    public function handle(PrintProvenanceIntegrityCheck $check): int
    {
        return $this->runCheck($check);
    }
}
