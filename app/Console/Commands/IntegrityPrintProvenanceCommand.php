<?php

namespace App\Console\Commands;

use App\Support\Integrity\Checks\PrintProvenanceIntegrityCheck;
use App\Support\Integrity\RunIntegrityCheckCommand;

class IntegrityPrintProvenanceCommand extends RunIntegrityCheckCommand
{
    protected $signature = 'integrity:print-provenance';

    protected $description = 'Aktif period DBlerde belge çıktı template revision ve print job provenance bütünlüğünü kontrol eder';

    protected function checkClass(): string
    {
        return PrintProvenanceIntegrityCheck::class;
    }
}
