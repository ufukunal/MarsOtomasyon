<?php

namespace App\Actions\Channels;

use App\Models\Period\ChannelOrderSnapshot;
use App\Models\Period\ChannelProductListing;
use App\Models\Period\ChannelSyncEvent;
use App\Models\Period\Document;
use App\Models\SalesChannelAccount;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Channels\ChannelAdapterResolver;
use App\Support\Channels\ChannelPriceResolver;
use App\Support\Channels\ChannelStockResolver;
use App\Support\Channels\ChannelSyncRecorder;
use App\Support\Period\PeriodContext;
use DomainException;
use Throwable;

final class RetryChannelSyncEvent
{
    public function __construct(
        private readonly ChannelAdapterResolver $adapters,
        private readonly ChannelPriceResolver $prices,
        private readonly ChannelStockResolver $stock,
        private readonly ChannelSyncRecorder $sync,
    ) {}

    public function handle(ChannelSyncEvent $event, bool $automatic = false): void
    {
        MutationAuthorizer::authorize('channel_sync.update');
        PeriodContext::ensureWritable();

        if ($event->status !== 'failed'
            || $event->direction !== 'outbound'
            || $event->entity_id === null) {
            throw new DomainException('Bu sync event retry için uygun değil.');
        }

        $account = SalesChannelAccount::query()
            ->where('company_id', PeriodContext::companyId())
            ->where('is_active', true)
            ->findOrFail($event->channel_account_id);
        $adapter = $this->adapters->resolve($account);
        $attempt = $this->sync->startAttempt($event);

        try {
            if ($event->entity_type === 'listing') {
                $listing = ChannelProductListing::query()
                    ->where('channel_account_id', $account->id)
                    ->where('is_active', true)
                    ->findOrFail($event->entity_id);

                $result = match ($event->action) {
                    'publish' => $adapter->publishListing($account, $listing),
                    'content' => $adapter->updateContent($account, $listing),
                    'stock' => $adapter->updateStock(
                        $account,
                        $listing,
                        $this->stock->quantity($listing),
                        $listing->lead_time_days,
                    ),
                    'price' => $adapter->updatePrice(
                        $account,
                        $listing,
                        $this->prices->price($listing),
                    ),
                    default => throw new DomainException('Desteklenmeyen listing sync action.'),
                };
            } elseif ($event->entity_type === 'shipment') {
                $order = Document::query()->findOrFail($event->entity_id);
                $meta = is_array($event->safe_metadata) ? $event->safe_metadata : [];
                $status = trim((string) ($meta['status'] ?? ''));

                if ($status === '') {
                    throw new DomainException('Shipment retry metadata status eksik.');
                }

                $snapshot = ChannelOrderSnapshot::query()
                    ->where('sales_order_id', $order->id)
                    ->first();

                if (! $snapshot || (int) $snapshot->channel_account_id !== (int) $account->id) {
                    throw new DomainException('Shipment retry kanal sipariş snapshotı bulunamadı.');
                }

                $result = $adapter->pushShipmentStatus($account, [
                    'package_id' => (string) $snapshot->external_package_id,
                    'status' => $status,
                    'lines' => $this->shipmentLines($meta['lines'] ?? null),
                    'invoice_number' => isset($meta['invoice_number'])
                        ? (string) $meta['invoice_number']
                        : null,
                ]);
            } else {
                throw new DomainException('Bu outbound sync entity tipi retry için desteklenmiyor.');
            }

            if (! $result->success) {
                throw new DomainException($result->message ?: 'Kanal retry işlemi başarısız.');
            }

            $this->sync->markSuccess($attempt, $result->externalId);

            AuditContext::period(
                $automatic ? 'Kanal sync otomatik retry başarılı.' : 'Kanal sync manuel retry başarılı.',
                [
                    'sync_event_id' => $event->id,
                    'attempts' => $attempt->attempts,
                ],
                $event,
                $automatic ? 'channel_sync_automatic_retry' : 'channel_sync_manual_retry',
            );
        } catch (Throwable $exception) {
            $this->sync->markFailure($attempt, $exception->getMessage());

            throw $exception;
        }
    }

    /** @return list<array<string,int|string>> */
    private function shipmentLines(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $lines = [];

        foreach ($value as $line) {
            if (! is_array($line)) {
                continue;
            }

            $normalized = [];

            foreach ($line as $key => $item) {
                if ((is_int($item) || is_string($item)) && is_string($key)) {
                    $normalized[$key] = $item;
                }
            }

            if ($normalized !== []) {
                $lines[] = $normalized;
            }
        }

        return $lines;
    }
}
