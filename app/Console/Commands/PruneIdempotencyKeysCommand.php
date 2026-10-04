<?php

namespace App\Console\Commands;

use App\Models\Period;
use App\Support\Period\PeriodContext;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

class PruneIdempotencyKeysCommand extends Command
{
    protected $signature = 'idempotency:prune';

    protected $description = 'Eski ve terk edilmiş idempotency kayıtlarını temizler';

    public function handle(): int
    {
        $failed = [];

        try {
            $this->pruneConnection(DB::connection('master'));
        } catch (Throwable $exception) {
            $failed['master'] = $exception->getMessage();
        }

        Period::query()
            ->whereIn('status', ['active', 'closed'])
            ->each(function (Period $period) use (&$failed): void {
                try {
                    PeriodContext::useSystem($period->company_id, $period->id);
                    $this->pruneConnection(DB::connection('period'));
                } catch (Throwable $exception) {
                    $failed[$period->database_name] = $exception->getMessage();
                }
            });

        PeriodContext::clear();

        foreach ($failed as $database => $error) {
            $this->error("{$database}: {$error}");
        }

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }

    private function pruneConnection(ConnectionInterface $connection): void
    {
        $connection->table('idempotency_keys')
            ->where('status', 'done')
            ->where('completed_at', '<', now()->subDays(7))
            ->delete();

        $connection->table('idempotency_keys')
            ->where('status', 'processing')
            ->where('updated_at', '<', now()->subDay())
            ->delete();
    }
}
