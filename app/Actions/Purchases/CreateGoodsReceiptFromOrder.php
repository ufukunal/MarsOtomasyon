<?php

namespace App\Actions\Purchases;

use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use DomainException;

final class CreateGoodsReceiptFromOrder
{
    public function __construct(
        private readonly PurchaseLineAvailability $availability,
        private readonly SavePurchaseDocumentDraft $saveDraft,
    ) {}

    /**
     * @param array<int,string> $lineQuantities
     * @param array<int,int> $lineLocationIds
     */
    public function handle(
        Document $order,
        array $lineQuantities,
        array $lineLocationIds,
        string $documentDate,
        string $idempotencyKey,
        ?string $deliveryNote = null,
    ): Document {
        MutationAuthorizer::authorize('goods_receipts.create');

        return IdempotencyKey::run(
            $idempotencyKey,
            'purchase-order.to-goods-receipt:'.$order->id,
            function () use ($order, $lineQuantities, $lineLocationIds, $documentDate, $deliveryNote): Document {
                $locked = Document::query()->with('lines')->lockForUpdate()->findOrFail($order->id);

                if ($locked->document_type !== DocumentType::PurchaseOrder || $locked->status !== 'approved') {
                    throw new DomainException('Mal kabul yalnız onaylı satınalma siparişinden oluşturulabilir.');
                }

                $draftLines = [];

                foreach ($lineQuantities as $lineId => $quantity) {
                    $source = DocumentLine::query()
                        ->where('document_id', $locked->id)
                        ->lockForUpdate()
                        ->findOrFail((int) $lineId);
                    $requested = bcadd((string) $quantity, '0', 3);
                    $remaining = $this->availability->orderReceiptRemaining($source);

                    if (bccomp($requested, '0', 3) <= 0 || bccomp($requested, $remaining, 3) > 0) {
                        throw new DomainException('Mal kabul miktarı sipariş satırı kalanını aşıyor.');
                    }

                    $locationId = $source->line_kind === 'stock'
                        ? ($lineLocationIds[(int) $source->id] ?? $source->location_id)
                        : null;

                    if ($source->line_kind === 'stock' && $locationId === null) {
                        throw new DomainException('Stok mal kabul satırında depo/lokasyon seçilmelidir.');
                    }

                    $draftLines[] = [
                        'line_kind' => $source->line_kind,
                        'product_id' => $source->product_id,
                        'description' => $source->description,
                        'unit_id' => $source->unit_id,
                        'quantity' => $requested,
                        'conversion_factor' => $source->conversion_factor,
                        'location_id' => $locationId,
                        'unit_price' => $source->unit_price,
                        'line_discount_rate' => $source->line_discount_rate,
                        'line_discount_amount' => '0',
                        'vat_rate' => $source->vat_rate,
                        'configuration' => $source->configuration,
                        'source_line_id' => $source->id,
                    ];
                }

                if ($draftLines === []) {
                    throw new DomainException('Mal kabul edilecek satır seçilmedi.');
                }

                return $this->saveDraft->handle(
                    DocumentType::GoodsReceipt,
                    [
                        'contact_id' => $locked->contact_id,
                        'document_date' => $documentDate,
                        'currency' => $locked->currency,
                        'exchange_rate' => $locked->exchange_rate,
                        'discount_rate' => $locked->discount_rate,
                        'notes' => $deliveryNote,
                    ],
                    $draftLines,
                );
            },
        );
    }
}
