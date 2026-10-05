<?php

namespace App\Console\Commands;

use App\Actions\Channels\PollChannelAccount;
use App\Models\Period;
use App\Models\SalesChannelAccount;
use App\Support\Channels\ChannelAdapterResolver;
use App\Support\Channels\ChannelAutomationActorResolver;
use App\Support\Period\PeriodContext;
use Illuminate\Console\Command;
use Throwable;

class PollChannelsCommand extends Command
{
    protected $signature = 'channels:poll {--account=}';

    protected $description = 'Aktif dönemlerde kanal order/cancel/return polling çalıştırır';

    public function handle(
        PollChannelAccount $poll,
        ChannelAdapterResolver $adapters,
        ChannelAutomationActorResolver $actors,
    ): int {
        $failed = 0;
        $accountFilter = $this->option('account');

        try {
            Period::query()
                ->where('status', 'active')
                ->orderBy('company_id')
                ->orderBy('year')
                ->each(function (Period $period) use (
                    $poll,
                    $adapters,
                    $actors,
                    $accountFilter,
                    &$failed,
                ): void {
                    PeriodContext::useSystem((int) $period->company_id, (int) $period->id);

                    $accounts = SalesChannelAccount::query()
                        ->where('company_id', $period->company_id)
                        ->where('is_active', true)
                        ->when($accountFilter, fn ($query) => $query->whereKey((int) $accountFilter))
                        ->orderBy('id')
                        ->get();

                    foreach ($accounts as $account) {
                        if (! $adapters->hasAdapter($account->platform)) {
                            continue;
                        }

                        try {
                            $counts = $actors->run(
                                ['channel_sync.update'],
                                fn () => $poll->handle($account),
                            );

                            $this->line(sprintf(
                                '%s #%d order=%d cancel=%d return=%d processed=%d',
                                $account->platform->value,
                                $account->id,
                                $counts['orders'],
                                $counts['cancellations'],
                                $counts['returns'],
                                $counts['processed'],
                            ));
                        } catch (Throwable $exception) {
                            $failed++;
                            $this->error(
                                $period->database_name.' account#'.$account->id.': '.$exception->getMessage(),
                            );
                        }
                    }
                });
        } finally {
            PeriodContext::clear();
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
