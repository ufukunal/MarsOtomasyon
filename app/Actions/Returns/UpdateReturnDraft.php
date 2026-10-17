<?php

namespace App\Actions\Returns;

use App\Actions\Documents\CalculateDocumentTotals;
use App\Actions\Periods\EnsurePeriodOpen;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\Period\DocumentRelation;
use App\Models\Period\Location;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use App\Support\Documents\ProrateFixedDiscount;
use App\Support\Period\PeriodContext;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Edit unposted return lines without losing the invoice lineage or creating
 * stock/cari movements. Posted returns still require their reversal action.
 */
final class UpdateReturnDraft
{
    public function __construct(
        private readonly CalculateDocumentTotals $calculator,
        private readonly ReturnLineAvailability $availability,
        private readonly EnsurePeriodOpen $period,
        private readonly ProrateFixedDiscount $proration,
    ) {}

    /**
     * @param array<int,string> $quantities Invoice-line ID => requested quantity.
     * @param array<int,int> $locations Invoice-line ID => stock location ID.
     */
    public function handle(
        Document $return,
        array $quantities,
        array $locations,
        string $date,
        ?string $note,
        int $expectedVersion,
        string $key,
    ): Document {
        MutationAuthorizer::authorize('returns.update');
        PeriodContext::ensureWritable();

        $id = IdempotencyKey::run(
            $key,
            'return.draft-edit:'.$return->id,
            function () use ($return, $quantities, $locations, $date, $note, $expectedVersion): int {
                return DB::connection('period')->transaction(function () use (
                    $return, $quantities, $locations, $date, $note, $expectedVersion,
                ): int {
                    $locked = Document::query()->with(['lines', 'incomingRelations'])
                        ->lockForUpdate()->findOrFail($return->id);

                    if (! in_array($locked->document_type, [
                        DocumentType::SalesReturn, DocumentType::PurchaseReturn,
                    ], true) || $locked->status !== 'draft') {
                        throw new DomainException('Yalnız taslak iade belgesi düzenlenebilir.');
                    }
                    if ((int) $locked->version !== $expectedVersion) {
                        throw new DomainException('İade başka oturumda değiştirildi; tekrar açın.');
                    }

                    $relationType = $locked->document_type === DocumentType::SalesReturn
                        ? 'sales_invoice_to_return' : 'supplier_invoice_to_return';
                    $relation = $locked->incomingRelations->firstWhere('relation_type', $relationType);
                    $source = $relation
                        ? Document::query()->with('lines')->lockForUpdate()->findOrFail($relation->source_document_id)
                        : null;
                    $sourceType = $locked->document_type === DocumentType::SalesReturn
                        ? DocumentType::SalesInvoice : DocumentType::SupplierInvoice;

                    if ($source === null || $source->document_type !== $sourceType
                        || $source->status !== 'posted'
                        || (int) $source->contact_id !== (int) $locked->contact_id
                        || $source->currency !== $locked->currency
                        || DocumentRelation::query()->where('relation_type', 'reversal_of')
                            ->where(fn ($q) => $q->where('target_document_id', $source->id)
                                ->orWhere('source_document_id', $source->id))->exists()) {
                        throw new DomainException('İade kaynak faturası değişmiş veya artık geçerli değil.');
                    }

                    $date = CarbonImmutable::parse($date);
                    $this->period->handle($date);

                    $rows = [];
                    $calculationRows = [];
                    foreach ($quantities as $lineId => $quantity) {
                        if (! preg_match('/^\d{1,14}(?:\.\d{1,3})?$/D', (string) $quantity)) {
                            throw new DomainException('İade miktarı geçersiz.');
                        }
                        if (bccomp((string) $quantity, '0', 3) <= 0) {
                            continue;
                        }

                        $line = $source->lines->firstWhere('id', (int) $lineId);
                        if ($line === null) {
                            throw new DomainException('İade satırı kaynak faturaya ait değil.');
                        }

                        if (bccomp((string) $quantity,
                            $this->availability->remaining($line, $locked->document_type), 3) > 0) {
                            throw new DomainException('İade miktarı kalan kaynak miktarı aşamaz.');
                        }

                        $location = $line->line_kind === 'stock'
                            ? ($locations[(int) $lineId] ?? $line->location_id)
                            : null;
                        if ($line->line_kind === 'stock') {
                            if ($location === null || ! Location::query()
                                ->where('is_active', true)->whereKey((int) $location)->exists()) {
                                throw new DomainException('İade satırı için aktif lokasyon zorunludur.');
                            }
                        }

                        $calculationRows[] = [
                            'quantity' => (string) $quantity,
                            'unit_price' => (string) $line->unit_price,
                            'line_discount_rate' => (string) $line->line_discount_rate,
                            'line_discount_amount' => '0',
                            'vat_rate' => (string) $line->vat_rate,
                        ];
                        $rows[] = ['source' => $line, 'quantity' => (string) $quantity,
                            'location_id' => $location];
                    }

                    if ($rows === []) {
                        throw new DomainException('İade taslağında en az bir pozitif miktarlı satır zorunludur.');
                    }

                    $additional = $this->proration->handle($source, $calculationRows);
                    $totals = $this->calculator->handle(
                        $calculationRows, (string) $source->discount_rate, '0', $additional,
                    );

                    $locked = $locked->updateWithVersion([
                        'document_date' => $date->toDateString(),
                        'notes' => trim((string) $note) ?: null,
                        'discount_rate' => $totals->discountRate,
                        'discount_amount' => $totals->discountAmount,
                        'additional_discount_amount' => $additional,
                        'subtotal' => $totals->subtotal,
                        'tax_base' => $totals->taxBase,
                        'vat_amount' => $totals->vatAmount,
                        'rounding_difference' => $totals->roundingDifference,
                        'grand_total' => $totals->grandTotal,
                    ], $expectedVersion);

                    $locked->lines()->delete();
                    foreach ($rows as $index => $row) {
                        $line = $row['source'];
                        $calc = $totals->lines[$index];
                        DocumentLine::query()->create([
                            'document_id' => $locked->id,
                            'line_no' => $index + 1,
                            'line_kind' => $line->line_kind,
                            'product_id' => $line->product_id,
                            'description' => $line->description,
                            'unit_id' => $line->unit_id,
                            'quantity' => $row['quantity'],
                            'conversion_factor' => $line->conversion_factor,
                            'base_quantity' => $line->line_kind === 'stock'
                                ? bcadd(bcmul($row['quantity'], (string) $line->conversion_factor, 6), '0', 3)
                                : null,
                            'location_id' => $row['location_id'],
                            'unit_price' => $line->unit_price,
                            'line_discount_rate' => $calc->discountRate,
                            'line_discount_amount' => $calc->discountAmount,
                            'vat_rate' => $line->vat_rate,
                            'line_total' => $calc->lineTotal,
                            'reserve_stock' => false,
                            'cancelled_quantity' => '0.000',
                            'configuration' => $line->configuration,
                            'source_line_id' => $line->id,
                        ]);
                    }

                    AuditContext::period('İade taslağı düzenlendi.', [
                        'return_id' => $locked->id,
                        'source_invoice_id' => $source->id,
                        'line_count' => count($rows),
                    ], $locked, 'return_draft_updated');

                    return (int) $locked->id;
                }, attempts: 3);
            },
        );

        return Document::query()->with('lines')->findOrFail((int) $id);
    }
}
