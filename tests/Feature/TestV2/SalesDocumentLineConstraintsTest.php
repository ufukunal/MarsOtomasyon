<?php

use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->createCompanyWithPeriod('V2DOCCHECK');
});

function v2DraftDocument(): Document
{
    return Document::query()->create([
        'document_type' => DocumentType::SalesInvoice->value,
        'document_date' => '2026-09-01',
        'status' => 'draft',
        'currency' => 'TRY',
        'exchange_rate' => '1.000000',
        'grand_total' => '0.0000',
    ]);
}

it('v2 document line rejects zero quantities at PostgreSQL constraint layer', function () {
    $document = v2DraftDocument();
    expect(fn () => DocumentLine::query()->create([
        'document_id' => $document->id,
        'line_no' => 1,
        'line_kind' => 'service',
        'description' => 'Invalid zero service',
        'quantity' => '0.000',
        'unit_price' => '10.0000',
        'line_total' => '0.0000',
    ]))->toThrow(QueryException::class);
    expect($document->lines()->count())->toBe(0);
});

it('v2 document line rejects an invalid line kind at PostgreSQL constraint layer', function () {
    $document = v2DraftDocument();
    expect(fn () => DocumentLine::query()->create([
        'document_id' => $document->id,
        'line_no' => 1,
        'line_kind' => 'unsupported-kind',
        'description' => 'Invalid kind',
        'quantity' => '1.000',
        'unit_price' => '10.0000',
        'line_total' => '10.0000',
    ]))->toThrow(QueryException::class);
    expect($document->lines()->count())->toBe(0);
});

it('v2 document line rejects an unbound physical stock line shape', function () {
    $document = v2DraftDocument();
    expect(fn () => DocumentLine::query()->create([
        'document_id' => $document->id,
        'line_no' => 1,
        'line_kind' => 'stock',
        'description' => 'Stock with missing unit and product',
        'quantity' => '1.000',
        'unit_price' => '10.0000',
        'line_total' => '10.0000',
    ]))->toThrow(QueryException::class);
    expect($document->lines()->count())->toBe(0);
});

it('v2 document line rejects cancelled quantity beyond source quantity', function () {
    $document = v2DraftDocument();
    expect(fn () => DocumentLine::query()->create([
        'document_id' => $document->id,
        'line_no' => 1,
        'line_kind' => 'service',
        'description' => 'Over cancelled',
        'quantity' => '1.000',
        'unit_price' => '10.0000',
        'line_total' => '10.0000',
        'cancelled_quantity' => '2.000',
    ]))->toThrow(QueryException::class);
    expect($document->lines()->count())->toBe(0);
});

it('v2 document lines do not allow writing to a posted document', function () {
    $document = v2DraftDocument();
    $document->update(['status' => 'posted']);
    expect(fn () => DocumentLine::query()->create([
        'document_id' => $document->id,
        'line_no' => 1,
        'line_kind' => 'service',
        'description' => 'Posted immutable',
        'quantity' => '1.000',
        'unit_price' => '10.0000',
        'line_total' => '10.0000',
    ]))->toThrow(LogicException::class);
    expect($document->lines()->count())->toBe(0);
});