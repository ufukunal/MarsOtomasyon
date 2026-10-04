<?php

namespace App\Actions\Sales;

use App\Actions\Documents\SaveSalesDocumentDraft;
use App\Actions\Documents\SourceLineAvailability;
use App\Enums\DocumentType;
use App\Models\Period\Contact;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\Period\DocumentRelation;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use DomainException;

final class CreateInvoiceFromDispatches
{
    public function __construct(
        private readonly SourceLineAvailability $availability,
        private readonly ResolveSalesDueDate $dueDate,
        private readonly SaveSalesDocumentDraft $saveDraft,
    ) {}

    /**
     * @param  array<int,string>  $dispatchLineQuantities
     */
    public function handle(
        array $dispatchLineQuantities,
        string $documentDate,
        string $idempotencyKey,
    ): Document {
        MutationAuthorizer::authorize('sales_invoices.create');

        return IdempotencyKey::run(
            $idempotencyKey,
            'dispatches.to-invoice:'.hash('sha256', json_encode(array_keys($dispatchLineQuantities)) ?: ''),
            function () use ($dispatchLineQuantities, $documentDate): Document {
                $draftLines = [];
                $dispatchIds = [];
                $contactId = null;
                $discountRate = null;
                $currency = null;

                foreach ($dispatchLineQuantities as $lineId => $quantity) {
                    $source = DocumentLine::query()
                        ->with('document')
                        ->lockForUpdate()
                        ->findOrFail((int) $lineId);
                    $dispatch = $source->document;

                    if ($dispatch->document_type !== DocumentType::Dispatch || $dispatch->status !== 'posted') {
                        throw new DomainException('Fatura kaynağı kesinleşmiş irsaliye olmalıdır.');
                    }

                    $contactId ??= $dispatch->contact_id;
                    $currency ??= $dispatch->currency;
                    $discountRate ??= (string) $dispatch->discount_rate;

                    if ($dispatch->contact_id !== $contactId
                        || $dispatch->currency !== $currency
                        || bccomp((string) $dispatch->discount_rate, $discountRate, 4) !== 0) {
                        throw new DomainException('Birleştirilen irsaliyelerin cari, para birimi ve satış koşulları uyumlu olmalıdır.');
                    }

                    $requested = bcadd((string) $quantity, '0', 3);
                    $remaining = $this->availability->dispatchRemaining($source);

                    if (bccomp($requested, '0', 3) <= 0 || bccomp($requested, $remaining, 3) > 0) {
                        throw new DomainException('Fatura miktarı irsaliye satırı kalanını aşıyor.');
                    }

                    $draftLines[] = [
                        'line_kind' => $source->line_kind,
                        'product_id' => $source->product_id,
                        'description' => $source->description,
                        'unit_id' => $source->unit_id,
                        'quantity' => $requested,
                        'conversion_factor' => $source->conversion_factor,
                        'location_id' => $source->location_id,
                        'unit_price' => $source->unit_price,
                        'line_discount_rate' => $source->line_discount_rate,
                        'line_discount_amount' => '0',
                        'vat_rate' => $source->vat_rate,
                        'configuration' => $source->configuration,
                        'source_line_id' => $source->id,
                    ];
                    $dispatchIds[(int) $dispatch->id] = true;
                }

                if ($draftLines === [] || $contactId === null) {
                    throw new DomainException('Faturalanacak irsaliye satırı seçilmedi.');
                }

                $contact = Contact::query()->findOrFail((int) $contactId);
                $invoice = $this->saveDraft->handle(
                    DocumentType::SalesInvoice,
                    [
                        'document_date' => $documentDate,
                        'due_date' => $this->dueDate->handle($contact, $documentDate),
                        'contact_id' => $contactId,
                        'discount_rate' => $discountRate,
                        'discount_amount' => '0',
                    ],
                    $draftLines,
                );

                $actor = auth()->user();

                foreach (array_keys($dispatchIds) as $dispatchId) {
                    DocumentRelation::query()->create([
                        'source_document_id' => $dispatchId,
                        'target_document_id' => $invoice->id,
                        'relation_type' => 'dispatch_to_invoice',
                        'created_by' => $actor?->id,
                        'created_by_name' => $actor?->name,
                    ]);
                }

                return $invoice->load('lines');
            },
        );
    }
}
