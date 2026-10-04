<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsIntegrityCheck;
use App\Support\Integrity\Checks\ImportIntegrityCheck;
use Illuminate\Console\Command;

class IntegrityImportsCommand extends Command
{
    use RunsIntegrityCheck;

    protected $signature = 'integrity:imports';

    protected $description = 'İthalat kur, landed cost, allocation ve stok giriş bütünlüğünü kontrol eder';

    public function handle(ImportIntegrityCheck $check): int
    {
        return $this->runCheck($check);
    }
}
