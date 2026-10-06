<?php

namespace App\Actions\Purchases;

use App\Enums\DocumentType;
use App\Models\Period\Contact;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\Period\DocumentRelation;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use DomainException;

final class CreateSupplierInvoiceFromReceipts
{
    public function __construct(
        private readonly ResolvePurchaseLineage $lineage,
        private readonly PurchaseLineAvailability $availability,
        private readonly ResolvePurchaseDueDate $dueDate,
        private readonly SavePurchaseDocumentDraft $saveDraft,
    ) {}

    /** @param array<int,string> $lineQuantities */
    public function handle(
        array $lineQuantities,
        string $documentDate,
        string $currency,
        string $exchangeRate,
        string $idempotencyKey,
    ): Document {
        MutationAuthorizer::authorize('supplier_invoices.create');

        return IdempotencyKey::run(
            $idempotencyKey,
            'supplier-invoice.create-from-receipts',
            function () use ($lineQuantities, $documentDate, $currency, $exchangeRate): Document {
                $draftLines = [];
                $contactId = null;
                $receiptDocumentIds = [];

                foreach ($lineQuantities as $lineId => $quantity) {
                    $receiptLine = DocumentLine::query()
                        ->with('document')
                        ->lockForUpdate()
                        ->findOrFail((int) $lineId);

                    if ($receiptLine->document->document_type !== DocumentType::GoodsReceipt
                        || $receiptLine->document->status !== 'posted') {
                        throw new DomainException('Alış faturası kaynağı kesinleşmiş mal kabul olmalıdır.');
                    }

                    $requested = bcadd((string) $quantity, '0', 3);
                    $remaining = $this->availability->receiptInvoiceRemaining($receiptLine);

                    if (bccomp($requested, '0', 3) <= 0 || bccomp($requested, $remaining, 3) > 0) {
                        throw new DomainException('Alış fatura miktarı mal kabul kalanını aşıyor.');
                    }

                    $lineage = $this->lineage->handle($receiptLine);

                    if ($lineage['purchase_order_line_id'] === null) {
                        throw new DomainException('Mal kabul satırının satınalma siparişi kaynağı bulunamadı.');
                    }

                    $orderLine = DocumentLine::query()->with('document')->findOrFail($lineage['purchase_order_line_id']);
                    $supplierId = (int) $orderLine->document->contact_id;

                    if ($contactId !== null && $contactId !== $supplierId) {
                        throw new DomainException('Tek alış faturasında farklı tedarikçilerin mal kabulleri birleştirilemez.');
                    }

                    if (strtoupper($currency) !== $orderLine->document->currency) {
                        throw new DomainException('Alış faturası para birimi satınalma siparişiyle eşleşmelidir.');
                    }

                    $contactId = $supplierId;
                    $receiptDocumentIds[(int) $receiptLine->document_id] = true;

                    $draftLines[] = [
                        'line_kind' => $receiptLine->line_kind,
                        'product_id' => $receiptLine->product_id,
                        'description' => $receiptLine->description,
                        'unit_id' => $receiptLine->unit_id,
                        'quantity' => $requested,
                        'conversion_factor' => $receiptLine->conversion_factor,
                        'location_id' => $receiptLine->location_id,
                        'unit_price' => $orderLine->unit_price,
                        'line_discount_rate' => $orderLine->line_discount_rate,
                        'line_discount_amount' => '0',
                        'vat_rate' => $orderLine->vat_rate,
                        'configuration' => $receiptLine->configuration,
                        'source_line_id' => $receiptLine->id,
                    ];
                }

                if ($draftLines === []) {
                    throw new DomainException('Faturalanacak mal kabul satırı seçilmedi.');
                }

                $contact = Contact::query()->findOrFail($contactId);
                $invoice = $this->saveDraft->handle(
                    DocumentType::SupplierInvoice,
                    [
                        'contact_id' => $contactId,
                        'document_date' => $documentDate,
                        'due_date' => $this->dueDate->handle($contact, $documentDate),
                        'currency' => strtoupper($currency),
                        'exchange_rate' => $exchangeRate,
                        'discount_rate' => '0',
                    ],
                    $draftLines,
                );

                $actor = auth()->user();

                foreach (array_keys($receiptDocumentIds) as $receiptDocumentId) {
                    DocumentRelation::query()->create([
                        'source_document_id' => $receiptDocumentId,
                        'target_document_id' => $invoice->id,
                        'relation_type' => 'goods_receipt_to_supplier_invoice',
                        'created_by' => $actor?->id,
                        'created_by_name' => $actor?->name,
                    ]);
                }

                return $invoice->load('lines');
            },
        );
    }
}
