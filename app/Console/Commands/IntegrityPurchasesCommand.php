<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsIntegrityCheck;
use App\Support\Integrity\Checks\PurchaseMatchCheck;
use Illuminate\Console\Command;

class IntegrityPurchasesCommand extends Command
{
    use RunsIntegrityCheck;

    protected $signature = 'integrity:purchases';

    protected $description = 'Satınalma, mal kabul, alış faturası ve üçlü eşleştirme bütünlüğünü kontrol eder';

    public function handle(PurchaseMatchCheck $check): int
    {
        return $this->runCheck($check);
    }
}
