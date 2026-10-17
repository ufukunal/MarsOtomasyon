<?php

namespace App\Actions\Purchases;

use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\Period\DocumentRelation;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use App\Support\Documents\ProrateFixedDiscount;
use DomainException;

final class CreatePurchaseDispatchFromOrder
{
    public function __construct(
        private readonly PurchaseLineAvailability $availability,
        private readonly SavePurchaseDocumentDraft $saveDraft,
        private readonly ProrateFixedDiscount $proration,
    ) {}

    /** @param array<int,string> $lineQuantities */
    public function handle(Document $order, array $lineQuantities, string $date, string $key): Document
    {
        MutationAuthorizer::authorize('purchase_dispatches.create');

        return IdempotencyKey::run($key, 'purchase-order.to-dispatch:'.$order->id, function () use ($order, $lineQuantities, $date): Document {
            $locked = Document::query()->with('lines')->lockForUpdate()->findOrFail($order->id);
            if ($locked->document_type !== DocumentType::PurchaseOrder || ! in_array($locked->status, ['approved', 'sent'], true)) {
                throw new DomainException('Alış irsaliyesi yalnız onaylı satınalma siparişinden oluşturulabilir.');
            }

            $lines = [];
            foreach ($lineQuantities as $lineId => $quantity) {
                $source = DocumentLine::query()->where('document_id', $locked->id)->lockForUpdate()->findOrFail((int) $lineId);
                $requested = bcadd((string) $quantity, '0', 3);
                if (bccomp($requested, '0', 3) <= 0 || bccomp($requested, $this->availability->orderDispatchRemaining($source), 3) > 0) {
                    throw new DomainException('Alış irsaliyesi miktarı sipariş kalanını aşıyor.');
                }
                $lines[] = [
                    'line_kind' => $source->line_kind,
                    'product_id' => $source->product_id,
                    'description' => $source->description,
                    'unit_id' => $source->unit_id,
                    'quantity' => $requested,
                    'conversion_factor' => $source->conversion_factor,
                    'location_id' => $source->location_id,
                    'unit_price' => $source->unit_price,
                    'line_discount_rate' => $source->line_discount_rate,
                    'vat_rate' => $source->vat_rate,
                    'source_line_id' => $source->id,
                    'configuration' => $source->configuration,
                ];
            }
            if ($lines === []) {
                throw new DomainException('İrsaliye için sipariş kalemi seçilmedi.');
            }

            $dispatch = $this->saveDraft->handle(DocumentType::PurchaseDispatch, [
                'contact_id' => $locked->contact_id,
                'document_date' => $date,
                'currency' => $locked->currency,
                'exchange_rate' => $locked->exchange_rate,
                'ui_meta' => $locked->ui_meta ?? [],
                'discount_rate' => $locked->discount_rate,
                'additional_discount_amount' => $this->proration->handle($locked, $lines),
            ], $lines);

            $actor = auth()->user();
            DocumentRelation::query()->create([
                'source_document_id' => $locked->id, 'target_document_id' => $dispatch->id,
                'relation_type' => 'purchase_order_to_purchase_dispatch',
                'created_by' => $actor?->id, 'created_by_name' => $actor?->name,
            ]);

            return $dispatch;
        });
    }
}
