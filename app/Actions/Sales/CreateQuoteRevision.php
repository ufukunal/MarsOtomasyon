<?php

namespace App\Actions\Sales;

use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\Period\DocumentRelation;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CreateQuoteRevision
{
    public function handle(Document $quote, string $idempotencyKey): Document
    {
        MutationAuthorizer::authorize('quotes.update');

        return IdempotencyKey::run(
            $idempotencyKey,
            'quote.revision:'.$quote->id,
            function () use ($quote): Document {
                if ($quote->document_type !== DocumentType::Quote || $quote->number === null) {
                    throw new DomainException('Numarasız teklif için revizyon oluşturulamaz.');
                }

                return DB::connection('period')->transaction(function () use ($quote): Document {
                    Document::query()
                        ->where('document_type', DocumentType::Quote->value)
                        ->where('number', $quote->number)
                        ->where('revision_no', 1)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $latest = Document::query()
                        ->with('lines')
                        ->where('document_type', DocumentType::Quote->value)
                        ->where('number', $quote->number)
                        ->orderByDesc('revision_no')
                        ->firstOrFail();

                    if ((int) $latest->id !== (int) $quote->id) {
                        throw new DomainException('Eski teklif revizyonundan yeni branch oluşturulamaz.');
                    }

                    $actor = auth()->user();
                    $revision = Document::query()->create([
                        'document_type' => DocumentType::Quote->value,
                        'number' => $latest->number,
                        'revision_no' => (int) $latest->revision_no + 1,
                        'document_date' => $latest->document_date,
                        'due_date' => $latest->due_date,
                        'valid_until' => $latest->valid_until,
                        'contact_id' => $latest->contact_id,
                        'currency' => $latest->currency,
                        'exchange_rate' => $latest->exchange_rate,
                        'status' => 'draft',
                        'discount_rate' => $latest->discount_rate,
                        'discount_amount' => $latest->discount_amount,
                        'subtotal' => $latest->subtotal,
                        'tax_base' => $latest->tax_base,
                        'vat_amount' => $latest->vat_amount,
                        'rounding_difference' => $latest->rounding_difference,
                        'grand_total' => $latest->grand_total,
                        'requirements_snapshot' => $latest->requirements_snapshot,
                        'notes' => $latest->notes,
                        'created_by' => $actor?->id,
                        'created_by_name' => $actor?->name,
                    ]);

                    foreach ($latest->lines as $line) {
                        DocumentLine::query()->create([
                            ...$line->only([
                                'line_no', 'line_kind', 'product_id', 'description', 'unit_id', 'quantity',
                                'conversion_factor', 'base_quantity', 'location_id', 'unit_price',
                                'line_discount_rate', 'line_discount_amount', 'vat_rate', 'line_total',
                                'reserve_stock', 'cancelled_quantity', 'configuration', 'source_line_id',
                            ]),
                            'document_id' => $revision->id,
                        ]);
                    }

                    DocumentRelation::query()->create([
                        'source_document_id' => $revision->id,
                        'target_document_id' => $latest->id,
                        'relation_type' => 'revision_of',
                        'created_by' => $actor?->id,
                        'created_by_name' => $actor?->name,
                    ]);

                    AuditContext::period(
                        'Teklif revizyonu oluşturuldu.',
                        ['source_quote_id' => $latest->id, 'revision_id' => $revision->id],
                        $revision,
                        'quote_revision_created',
                    );

                    return $revision->load('lines');
                }, attempts: 3);
            },
        );
    }
}
