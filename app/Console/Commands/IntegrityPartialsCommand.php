<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsIntegrityCheck;
use App\Support\Integrity\Checks\PartialDocumentCheck;
use Illuminate\Console\Command;

class IntegrityPartialsCommand extends Command
{
    use RunsIntegrityCheck;

    protected $signature = 'integrity:partials';

    protected $description = 'Kısmi belge ve source_line ancestry bütünlüğünü kontrol eder';

    public function handle(PartialDocumentCheck $check): int
    {
        return $this->runCheck($check);
    }
}
