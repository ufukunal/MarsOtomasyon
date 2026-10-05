<?php

namespace App\Actions\Channels;

use App\Actions\Documents\SourceLineAvailability;
use App\Actions\Stock\ReleaseReservation;
use App\DataObjects\Channels\ChannelInboundEvent;
use App\Enums\DocumentType;
use App\Models\Period\ChannelOrderSnapshot;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\Period\StockReservation;
use App\Models\SalesChannelAccount;
use App\Support\Audit\AuditContext;
use App\Support\Channels\ChannelAutomationActorResolver;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ImportChannelCancellation
{
    public function __construct(
        private readonly SourceLineAvailability $availability,
        private readonly ReleaseReservation $releaseReservation,
        private readonly ChannelAutomationActorResolver $actors,
    ) {}

    public function handle(
        SalesChannelAccount $account,
        ChannelInboundEvent $event,
    ): Document {
        if ($event->eventType !== 'cancel') {
            throw new DomainException('ImportChannelCancellation yalnız cancel event kabul eder.');
        }

        return $this->actors->run(
            ['sales_orders.cancel', 'reservations.update'],
            fn () => DB::connection('period')->transaction(
                fn (): Document => $this->cancel($account, $event),
                attempts: 3,
            ),
        );
    }

    private function cancel(SalesChannelAccount $account, ChannelInboundEvent $event): Document
    {
        $data = $event->data;
        $orderNumber = trim((string) ($data['orderNumber'] ?? ''));
        $externalOrderId = trim((string) ($data['externalOrderId'] ?? $orderNumber));

        if ($orderNumber === '' || $externalOrderId === '') {
            throw new DomainException('Kanal cancel event orderNumber/externalOrderId içermiyor.');
        }

        $snapshot = ChannelOrderSnapshot::query()
            ->where('channel_account_id', $account->id)
            ->where('external_order_id', $externalOrderId)
            ->lockForUpdate()
            ->firstOrFail();
        $order = Document::query()
            ->with('lines')
            ->lockForUpdate()
            ->findOrFail($snapshot->sales_order_id);

        if ($order->document_type !== DocumentType::SalesOrder
            || ! in_array($order->status, ['confirmed', 'closed'], true)) {
            throw new DomainException('Kanal cancel hedefi geçerli satış siparişi değil.');
        }

        if ($order->status === 'closed') {
            return $order;
        }

        $cancelled = [];
        $matchedLines = 0;
        $sourceLineCount = 0;

        foreach (($data['lines'] ?? []) as $sourceLine) {
            if (! is_array($sourceLine)) {
                continue;
            }

            $requested = bcadd((string) ($sourceLine['quantity'] ?? '0'), '0', 3);

            if (bccomp($requested, '0', 3) <= 0) {
                continue;
            }

            $sourceLineCount++;

            $line = $this->resolveOrderLine($order, $sourceLine);

            if (! $line) {
                continue;
            }

            $matchedLines++;
            $remaining = $this->availability->orderRemaining($line);

            if (bccomp($remaining, '0', 3) <= 0) {
                continue;
            }

            $cancel = bccomp($requested, $remaining, 3) < 0 ? $requested : $remaining;
            $this->releaseReservations($line, $cancel, $account, $event);

            $line->cancelled_quantity = bcadd((string) $line->cancelled_quantity, $cancel, 3);
            $line->version = (int) $line->version + 1;
            $line->save();

            $cancelled[] = [
                'line_id' => (int) $line->id,
                'quantity' => $cancel,
            ];
        }

        if ($sourceLineCount > 0 && $matchedLines === 0) {
            throw new DomainException('Kanal cancel payload satırları ERP sipariş satırlarıyla eşlenemedi.');
        }

        $allClosed = $order->lines()
            ->get()
            ->every(fn (DocumentLine $line): bool => bccomp($this->availability->orderRemaining($line), '0', 3) <= 0);

        if ($allClosed) {
            $order->status = 'closed';
            $order->version = (int) $order->version + 1;
            $order->save();
        }

        $snapshot->external_package_id = trim((string) ($data['shipmentPackageId'] ?? '')) ?: $snapshot->external_package_id;
        $snapshot->campaign_metadata = [
            ...($snapshot->campaign_metadata ?? []),
            'last_cancel_package_id' => $data['shipmentPackageId'] ?? null,
            'last_cancel_modified_date' => $data['lastModifiedDate'] ?? null,
        ];
        $snapshot->save();

        AuditContext::period(
            'Kanal satış siparişi kısmi/tam iptal işlendi.',
            [
                'channel_account_id' => $account->id,
                'external_order_id' => $externalOrderId,
                'external_cancel_id' => $event->externalId,
                'cancelled_lines' => $cancelled,
            ],
            $order,
            'channel_order_cancelled',
        );

        return $order->refresh();
    }

    private function resolveOrderLine(Document $order, array $sourceLine): ?DocumentLine
    {
        $externalLineId = trim((string) ($sourceLine['lineId'] ?? ''));
        $stockCode = trim((string) ($sourceLine['stockCode'] ?? ''));
        $barcode = trim((string) ($sourceLine['barcode'] ?? ''));

        return $order->lines->first(function (DocumentLine $line) use ($externalLineId, $stockCode, $barcode): bool {
            $channel = is_array($line->configuration['channel'] ?? null)
                ? $line->configuration['channel']
                : [];

            if ($externalLineId !== '' && (string) ($channel['external_line_id'] ?? '') === $externalLineId) {
                return true;
            }

            if ($stockCode !== '' && (string) ($channel['stock_code'] ?? '') === $stockCode) {
                return true;
            }

            return $barcode !== '' && (string) ($channel['barcode'] ?? '') === $barcode;
        });
    }

    private function releaseReservations(
        DocumentLine $line,
        string $cancelQuantity,
        SalesChannelAccount $account,
        ChannelInboundEvent $event,
    ): void {
        $baseQuantity = bcadd(
            bcmul($cancelQuantity, (string) $line->conversion_factor, 8),
            '0',
            3,
        );
        $remaining = $baseQuantity;
        $reservations = StockReservation::query()
            ->where('document_line_id', $line->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($reservations as $reservation) {
            if (bccomp($remaining, '0', 3) <= 0) {
                break;
            }

            $release = bccomp($remaining, (string) $reservation->quantity, 3) < 0
                ? $remaining
                : (string) $reservation->quantity;

            $this->releaseReservation->handle(
                (int) $reservation->id,
                hash(
                    'sha256',
                    'channel-cancel-release:'.$account->id.':'.$event->externalId.':'.$reservation->id,
                ),
                $release,
            );
            $remaining = bcsub($remaining, $release, 3);
        }
    }
}
