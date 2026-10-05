<?php

namespace App\Support\Integrity\Checks;

use App\Enums\DocumentType;
use App\Models\ChannelExternalEventRegistry;
use App\Models\Period;
use App\Models\Period\ChannelOrderSnapshot;
use App\Models\SalesChannelAccount;
use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
use App\Support\Period\PeriodContext;
use Illuminate\Support\Facades\Schema;

final class ChannelOrderIntegrityCheck implements IntegrityCheck
{
    public function name(): string
    {
        return 'channel_orders_phase9';
    }

    public function run(): IntegrityResult
    {
        $started = hrtime(true);

        if (! Schema::connection('period')->hasTable('channel_order_snapshots')
            || ! Schema::connection('master')->hasTable('channel_external_event_registry')) {
            return new IntegrityResult(
                checked: 0,
                mismatches: [],
                durationMs: $this->elapsed($started),
                meta: ['status' => 'not_applicable'],
            );
        }

        $currentPeriod = Period::query()->findOrFail(PeriodContext::periodId());
        $accounts = SalesChannelAccount::query()
            ->where('company_id', PeriodContext::companyId())
            ->get()
            ->keyBy('id');
        $snapshots = ChannelOrderSnapshot::query()
            ->with('salesOrder')
            ->orderBy('id')
            ->get();
        $mismatches = [];

        foreach ($snapshots as $snapshot) {
            $order = $snapshot->salesOrder;
            $registry = ChannelExternalEventRegistry::query()
                ->where('channel_account_id', $snapshot->channel_account_id)
                ->where('event_type', 'order')
                ->where('external_id', $snapshot->external_order_id)
                ->first();

            if (! $accounts->has($snapshot->channel_account_id)
                || $order === null
                || $order->document_type !== DocumentType::SalesOrder) {
                $mismatches[] = [
                    'channel_order_snapshot_id' => $snapshot->id,
                    'reason' => 'snapshot_account_or_sales_order_invalid',
                ];
                continue;
            }

            if ($registry === null || $registry->status !== 'done') {
                $mismatches[] = [
                    'channel_order_snapshot_id' => $snapshot->id,
                    'sales_order_id' => $order->id,
                    'reason' => 'external_event_registry_missing_or_not_done',
                ];
                continue;
            }

            $registryTargetsCurrent = (int) $registry->period_id === (int) $currentPeriod->id
                && (int) $registry->period_document_id === (int) $order->id;

            if ($registryTargetsCurrent) {
                continue;
            }

            $routedPeriod = $registry->period_id
                ? Period::query()
                    ->whereKey((int) $registry->period_id)
                    ->where('company_id', $currentPeriod->company_id)
                    ->first()
                : null;
            $historicalSourceReroutedForward = $routedPeriod !== null
                && (int) $routedPeriod->year > (int) $currentPeriod->year
                && (int) $registry->period_document_id > 0;

            if (! $historicalSourceReroutedForward) {
                $mismatches[] = [
                    'channel_order_snapshot_id' => $snapshot->id,
                    'sales_order_id' => $order->id,
                    'registry_period_id' => $registry->period_id,
                    'registry_period_document_id' => $registry->period_document_id,
                    'reason' => 'external_event_registry_provenance_mismatch',
                ];
            }
        }

        return new IntegrityResult(
            checked: $snapshots->count(),
            mismatches: $mismatches,
            durationMs: $this->elapsed($started),
        );
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
