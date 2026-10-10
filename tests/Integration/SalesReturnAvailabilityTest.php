<?php

use App\Actions\Returns\ReturnLineAvailability;
use App\Enums\DocumentType;
use App\Models\Period\DocumentLine;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

function marsReturnDocument(string $type, string $status): int
{
    return (int) DB::connection('period')->table('documents')->insertGetId([
        'document_type' => $type,
        'document_date' => '2026-10-10',
        'status' => $status,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function marsReturnLine(int $documentId, int $lineNo, string $quantity, ?int $sourceId = null): int
{
    return (int) DB::connection('period')->table('document_lines')->insertGetId([
        'document_id' => $documentId,
        'line_no' => $lineNo,
        'line_kind' => 'service',
        'quantity' => $quantity,
        'unit_price' => '10.0000',
        'line_total' => bcmul($quantity, '10', 4),
        'source_line_id' => $sourceId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('reduces returnable sales invoice quantity only for posted unreversed returns', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $source = marsReturnDocument('sales_invoice', 'posted');
        $sourceLine = marsReturnLine($source, 1, '5.000');

        $posted = marsReturnDocument('sales_return', 'posted');
        marsReturnLine($posted, 1, '2.000', $sourceLine);

        $draft = marsReturnDocument('sales_return', 'draft');
        marsReturnLine($draft, 1, '1.000', $sourceLine);

        $remaining = (new ReturnLineAvailability)->remaining(
            DocumentLine::query()->findOrFail($sourceLine),
            DocumentType::SalesReturn,
        );
        expect($remaining)->toBe('3.000');

        $reversal = marsReturnDocument('sales_return', 'draft');
        DB::connection('period')->table('document_relations')->insert([
            'source_document_id' => $reversal,
            'target_document_id' => $posted,
            'relation_type' => 'reversal_of',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect((new ReturnLineAvailability)->remaining(
            DocumentLine::query()->findOrFail($sourceLine),
            DocumentType::SalesReturn,
        ))->toBe('5.000');
    });
});

it('does not mix sales and purchase return categories', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $source = marsReturnDocument('supplier_invoice', 'posted');
        $sourceLine = marsReturnLine($source, 1, '6.000');
        $purchaseReturn = marsReturnDocument('purchase_return', 'posted');
        marsReturnLine($purchaseReturn, 1, '4.000', $sourceLine);

        $line = DocumentLine::query()->findOrFail($sourceLine);
        expect((new ReturnLineAvailability)->remaining($line, DocumentType::PurchaseReturn))
            ->toBe('2.000');
        expect((new ReturnLineAvailability)->remaining($line, DocumentType::SalesReturn))
            ->toBe('6.000');
    });
});
