<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsIntegrityCheck;
use App\Support\Integrity\Checks\DocumentTotalCheck;
use Illuminate\Console\Command;

class IntegrityDocumentsCommand extends Command
{
    use RunsIntegrityCheck;

    protected $signature = 'integrity:documents';

    protected $description = 'Belge toplam bütünlüğünü kontrol eder';

    public function handle(DocumentTotalCheck $check): int
    {
        return $this->runCheck($check);
    }
}
