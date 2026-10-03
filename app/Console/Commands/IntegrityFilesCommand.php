<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsIntegrityCheck;
use App\Support\Integrity\Checks\FilesIntegrityCheck;
use Illuminate\Console\Command;

class IntegrityFilesCommand extends Command
{
    use RunsIntegrityCheck;

    protected $signature = 'integrity:files';

    protected $description = 'Attachment kayıtlarının fiziksel dosyalarını doğrular';

    public function handle(FilesIntegrityCheck $check): int
    {
        return $this->runCheck($check);
    }
}
