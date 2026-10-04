<?php

namespace App\Console\Commands;

use App\Support\Integrity\Checks\CostIntegrityCheck;
use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\RunIntegrityCheckCommand;

class IntegrityCostsCommand extends RunIntegrityCheckCommand
{
    protected $signature = 'integrity:costs';

    protected $description = 'Ürün maliyet özetlerini son stok hareketi maliyetleriyle doğrular';

    /** @return class-string<IntegrityCheck> */
    protected function checkClass(): string
    {
        return CostIntegrityCheck::class;
    }
}
