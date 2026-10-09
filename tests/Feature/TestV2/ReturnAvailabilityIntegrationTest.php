<?php

use App\Actions\Returns\ReturnLineAvailability;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\Period\DocumentRelation;

/**
 * Fixture intentionally models the posted state through the actual Document Eloquent model,
 * not direct SQL mocks. Source, return and reversal records stay inside an isolated period DB.
 */
function v2ReturnDocument(DocumentType $type, string $status = 'draft'): Document
{
    $document = Document::query()->create([
        'document_type' => $type->value,
        'document_date' => '2026-09-15',
        'currency' => 'TRY',
        'status' => 'draft',
        'exchange_rate' => '1',
        'grand_total' => '0',
    ]);

    if ($status === 'posted') {
        $document->update(['status' => 'posted']);
    }

    return $document;
}

function v2ReturnServiceLine(Document $document, string $quantity, ?int $sourceLineId = null): DocumentLine
{
    return DocumentLine::query()->create([
        'document_id' => $document->id,
        'line_no' => 1,
        'line_kind' => 'service',
        'description' => 'V2 service return fixture',
        'quantity' => $quantity,
        'unit_price' => '0.0000',
        'line_total' => '0.0000',
        'vat_rate' => '0',
        'source_line_id' => $sourceLineId,
    ]);
}

it('v2 return remaining quantity counts only posted returns linked to the source line', function () {
    $this->createCompanyWithPeriod('V2RETQTY');

    $source = v2ReturnDocument(DocumentType::SalesInvoice);
    $sourceLine = v2ReturnServiceLine($source, '8.000');
    $source->update(['status' => 'posted']);

    $posted = v2ReturnDocument(DocumentType::SalesReturn);
    v2ReturnServiceLine($posted, '2.500', $sourceLine->id);
    $posted->update(['status' => 'posted']);

    $draft = v2ReturnDocument(DocumentType::SalesReturn);
    v2ReturnServiceLine($draft, '4.000', $sourceLine->id);

    expect(app(ReturnLineAvailability::class)->remaining($sourceLine, DocumentType::SalesReturn))
        ->toBe('5.500');
});

it('v2 return remaining quantity disregards reversed return documents', function () {
    $this->createCompanyWithPeriod('V2RETREV');

    $source = v2ReturnDocument(DocumentType::SupplierInvoice);
    $sourceLine = v2ReturnServiceLine($source, '7.000');
    $source->update(['status' => 'posted']);

    $returned = v2ReturnDocument(DocumentType::PurchaseReturn);
    v2ReturnServiceLine($returned, '3.000', $sourceLine->id);
    $returned->update(['status' => 'posted']);

    $service = app(ReturnLineAvailability::class);
    expect($service->remaining($sourceLine, DocumentType::PurchaseReturn))->toBe('4.000');

    $reversal = v2ReturnDocument(DocumentType::PurchaseReturn, 'posted');
    DocumentRelation::query()->create([
        'source_document_id' => $reversal->id,
        'target_document_id' => $returned->id,
        'relation_type' => 'reversal_of',
    ]);

    expect($service->remaining($sourceLine, DocumentType::PurchaseReturn))
        ->toBe('7.000');
});
