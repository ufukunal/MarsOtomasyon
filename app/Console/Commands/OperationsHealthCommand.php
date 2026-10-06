<?php

namespace App\Console\Commands;

use App\Support\Operations\OperationalHealthService;
use Illuminate\Console\Command;

class OperationsHealthCommand extends Command
{
    protected $signature = 'operations:health {--no-persist : health_check_runs kaydı oluşturma}';

    protected $description = 'Production operational health kontrollerini çalıştırır';

    public function handle(OperationalHealthService $health): int
    {
        $result = $health->check(! (bool) $this->option('no-persist'));

        foreach ($result['checks'] as $name => $check) {
            $this->line(sprintf(
                '%s: %s (%s)',
                $name,
                ($check['ok'] ?? false) ? 'OK' : 'FAIL',
                $check['status'] ?? 'unknown',
            ));
        }

        $this->line('correlation_id='.$result['correlation_id']);

        return $result['status'] === 'healthy' ? self::SUCCESS : self::FAILURE;
    }
}
