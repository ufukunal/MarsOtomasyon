<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsIntegrityCheck;
use App\Support\Integrity\Checks\FinanceIntegrityCheck;
use Illuminate\Console\Command;

class IntegrityFinanceCommand extends Command
{
    use RunsIntegrityCheck;

    protected $signature = 'integrity:finance';

    protected $description = 'Kasa, banka, virman, ekstre ve çek/senet bütünlüğünü kontrol eder';

    public function handle(FinanceIntegrityCheck $check): int
    {
        return $this->runCheck($check);
    }
}
