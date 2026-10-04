<?php

namespace App\Actions\Sales;

use App\Actions\Documents\SaveSalesDocumentDraft;
use App\Actions\Documents\SourceLineAvailability;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\Period\DocumentRelation;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use DomainException;

final class CreateDispatchFromOrder
{
    public function __construct(
        private readonly SourceLineAvailability $availability,
        private readonly AllocateOrderLineLocations $allocator,
        private readonly SaveSalesDocumentDraft $saveDraft,
    ) {}

    /**
     * @param array<int,string> $lineQuantities
     * @param array<int,int> $fallbackLocations
     */
    public function handle(
        Document $order,
        array $lineQuantities,
        array $fallbackLocations,
        string $documentDate,
        string $idempotencyKey,
    ): Document {
        MutationAuthorizer::authorize('dispatches.create');

        return IdempotencyKey::run(
            $idempotencyKey,
            'sales-order.to-dispatch:'.$order->id,
            function () use ($order, $lineQuantities, $fallbackLocations, $documentDate): Document {
                $locked = Document::query()->with('lines')->lockForUpdate()->findOrFail($order->id);

                if ($locked->document_type !== DocumentType::SalesOrder || $locked->status !== 'confirmed') {
                    throw new DomainException('İrsaliye yalnız onaylı satış siparişinden oluşturulabilir.');
                }

                $draftLines = [];

                foreach ($lineQuantities as $lineId => $quantity) {
                    $source = DocumentLine::query()
                        ->where('document_id', $locked->id)
                        ->lockForUpdate()
                        ->findOrFail((int) $lineId);
                    $requested = bcadd((string) $quantity, '0', 3);
                    $remaining = $this->availability->orderRemaining($source);

                    if (bccomp($requested, '0', 3) <= 0 || bccomp($requested, $remaining, 3) > 0) {
                        throw new DomainException('Sevk miktarı sipariş satırı kalanını aşıyor.');
                    }

                    if ($source->line_kind === 'service') {
                        $draftLines[] = $this->copyLine($source, $requested, null);

                        continue;
                    }

                    foreach ($this->allocator->handle(
                        $source,
                        $requested,
                        $fallbackLocations[(int) $source->id] ?? null,
                    ) as $allocation) {
                        $draftLines[] = $this->copyLine(
                            $source,
                            $allocation['quantity'],
                            $allocation['location_id'],
                        );
                    }
                }

                if ($draftLines === []) {
                    throw new DomainException('Sevk edilecek satır seçilmedi.');
                }

                $dispatch = $this->saveDraft->handle(
                    DocumentType::Dispatch,
                    [
                        'document_date' => $documentDate,
                        'contact_id' => $locked->contact_id,
                        'discount_rate' => $locked->discount_rate,
                        'discount_amount' => '0',
                        'notes' => $locked->notes,
                    ],
                    $draftLines,
                );

                $actor = auth()->user();
                DocumentRelation::query()->create([
                    'source_document_id' => $locked->id,
                    'target_document_id' => $dispatch->id,
                    'relation_type' => 'order_to_dispatch',
                    'created_by' => $actor?->id,
                    'created_by_name' => $actor?->name,
                ]);

                return $dispatch->load('lines');
            },
        );
    }

    /** @return array<string,mixed> */
    private function copyLine(DocumentLine $source, string $quantity, ?int $locationId): array
    {
        return [
            'line_kind' => $source->line_kind,
            'product_id' => $source->product_id,
            'description' => $source->description,
            'unit_id' => $source->unit_id,
            'quantity' => $quantity,
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
}
