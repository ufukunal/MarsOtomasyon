<?php

use App\Actions\Sales\AllocateOrderLineLocations;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

it('maps non-stock sales service quantities without allocating physical warehouse stock', function (): void {
    $order = new Document(['document_type' => 'sales_order', 'status' => 'confirmed']);
    $line = new DocumentLine(['line_kind' => 'service', 'quantity' => '3.500']);
    $line->setRelation('document', $order);

    expect(app(AllocateOrderLineLocations::class)->handle($line, '2.250'))->toBe([
        ['quantity' => '2.250', 'location_id' => 0],
    ]);
});

it('blocks using a supplier invoice line as an outbound sales order allocation', function (): void {
    $document = new Document(['document_type' => 'supplier_invoice']);
    $line = new DocumentLine(['line_kind' => 'stock', 'quantity' => '1.000']);
    $line->setRelation('document', $document);

    expect(fn () => app(AllocateOrderLineLocations::class)->handle($line, '1.000'))
        ->toThrow(DomainException::class);
});

it('requires an explicit fallback depot for unreserved order stock and never overallocates', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $ids = IsolatedPostgres::productAndLocation();
        $db = DB::connection('period');

        $documentId = $db->table('documents')->insertGetId([
            'document_type' => 'sales_order',
            'document_date' => '2026-10-10',
            'status' => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lineId = $db->table('document_lines')->insertGetId([
            'document_id' => $documentId,
            'line_no' => 1,
            'line_kind' => 'stock',
            'product_id' => $ids['product'],
            'unit_id' => $ids['unit'],
            'location_id' => $ids['location'],
            'quantity' => '5.000',
            'base_quantity' => '5.000',
            'conversion_factor' => '1.000000',
            'unit_price' => '10.0000',
            'line_total' => '50.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $line = DocumentLine::query()->findOrFail($lineId);
        $allocate = app(AllocateOrderLineLocations::class);

        expect(fn () => $allocate->handle($line, '2.000'))->toThrow(DomainException::class);
        expect($allocate->handle($line, '2.000', $ids['location']))->toBe([
            ['quantity' => '2.000', 'location_id' => $ids['location']],
        ]);
        expect(fn () => $allocate->handle($line, '5.001', $ids['location']))
            ->toThrow(DomainException::class);
    });
});
