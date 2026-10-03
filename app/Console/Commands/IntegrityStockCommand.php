<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsIntegrityCheck;
use App\Support\Integrity\Checks\StockBalanceCheck;
use Illuminate\Console\Command;

class IntegrityStockCommand extends Command
{
    use RunsIntegrityCheck;

    protected $signature = 'integrity:stock';

    protected $description = 'Stok hareketleri ile stok bakiyelerini karşılaştırır';

    public function handle(StockBalanceCheck $check): int
    {
        return $this->runCheck($check);
    }
}
