<?php

namespace App\Jobs;

use App\Actions\Channels\PollChannelAccount;
use App\Enums\SalesChannelPlatform;
use App\Models\SalesChannelAccount;
use App\Support\Audit\AuditContext;
use App\Support\Channels\ChannelAutomationActorResolver;
use App\Support\Period\PeriodContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PollWooCommerceWebhookJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 4;

    public int $uniqueFor = 60;

    public function __construct(
        public readonly int $companyId,
        public readonly int $periodId,
        public readonly int $channelAccountId,
        public readonly string $topic,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function uniqueId(): string
    {
        return implode(':', [
            'woocommerce-webhook-poll',
            $this->companyId,
            $this->periodId,
            $this->channelAccountId,
        ]);
    }

    public function handle(
        PollChannelAccount $poll,
        ChannelAutomationActorResolver $actors,
    ): void {
        PeriodContext::useSystem($this->companyId, $this->periodId);

        try {
            PeriodContext::ensureWritable();

            $account = SalesChannelAccount::query()
                ->whereKey($this->channelAccountId)
                ->where('company_id', $this->companyId)
                ->where('platform', SalesChannelPlatform::WooCommerce->value)
                ->where('is_active', true)
                ->firstOrFail();

            $actors->run(
                ['channel_sync.update'],
                function () use ($poll, $account): void {
                    $counts = $poll->handle(
                        $account,
                        CarbonImmutable::now()->subMinutes(30),
                    );

                    AuditContext::period(
                        'WooCommerce webhook reconciliation tamamlandı.',
                        [
                            'channel_account_id' => $account->id,
                            'topic' => $this->topic,
                            'orders' => $counts['orders'],
                            'cancellations' => $counts['cancellations'],
                            'returns' => $counts['returns'],
                            'processed' => $counts['processed'],
                        ],
                        null,
                        'woocommerce_webhook_reconciled',
                    );
                },
            );
        } finally {
            PeriodContext::clear();
        }
    }
}
