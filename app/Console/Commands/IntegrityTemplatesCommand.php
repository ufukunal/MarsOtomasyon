<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsIntegrityCheck;
use App\Support\Integrity\Checks\DocumentTemplateIntegrityCheck;
use Illuminate\Console\Command;

class IntegrityTemplatesCommand extends Command
{
    use RunsIntegrityCheck;

    protected $signature = 'integrity:templates';

    protected $description = 'Document template revizyon/default/section bütünlüğünü kontrol eder';

    public function handle(DocumentTemplateIntegrityCheck $check): int
    {
        return $this->runCheck($check, false);
    }
}
