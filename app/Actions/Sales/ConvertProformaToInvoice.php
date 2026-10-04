<?php

namespace App\Actions\Sales;

use App\Actions\Documents\ResolveSourceLineage;
use App\Actions\Documents\SaveSalesDocumentDraft;
use App\Enums\DocumentType;
use App\Models\Period\Contact;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\Period\DocumentRelation;
use App\Models\Period\Location;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use DomainException;

final class ConvertProformaToInvoice
{
    public function __construct(
        private readonly ResolveSourceLineage $lineage,
        private readonly AllocateOrderLineLocations $allocator,
        private readonly ResolveSalesDueDate $dueDate,
        private readonly SaveSalesDocumentDraft $saveDraft,
    ) {}

    /** @param array<int,int> $fallbackLocations */
    public function handle(
        Document $proforma,
        array $fallbackLocations,
        string $documentDate,
        string $idempotencyKey,
    ): Document {
        MutationAuthorizer::authorize('sales_invoices.create');

        return IdempotencyKey::run(
            $idempotencyKey,
            'proforma.to-invoice:'.$proforma->id,
            function () use ($proforma, $fallbackLocations, $documentDate): Document {
                $locked = Document::query()->with('lines')->lockForUpdate()->findOrFail($proforma->id);

                if ($locked->document_type !== DocumentType::Proforma || $locked->status !== 'posted') {
                    throw new DomainException('Yalnız kesinleşmiş proforma satış faturasına dönüştürülebilir.');
                }

                if ($locked->contact_id === null) {
                    throw new DomainException('Proforma faturasında cari zorunludur.');
                }

                $draftLines = [];

                foreach ($locked->lines as $line) {
                    if ($line->line_kind === 'service') {
                        $draftLines[] = $this->copyLine($line, (string) $line->quantity, null);

                        continue;
                    }

                    $lineage = $this->lineage->handle($line);

                    if ($lineage['origin_order_line_id'] !== null) {
                        $origin = DocumentLine::query()
                            ->lockForUpdate()
                            ->findOrFail($lineage['origin_order_line_id']);

                        foreach ($this->allocator->handle(
                            $origin,
                            (string) $line->quantity,
                            $fallbackLocations[(int) $line->id] ?? null,
                        ) as $allocation) {
                            $draftLines[] = $this->copyLine(
                                $line,
                                $allocation['quantity'],
                                $allocation['location_id'],
                            );
                        }

                        continue;
                    }

                    $locationId = $line->location_id
                        ?? ($fallbackLocations[(int) $line->id] ?? null);

                    if ($locationId === null) {
                        throw new DomainException('Proforma kaynaklı doğrudan stok çıkışı için lokasyon seçilmelidir.');
                    }

                    Location::query()->where('is_active', true)->findOrFail((int) $locationId);
                    $draftLines[] = $this->copyLine($line, (string) $line->quantity, (int) $locationId);
                }

                $contact = Contact::query()->findOrFail((int) $locked->contact_id);
                $invoice = $this->saveDraft->handle(
                    DocumentType::SalesInvoice,
                    [
                        'document_date' => $documentDate,
                        'due_date' => $this->dueDate->handle($contact, $documentDate),
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
                    'target_document_id' => $invoice->id,
                    'relation_type' => 'proforma_to_invoice',
                    'created_by' => $actor?->id,
                    'created_by_name' => $actor?->name,
                ]);

                return $invoice->load('lines');
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
