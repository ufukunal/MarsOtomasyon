<?php

namespace App\Actions\Channels;

use App\Actions\Documents\ResolveSourceLineage;
use App\Actions\Returns\CreateReturnFromInvoice;
use App\Actions\Returns\ReturnLineAvailability;
use App\DataObjects\Channels\ChannelInboundEvent;
use App\Enums\DocumentType;
use App\Models\Period\ChannelOrderSnapshot;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\SalesChannelAccount;
use App\Support\Channels\ChannelAutomationActorResolver;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ImportChannelReturn
{
    public function __construct(
        private readonly ResolveSourceLineage $lineage,
        private readonly ReturnLineAvailability $availability,
        private readonly CreateReturnFromInvoice $returns,
        private readonly ChannelAutomationActorResolver $actors,
    ) {}

    public function handle(
        SalesChannelAccount $account,
        ChannelInboundEvent $event,
    ): Document {
        if ($event->eventType !== 'return') {
            throw new DomainException('ImportChannelReturn yalnız return event kabul eder.');
        }

        return $this->actors->run(
            ['returns.create'],
            fn () => DB::connection('period')->transaction(
                fn (): Document => $this->createDraft($account, $event),
                attempts: 3,
            ),
        );
    }

    private function createDraft(
        SalesChannelAccount $account,
        ChannelInboundEvent $event,
    ): Document {
        $data = $event->data;
        $orderNumber = trim((string) ($data['orderNumber'] ?? $data['orderParentNumber'] ?? ''));
        $externalOrderId = trim((string) ($data['externalOrderId'] ?? $orderNumber));

        if ($orderNumber === '' || $externalOrderId === '') {
            throw new DomainException('Kanal return claim orderNumber/externalOrderId içermiyor.');
        }

        $snapshot = ChannelOrderSnapshot::query()
            ->where('channel_account_id', $account->id)
            ->where('external_order_id', $externalOrderId)
            ->firstOrFail();
        $order = Document::query()->with('lines')->findOrFail($snapshot->sales_order_id);
        $orderLinesByExternalId = [];

        foreach ($order->lines as $line) {
            $channel = is_array($line->configuration['channel'] ?? null)
                ? $line->configuration['channel']
                : [];
            $externalLineId = trim((string) ($channel['external_line_id'] ?? ''));

            if ($externalLineId !== '') {
                $orderLinesByExternalId[$externalLineId] = (int) $line->id;
            }
        }

        $claimQuantitiesByOrderLine = [];
        $reasonNames = [];
        $normalizedReturnLines = is_array($data['returnLines'] ?? null)
            ? $data['returnLines']
            : [];

        if ($normalizedReturnLines !== []) {
            foreach ($normalizedReturnLines as $returnLine) {
                if (! is_array($returnLine)) {
                    continue;
                }

                $externalLineId = trim((string) ($returnLine['externalLineId'] ?? ''));
                $orderLineId = $externalLineId !== ''
                    ? ($orderLinesByExternalId[$externalLineId] ?? null)
                    : null;

                if ($orderLineId === null) {
                    $orderLineId = $this->fallbackOrderLineId(
                        $order,
                        (string) ($returnLine['stockCode'] ?? ''),
                        (string) ($returnLine['barcode'] ?? ''),
                    );
                }

                if ($orderLineId === null) {
                    throw new DomainException('Kanal iade satırı satış siparişi satırıyla eşlenemedi.');
                }

                $quantity = bcadd((string) ($returnLine['quantity'] ?? '0'), '0', 3);

                if (bccomp($quantity, '0', 3) <= 0) {
                    continue;
                }

                $claimQuantitiesByOrderLine[$orderLineId] = bcadd(
                    $claimQuantitiesByOrderLine[$orderLineId] ?? '0.000',
                    $quantity,
                    3,
                );

                $reason = trim((string) ($returnLine['reason'] ?? ''));

                if ($reason !== '') {
                    $reasonNames[$reason] = true;
                }
            }
        } else {
            foreach (($data['items'] ?? []) as $item) {
                if (! is_array($item) || ! is_array($item['orderLine'] ?? null)) {
                    continue;
                }

                $orderLineData = $item['orderLine'];
                $externalLineId = trim((string) ($orderLineData['id'] ?? $orderLineData['lineId'] ?? ''));
                $orderLineId = $externalLineId !== ''
                    ? ($orderLinesByExternalId[$externalLineId] ?? null)
                    : null;

                if ($orderLineId === null) {
                    $orderLineId = $this->fallbackOrderLineId(
                        $order,
                        (string) ($orderLineData['merchantSku'] ?? $orderLineData['stockCode'] ?? ''),
                        (string) ($orderLineData['barcode'] ?? ''),
                    );
                }

                if ($orderLineId === null) {
                    throw new DomainException('Kanal claim satırı satış siparişi satırıyla eşlenemedi.');
                }

                $quantity = '0.000';

                foreach (($item['claimItems'] ?? []) as $claimItem) {
                    if (! is_array($claimItem)) {
                        continue;
                    }

                    $status = (string) ($claimItem['claimItemStatus']['name'] ?? 'Created');

                    if ($status !== 'Created') {
                        continue;
                    }

                    $quantity = bcadd($quantity, '1.000', 3);
                    $reason = trim((string) (
                        $claimItem['customerClaimItemReason']['name']
                        ?? $claimItem['trendyolClaimItemReason']['name']
                        ?? ''
                    ));

                    if ($reason !== '') {
                        $reasonNames[$reason] = true;
                    }
                }

                if (bccomp($quantity, '0', 3) > 0) {
                    $claimQuantitiesByOrderLine[$orderLineId] = bcadd(
                        $claimQuantitiesByOrderLine[$orderLineId] ?? '0.000',
                        $quantity,
                        3,
                    );
                }
            }
        }

        if ($claimQuantitiesByOrderLine === []) {
            throw new DomainException('Kanal iade eventinde işlenebilir iade kalemi bulunamadı.');
        }

        $invoiceLines = DocumentLine::query()
            ->with('document')
            ->whereHas('document', fn ($query) => $query
                ->where('document_type', DocumentType::SalesInvoice->value)
                ->where('status', 'posted'))
            ->orderBy('document_id')
            ->orderBy('id')
            ->get();

        $lineQuantities = [];
        $locationIds = [];
        $sourceInvoiceId = null;

        foreach ($claimQuantitiesByOrderLine as $orderLineId => $requested) {
            $remaining = $requested;

            foreach ($invoiceLines as $invoiceLine) {
                $resolved = $this->lineage->handle($invoiceLine);

                if ((int) ($resolved['origin_order_line_id'] ?? 0) !== (int) $orderLineId) {
                    continue;
                }

                if ($sourceInvoiceId !== null && $sourceInvoiceId !== (int) $invoiceLine->document_id) {
                    throw new DomainException(
                        'Tek kanal iade eventi birden fazla satış faturasına dağılıyor; otomatik draft iade güvenli değil.',
                    );
                }

                $available = $this->availability->remaining($invoiceLine, DocumentType::SalesReturn);

                if (bccomp($available, '0', 3) <= 0) {
                    continue;
                }

                $take = bccomp($remaining, $available, 3) < 0 ? $remaining : $available;

                if (bccomp($take, '0', 3) <= 0) {
                    continue;
                }

                $sourceInvoiceId = (int) $invoiceLine->document_id;
                $lineQuantities[(int) $invoiceLine->id] = bcadd(
                    $lineQuantities[(int) $invoiceLine->id] ?? '0.000',
                    $take,
                    3,
                );

                if ($invoiceLine->line_kind === 'stock') {
                    if ($invoiceLine->location_id === null) {
                        throw new DomainException('Kanal iadesi kaynak satış faturası lokasyonu eksik.');
                    }

                    $locationIds[(int) $invoiceLine->id] = (int) $invoiceLine->location_id;
                }

                $remaining = bcsub($remaining, $take, 3);

                if (bccomp($remaining, '0', 3) <= 0) {
                    break;
                }
            }

            if (bccomp($remaining, '0', 3) > 0) {
                throw new DomainException(
                    'Kanal iade miktarı henüz posted satış faturasıyla karşılanamıyor; daha sonra yeniden denenmelidir.',
                );
            }
        }

        if ($sourceInvoiceId === null) {
            throw new DomainException(
                'Kanal iadesi için posted satış faturası bulunamadı; daha sonra yeniden denenmelidir.',
            );
        }

        $sourceInvoice = Document::query()->findOrFail($sourceInvoiceId);
        $draft = $this->returns->handle(
            $sourceInvoice,
            DocumentType::SalesReturn,
            $lineQuantities,
            $locationIds,
            $event->occurredAt->setTimezone(config('app.timezone'))->toDateString(),
            hash('sha256', 'channel-return:'.$account->id.':'.$event->externalId),
            $account->platform->label().' iade '.$event->externalId
                .($reasonNames !== [] ? ' · '.implode(', ', array_keys($reasonNames)) : ''),
        );

        $draft->requirements_snapshot = [
            ...($draft->requirements_snapshot ?? []),
            'channel' => [
                'platform' => $account->platform->value,
                'channel_account_id' => (int) $account->id,
                'event_type' => 'return',
                'external_id' => $event->externalId,
                'external_order_id' => $externalOrderId,
                'external_package_id' => $data['externalPackageId'] ?? $data['orderShipmentPackageId'] ?? null,
            ],
        ];
        $draft->save();

        return $draft->refresh();
    }

    private function fallbackOrderLineId(
        Document $order,
        string $stockCode,
        string $barcode,
    ): ?int {
        $stockCode = trim($stockCode);
        $barcode = trim($barcode);
        $matches = $order->lines->filter(function (DocumentLine $line) use ($stockCode, $barcode): bool {
            $channel = is_array($line->configuration['channel'] ?? null)
                ? $line->configuration['channel']
                : [];

            if ($stockCode !== '' && (string) ($channel['stock_code'] ?? '') === $stockCode) {
                return true;
            }

            return $barcode !== '' && (string) ($channel['barcode'] ?? '') === $barcode;
        });

        return $matches->count() === 1 ? (int) $matches->first()->id : null;
    }
}
