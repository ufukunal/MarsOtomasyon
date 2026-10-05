<?php

namespace App\Actions\Channels;

use App\Models\SalesChannelAccount;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Channels\ChannelAdapterResolver;
use App\Support\Period\PeriodContext;
use Carbon\CarbonImmutable;
use DomainException;

final class PollChannelAccount
{
    public function __construct(
        private readonly ChannelAdapterResolver $adapters,
        private readonly ProcessChannelInboundEvent $processor,
    ) {}

    /** @return array{orders:int,cancellations:int,returns:int,processed:int} */
    public function handle(
        SalesChannelAccount $account,
        ?CarbonImmutable $since = null,
    ): array {
        MutationAuthorizer::authorize('channel_sync.update');
        PeriodContext::ensureWritable();

        if (! $account->is_active
            || (int) $account->company_id !== (int) PeriodContext::companyId()) {
            throw new DomainException('Kanal polling hesabı pasif veya aktif şirkete ait değil.');
        }

        $since ??= CarbonImmutable::now()->subMinutes(
            max(15, (int) config('channels.poll_lookback_minutes', 30)),
        );
        $adapter = $this->adapters->resolve($account);
        $groups = [
            'orders' => $adapter->fetchOrders($account, $since),
            'cancellations' => $adapter->fetchCancellations($account, $since),
            'returns' => $adapter->fetchReturns($account, $since),
        ];
        $result = [
            'orders' => count($groups['orders']),
            'cancellations' => count($groups['cancellations']),
            'returns' => count($groups['returns']),
            'processed' => 0,
        ];

        foreach ($groups as $events) {
            foreach ($events as $event) {
                $this->processor->handle($account, $event);
                $result['processed']++;
            }
        }

        return $result;
    }
}
