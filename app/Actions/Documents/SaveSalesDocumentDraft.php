<?php

namespace App\Actions\Documents;

use App\Enums\DocumentType;
use App\Enums\LocationKind;
use App\Models\Period\Contact;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Support\Period\PeriodContext;
use App\Support\Pricing\PriceDeviation;
use App\Support\Pricing\PriceResolver;
use App\Support\Units\UnitConversionResolver;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SaveSalesDocumentDraft
{
    public function __construct(
        private readonly CalculateDocumentTotals $calculator,
        private readonly UnitConversionResolver $units,
        private readonly PriceResolver $prices,
        private readonly PriceDeviation $deviation,
    ) {}

    /**
     * @param  array<string,mixed>  $header
     * @param  list<array<string,mixed>>  $lines
     */
    public function handle(
        DocumentType $type,
        array $header,
        array $lines,
        ?Document $document = null,
        ?int $expectedVersion = null,
    ): Document {
        PeriodContext::ensureWritable();

        if (! in_array($type, [
            DocumentType::Quote,
            DocumentType::SalesOrder,
            DocumentType::Dispatch,
            DocumentType::SalesInvoice,
        ], true)) {
            throw new DomainException('Bu belge tipi taslak satış belge editörüyle kaydedilemez.');
        }

        if ($document && $document->status !== 'draft') {
            throw new DomainException('Yalnız taslak belge düzenlenebilir.');
        }

        return DB::connection('period')->transaction(function () use (
            $type,
            $header,
            $lines,
            $document,
            $expectedVersion,
        ): Document {
            $contactId = isset($header['contact_id']) && $header['contact_id'] !== ''
                ? (int) $header['contact_id']
                : null;
            $contact = $contactId ? Contact::query()->findOrFail($contactId) : null;
            $normalized = [];
            $calcInput = [];

            foreach ($lines as $index => $line) {
                $kind = (string) ($line['line_kind'] ?? 'stock');
                $quantity = bcadd((string) ($line['quantity'] ?? '0'), '0', 3);

                if (! in_array($kind, ['stock', 'service'], true) || bccomp($quantity, '0', 3) <= 0) {
                    throw new DomainException('Belge satır türü veya miktarı geçersiz.');
                }

                $product = null;
                $unitId = null;
                $factor = null;
                $baseQuantity = null;
                $locationId = null;

                if ($kind === 'stock') {
                    $product = Product::query()->findOrFail((int) ($line['product_id'] ?? 0));
                    $unitId = (int) ($line['unit_id'] ?? $product->unit_id);
                    $factor = isset($line['conversion_factor'])
                        ? bcadd((string) $line['conversion_factor'], '0', 6)
                        : $this->units->factor($unitId, (int) $product->unit_id);
                    $baseQuantity = bcadd(bcmul($quantity, $factor, 6), '0', 3);
                    $locationId = isset($line['location_id']) && $line['location_id'] !== ''
                        ? (int) $line['location_id']
                        : null;

                    if ($locationId !== null) {
                        $location = Location::query()->where('is_active', true)->findOrFail($locationId);

                        if ($location->kind === LocationKind::Subcontractor) {
                            throw new DomainException('Fason lokasyon normal satış satırında kullanılamaz.');
                        }
                    }
                }

                $resolvedPrice = $product
                    ? $this->prices->resolve($product, $contact)
                    : '0.0000';
                $unitPrice = isset($line['unit_price']) && $line['unit_price'] !== ''
                    ? bcadd((string) $line['unit_price'], '0', 4)
                    : $resolvedPrice;

                if ($product) {
                    $this->deviation->warnIfNeeded($product, $resolvedPrice, $unitPrice);
                }

                $vatRate = isset($line['vat_rate'])
                    ? bcadd((string) $line['vat_rate'], '0', 4)
                    : bcadd($product === null ? '0' : (string) $product->vat_rate, '0', 4);

                $calcInput[] = [
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_discount_rate' => (string) ($line['line_discount_rate'] ?? '0'),
                    'line_discount_amount' => (string) ($line['line_discount_amount'] ?? '0'),
                    'vat_rate' => $vatRate,
                ];

                $normalized[] = [
                    'line_no' => $index + 1,
                    'line_kind' => $kind,
                    'product_id' => $product?->id,
                    'description' => trim((string) ($line['description'] ?? '')) ?: ($product === null ? null : $product->name),
                    'unit_id' => $unitId,
                    'quantity' => $quantity,
                    'conversion_factor' => $factor,
                    'base_quantity' => $baseQuantity,
                    'location_id' => $kind === 'service' ? null : $locationId,
                    'unit_price' => $unitPrice,
                    'vat_rate' => $vatRate,
                    'reserve_stock' => (bool) ($line['reserve_stock'] ?? false),
                    'cancelled_quantity' => '0.000',
                    'configuration' => $line['configuration'] ?? null,
                    'source_line_id' => isset($line['source_line_id']) ? (int) $line['source_line_id'] : null,
                ];
            }

            $totals = $this->calculator->handle(
                $calcInput,
                (string) ($header['discount_rate'] ?? '0'),
                (string) ($header['discount_amount'] ?? '0'),
            );

            $actor = auth()->user();
            $attributes = [
                'document_type' => $type->value,
                'document_date' => (string) ($header['document_date'] ?? now()->toDateString()),
                'due_date' => $header['due_date'] ?? null,
                'valid_until' => $header['valid_until'] ?? null,
                'contact_id' => $contactId,
                'currency' => 'TRY',
                'exchange_rate' => '1.000000',
                'discount_rate' => $totals->discountRate,
                'discount_amount' => $totals->discountAmount,
                'subtotal' => $totals->subtotal,
                'tax_base' => $totals->taxBase,
                'vat_amount' => $totals->vatAmount,
                'rounding_difference' => $totals->roundingDifference,
                'grand_total' => $totals->grandTotal,
                'requirements_snapshot' => $header['requirements_snapshot'] ?? null,
                'notes' => trim((string) ($header['notes'] ?? '')) ?: null,
            ];

            if ($document) {
                if ($document->document_type !== $type) {
                    throw new DomainException('Belge tipi kayıt sonrası değiştirilemez.');
                }

                $document = $document->updateWithVersion(
                    $attributes,
                    $expectedVersion ?? (int) $document->version,
                );
                $document->lines()->delete();
            } else {
                $document = Document::query()->create([
                    ...$attributes,
                    'revision_no' => $type === DocumentType::Quote ? 1 : 0,
                    'status' => 'draft',
                    'created_by' => $actor?->id,
                    'created_by_name' => $actor?->name,
                ]);
            }

            foreach ($normalized as $index => $line) {
                $calculation = $totals->lines[$index];

                DocumentLine::query()->create([
                    ...$line,
                    'document_id' => $document->id,
                    'line_discount_rate' => $calculation->discountRate,
                    'line_discount_amount' => $calculation->discountAmount,
                    'line_total' => $calculation->lineTotal,
                ]);
            }

            return $document->load('lines')->refresh();
        }, attempts: 3);
    }
}
