<?php

use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;

beforeEach(function () {
    $this->createCompanyWithPeriod('V2DOCIMM');
});

it('v2 posted document cannot be edited via Eloquent', function () {
    $document = Document::query()->create([
        'document_type' => DocumentType::SalesInvoice->value,
        'document_date' => '2026-09-01', 'status' => 'posted',
    ]);
    expect(fn () => $document->update(['notes' => 'unauthorized after posting']))
        ->toThrow(LogicException::class);
    expect(Document::query()->findOrFail($document->id)->notes)->toBeNull();
});

it('v2 posted document cannot be deleted', function () {
    $document = Document::query()->create([
        'document_type' => DocumentType::SalesInvoice->value,
        'document_date' => '2026-09-01', 'status' => 'posted',
    ]);
    expect(fn () => $document->delete())->toThrow(LogicException::class);
    expect(Document::query()->whereKey($document->id)->exists())->toBeTrue();
});

it('v2 unposted draft remains editable and can be deleted', function () {
    $document = Document::query()->create([
        'document_type' => DocumentType::SalesOrder->value,
        'document_date' => '2026-09-01', 'status' => 'draft',
    ]);
    $document->update(['notes' => 'editable draft']);
    expect($document->fresh()->notes)->toBe('editable draft');
    $document->delete();
    expect(Document::query()->whereKey($document->id)->exists())->toBeFalse();
});

it('v2 posted document refuses insertion of additional lines', function () {
    $document = Document::query()->create([
        'document_type' => DocumentType::SalesInvoice->value,
        'document_date' => '2026-09-01', 'status' => 'posted',
    ]);
    expect(fn () => DocumentLine::query()->create([
        'document_id' => $document->id, 'line_no' => 1,
        'line_kind' => 'service', 'quantity' => '1.000',
        'unit_price' => '10.0000', 'line_total' => '10.0000',
    ]))->toThrow(LogicException::class);
    expect(DocumentLine::query()->where('document_id', $document->id)->count())->toBe(0);
});