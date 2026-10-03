<?php

namespace App\Console\Commands;

use App\Models\Period;
use App\Support\Period\PeriodContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class PruneIdempotencyKeysCommand extends Command
{
    protected $signature = 'idempotency:prune';

    protected $description = 'Yedi günden eski tamamlanmış idempotency kayıtlarını temizler';

    public function handle(): int
    {
        $failed = [];

        Period::query()
            ->whereIn('status', ['active', 'closed'])
            ->each(function (Period $period) use (&$failed): void {
                try {
                    PeriodContext::use($period->company_id, $period->id);

                    DB::connection('period')
                        ->table('idempotency_keys')
                        ->where('status', 'done')
                        ->where('completed_at', '<', now()->subDays(7))
                        ->delete();
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
}
