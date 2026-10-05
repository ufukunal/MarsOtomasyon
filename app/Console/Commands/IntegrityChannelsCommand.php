<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsIntegrityCheck;
use App\Support\Integrity\Checks\ChannelIntegrityCheck;
use Illuminate\Console\Command;

class IntegrityChannelsCommand extends Command
{
    use RunsIntegrityCheck;

    protected $signature = 'integrity:channels';

    protected $description = 'E-ticaret kanal account/listing/location mapping bütünlüğünü kontrol eder';

    public function handle(ChannelIntegrityCheck $check): int
    {
        return $this->runCheck($check);
    }
}
