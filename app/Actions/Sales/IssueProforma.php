<?php

namespace App\Actions\Sales;

use App\Actions\Periods\EnsurePeriodOpen;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\Period\DocumentRelation;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use Carbon\CarbonImmutable;
use DomainException;

final class IssueProforma
{
    public function __construct(
        private readonly EnsurePeriodOpen $ensurePeriodOpen,
    ) {}

    public function handle(
        Document $source,
        string $documentDate,
        string $idempotencyKey,
    ): Document {
        MutationAuthorizer::authorize('proformas.create');

        return IdempotencyKey::run(
            $idempotencyKey,
            'proforma.issue:'.$source->id,
            function () use ($source, $documentDate): Document {
                $locked = Document::query()->with('lines')->lockForUpdate()->findOrFail($source->id);

                $allowed = ($locked->document_type === DocumentType::Quote && $locked->status === 'approved')
                    || ($locked->document_type === DocumentType::SalesOrder && $locked->status === 'confirmed');

                if (! $allowed) {
                    throw new DomainException('Proforma yalnız onaylı teklif veya satış siparişinden üretilebilir.');
                }

                $date = CarbonImmutable::parse($documentDate);
                $this->ensurePeriodOpen->handle($date);
                $actor = auth()->user();

                $proforma = Document::query()->create([
                    'document_type' => DocumentType::Proforma->value,
                    'number' => null,
                    'revision_no' => 0,
                    'document_date' => $date->toDateString(),
                    'due_date' => $locked->due_date,
                    'valid_until' => $locked->valid_until,
                    'contact_id' => $locked->contact_id,
                    'currency' => 'TRY',
                    'exchange_rate' => '1.000000',
                    'status' => 'draft',
                    'discount_rate' => $locked->discount_rate,
                    'discount_amount' => $locked->discount_amount,
                    'subtotal' => $locked->subtotal,
                    'tax_base' => $locked->tax_base,
                    'vat_amount' => $locked->vat_amount,
                    'rounding_difference' => $locked->rounding_difference,
                    'grand_total' => $locked->grand_total,
                    'requirements_snapshot' => $locked->requirements_snapshot,
                    'notes' => $locked->notes,
                    'created_by' => $actor?->id,
                    'created_by_name' => $actor?->name,
                    'posted_by' => $actor?->id,
                    'posted_by_name' => $actor?->name,
                    'posted_at' => now(),
                ]);

                foreach ($locked->lines as $line) {
                    DocumentLine::query()->create([
                        ...$line->only([
                            'line_no', 'line_kind', 'product_id', 'description', 'unit_id', 'quantity',
                            'conversion_factor', 'base_quantity', 'location_id', 'unit_price',
                            'line_discount_rate', 'line_discount_amount', 'vat_rate', 'line_total',
                            'reserve_stock', 'configuration',
                        ]),
                        'document_id' => $proforma->id,
                        'cancelled_quantity' => '0.000',
                        'source_line_id' => $line->id,
                    ]);
                }

                DocumentRelation::query()->create([
                    'source_document_id' => $locked->id,
                    'target_document_id' => $proforma->id,
                    'relation_type' => $locked->document_type === DocumentType::Quote
                        ? 'quote_to_proforma'
                        : 'order_to_proforma',
                    'created_by' => $actor?->id,
                    'created_by_name' => $actor?->name,
                ]);

                $proforma->status = 'posted';
                $proforma->save();

                AuditContext::period(
                    'Proforma oluşturuldu.',
                    ['source_document_id' => $locked->id, 'proforma_id' => $proforma->id],
                    $proforma,
                    'proforma_issued',
                );

                return $proforma->load('lines');
            },
        );
    }
}
