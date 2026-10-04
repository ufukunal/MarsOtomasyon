<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsIntegrityCheck;
use App\Support\Integrity\Checks\ContactBalanceCheck;
use Illuminate\Console\Command;

class IntegrityContactsCommand extends Command
{
    use RunsIntegrityCheck;

    protected $signature = 'integrity:contacts';

    protected $description = 'Cari ledger bütünlüğünü kontrol eder';

    public function handle(ContactBalanceCheck $check): int
    {
        return $this->runCheck($check);
    }
}
