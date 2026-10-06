<?php

namespace App\Actions\Returns;

use App\Actions\Documents\CalculateDocumentTotals;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\Period\DocumentRelation;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CreateReturnFromInvoice
{
    public function __construct(
        private readonly CalculateDocumentTotals $calculator,
        private readonly ReturnLineAvailability $availability,
    ) {}

    /**
     * @param  array<int,string>  $lineQuantities
     * @param  array<int,int>  $lineLocationIds
     */
    public function handle(
        Document $sourceInvoice,
        DocumentType $returnType,
        array $lineQuantities,
        array $lineLocationIds,
        string $documentDate,
        string $idempotencyKey,
        ?string $note = null,
    ): Document {
        MutationAuthorizer::authorize('returns.create');

        if (! in_array($returnType, [DocumentType::SalesReturn, DocumentType::PurchaseReturn], true)) {
            throw new DomainException('İade belge tipi geçersiz.');
        }

        $id = IdempotencyKey::run(
            $idempotencyKey,
            'return.create:'.$returnType->value.':'.$sourceInvoice->id,
            fn (): int => DB::connection('period')->transaction(function () use (
                $sourceInvoice,
                $returnType,
                $lineQuantities,
                $lineLocationIds,
                $documentDate,
                $note,
            ): int {
                $source = Document::query()->with('lines')->lockForUpdate()->findOrFail($sourceInvoice->id);
                $expectedSourceType = $returnType === DocumentType::SalesReturn
                    ? DocumentType::SalesInvoice
                    : DocumentType::SupplierInvoice;

                if ($source->document_type !== $expectedSourceType || $source->status !== 'posted') {
                    throw new DomainException('İade kaynağı kesinleşmiş uygun fatura olmalıdır.');
                }

                if ($returnType === DocumentType::SalesReturn && $source->currency !== 'TRY') {
                    throw new DomainException('Satış iadesi yalnız TRY satış faturasından oluşturulabilir.');
                }

                if (DocumentRelation::query()
                    ->where('relation_type', 'reversal_of')
                    ->where(fn ($query) => $query
                        ->where('target_document_id', $source->id)
                        ->orWhere('source_document_id', $source->id))
                    ->exists()) {
                    throw new DomainException('Ters kayıt zincirindeki fatura iade kaynağı olamaz.');
                }

                $rows = [];
                $calcInput = [];

                foreach ($lineQuantities as $lineId => $quantity) {
                    $sourceLine = $source->lines->firstWhere('id', (int) $lineId);

                    if (! $sourceLine) {
                        throw new DomainException('İade satırı kaynak faturaya ait değil.');
                    }

                    $normalized = bcadd((string) $quantity, '0', 3);
                    $remaining = $this->availability->remaining($sourceLine, $returnType);

                    if (bccomp($normalized, '0', 3) <= 0 || bccomp($normalized, $remaining, 3) > 0) {
                        throw new DomainException('İade miktarı kaynak fatura satırı kalanını aşıyor.');
                    }

                    $locationId = $sourceLine->location_id;

                    if ($sourceLine->line_kind === 'stock') {
                        $locationId = $lineLocationIds[(int) $sourceLine->id] ?? $locationId;

                        if ($locationId === null) {
                            throw new DomainException('Stok iade satırında lokasyon zorunludur.');
                        }
                    }

                    $calcInput[] = [
                        'quantity' => $normalized,
                        'unit_price' => (string) $sourceLine->unit_price,
                        'line_discount_rate' => (string) $sourceLine->line_discount_rate,
                        'line_discount_amount' => '0',
                        'vat_rate' => (string) $sourceLine->vat_rate,
                    ];

                    $rows[] = [
                        'source' => $sourceLine,
                        'quantity' => $normalized,
                        'location_id' => $locationId,
                    ];
                }

                if ($rows === []) {
                    throw new DomainException('İade edilecek satır seçilmedi.');
                }

                $totals = $this->calculator->handle(
                    $calcInput,
                    (string) $source->discount_rate,
                    '0',
                );
                $actor = auth()->user();

                $return = Document::query()->create([
                    'document_type' => $returnType->value,
                    'revision_no' => 0,
                    'document_date' => $documentDate,
                    'contact_id' => $source->contact_id,
                    'currency' => $source->currency,
                    'exchange_rate' => $source->exchange_rate,
                    'status' => 'draft',
                    'discount_rate' => $totals->discountRate,
                    'discount_amount' => $totals->discountAmount,
                    'subtotal' => $totals->subtotal,
                    'tax_base' => $totals->taxBase,
                    'vat_amount' => $totals->vatAmount,
                    'rounding_difference' => $totals->roundingDifference,
                    'grand_total' => $totals->grandTotal,
                    'notes' => $note,
                    'created_by' => $actor?->id,
                    'created_by_name' => $actor?->name,
                ]);

                foreach ($rows as $index => $row) {
                    $sourceLine = $row['source'];
                    $calculation = $totals->lines[$index];
                    $factor = $sourceLine->conversion_factor;
                    $baseQuantity = $sourceLine->line_kind === 'stock'
                        ? bcadd(bcmul($row['quantity'], (string) $factor, 6), '0', 3)
                        : null;

                    DocumentLine::query()->create([
                        'document_id' => $return->id,
                        'line_no' => $index + 1,
                        'line_kind' => $sourceLine->line_kind,
                        'product_id' => $sourceLine->product_id,
                        'description' => $sourceLine->description,
                        'unit_id' => $sourceLine->unit_id,
                        'quantity' => $row['quantity'],
                        'conversion_factor' => $factor,
                        'base_quantity' => $baseQuantity,
                        'location_id' => $row['location_id'],
                        'unit_price' => $sourceLine->unit_price,
                        'line_discount_rate' => $calculation->discountRate,
                        'line_discount_amount' => $calculation->discountAmount,
                        'vat_rate' => $sourceLine->vat_rate,
                        'line_total' => $calculation->lineTotal,
                        'reserve_stock' => false,
                        'cancelled_quantity' => '0.000',
                        'configuration' => $sourceLine->configuration,
                        'source_line_id' => $sourceLine->id,
                    ]);
                }

                DocumentRelation::query()->create([
                    'source_document_id' => $source->id,
                    'target_document_id' => $return->id,
                    'relation_type' => $returnType === DocumentType::SalesReturn
                        ? 'sales_invoice_to_return'
                        : 'supplier_invoice_to_return',
                    'created_by' => $actor?->id,
                    'created_by_name' => $actor?->name,
                ]);

                return (int) $return->id;
            }, attempts: 3),
        );

        return Document::query()->with('lines')->findOrFail((int) $id);
    }
}
