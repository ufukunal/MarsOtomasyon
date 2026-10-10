<?php

use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

it('refuses in-place edits and deletion of posted sales invoices', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $id = DB::connection('period')->table('documents')->insertGetId([
            'document_type' => 'sales_invoice',
            'document_date' => '2026-10-10',
            'status' => 'posted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $document = Document::query()->findOrFail($id);
        $document->notes = 'tampered';

        expect(fn () => $document->save())->toThrow(LogicException::class);
        expect(fn () => $document->delete())->toThrow(LogicException::class);
        expect(DB::connection('period')->table('documents')->where('id', $id)->value('notes'))->toBeNull();
    });
});

it('blocks creating new invoice lines against a posted invoice', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $id = DB::connection('period')->table('documents')->insertGetId([
            'document_type' => 'sales_invoice',
            'document_date' => '2026-10-10',
            'status' => 'posted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DocumentLine::query()->create([
            'document_id' => $id,
            'line_no' => 1,
            'line_kind' => 'service',
            'quantity' => '1.000',
            'unit_price' => '10.0000',
            'line_total' => '10.0000',
        ]))->toThrow(LogicException::class);

        expect(DB::connection('period')->table('document_lines')->where('document_id', $id)->count())->toBe(0);
    });
});
