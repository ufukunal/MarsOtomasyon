<?php

namespace App\Console\Commands;

use App\Support\Integrity\Checks\StockBalanceCheck;
use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\RunIntegrityCheckCommand;

class IntegrityStockCommand extends RunIntegrityCheckCommand
{
    protected $signature = 'integrity:stock';

    protected $description = 'Stok hareketleri ile stok bakiyelerini doğrular';

    /** @return class-string<IntegrityCheck> */
    protected function checkClass(): string
    {
        return StockBalanceCheck::class;
    }
}
