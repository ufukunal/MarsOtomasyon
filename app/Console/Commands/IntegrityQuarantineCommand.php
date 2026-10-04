<?php

namespace App\Console\Commands;

use App\Support\Integrity\Checks\QuarantineBalanceCheck;
use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\RunIntegrityCheckCommand;

class IntegrityQuarantineCommand extends RunIntegrityCheckCommand
{
    protected $signature = 'integrity:quarantine';

    protected $description = 'Karantina kayıtları ile karantina stok özetini doğrular';

    /** @return class-string<IntegrityCheck> */
    protected function checkClass(): string
    {
        return QuarantineBalanceCheck::class;
    }
}
