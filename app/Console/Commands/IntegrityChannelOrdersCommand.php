<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsIntegrityCheck;
use App\Support\Integrity\Checks\ChannelOrderIntegrityCheck;
use Illuminate\Console\Command;

class IntegrityChannelOrdersCommand extends Command
{
    use RunsIntegrityCheck;

    protected $signature = 'integrity:channel-orders';

    protected $description = 'Kanal sipariş snapshot ve master external-event provenance bütünlüğünü kontrol eder';

    public function handle(ChannelOrderIntegrityCheck $check): int
    {
        return $this->runCheck($check);
    }
}
