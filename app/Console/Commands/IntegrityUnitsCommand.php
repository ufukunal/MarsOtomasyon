<?php

namespace App\Console\Commands;

use App\Support\Integrity\Checks\UnitIntegrityCheck;
use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\RunIntegrityCheckCommand;

class IntegrityUnitsCommand extends RunIntegrityCheckCommand
{
    protected $signature = 'integrity:units';

    protected $description = 'Birim dönüşüm ve temel miktar snapshot bütünlüğünü doğrular';

    /** @return class-string<IntegrityCheck> */
    protected function checkClass(): string
    {
        return UnitIntegrityCheck::class;
    }
}
