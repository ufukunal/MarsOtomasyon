<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsIntegrityCheck;
use App\Support\Integrity\Checks\ReturnIntegrityCheck;
use Illuminate\Console\Command;

class IntegrityReturnsCommand extends Command
{
    use RunsIntegrityCheck;

    protected $signature = 'integrity:returns';

    protected $description = 'Satış/alış iadesi, karantina ve cari etki bütünlüğünü kontrol eder';

    public function handle(ReturnIntegrityCheck $check): int
    {
        return $this->runCheck($check);
    }
}
