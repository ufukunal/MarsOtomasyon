<?php

namespace App\Listeners;

use App\Models\Period;
use Illuminate\Console\Events\CommandStarting;

class PrepareBackupSources
{
    public function handle(CommandStarting $event): void
    {
        if ($event->command !== 'backup:run') {
            return;
        }

        $master = config('database.connections.master');
        $connections = ['master'];

        Period::query()
            ->whereIn('status', ['active', 'closed'])
            ->orderBy('company_id')
            ->orderBy('year')
            ->each(function (Period $period) use ($master, &$connections): void {
                $connectionName = 'backup_period_'.$period->id;

                config([
                    "database.connections.{$connectionName}" => [
                        ...$master,
                        'database' => $period->database_name,
                    ],
                ]);

                $connections[] = $connectionName;
            });

        config([
            'backup.backup.source.databases' => $connections,
        ]);
    }
}
