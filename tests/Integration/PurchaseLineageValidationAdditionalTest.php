<?php

use App\Actions\Purchases\ResolvePurchaseLineage;
use App\Models\Period\DocumentLine;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

function marsPurchaseChainLine(string $type, ?int $sourceId = null): DocumentLine
{
    $db = DB::connection('period');
    $documentId = $db->table('documents')->insertGetId([
        'document_type' => $type,
        'document_date' => '2026-10-10',
        'status' => $type === 'purchase_order' ? 'approved' : 'posted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $id = $db->table('document_lines')->insertGetId([
        'document_id' => $documentId,
        'line_no' => 1,
        'line_kind' => 'service',
        'quantity' => '3.000',
        'unit_price' => '20.0000',
        'line_total' => '60.0000',
        'source_line_id' => $sourceId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return DocumentLine::query()->findOrFail($id);
}

it('resolves the purchase order and goods receipt origin of an invoiced line', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $order = marsPurchaseChainLine('purchase_order');
        $receipt = marsPurchaseChainLine('goods_receipt', $order->id);
        $invoice = marsPurchaseChainLine('supplier_invoice', $receipt->id);

        $lineage = app(ResolvePurchaseLineage::class)->handle($invoice);

        expect($lineage['purchase_order_line_id'])->toBe($order->id)
            ->and($lineage['goods_receipt_line_id'])->toBe($receipt->id)
            ->and($lineage['line_ids'])->toBe([$receipt->id, $order->id]);
    });
});

it('reports a direct invoice with no purchase order provenance without inventing an ancestor', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $invoice = marsPurchaseChainLine('supplier_invoice');

        expect(app(ResolvePurchaseLineage::class)->handle($invoice))->toBe([
            'purchase_order_line_id' => null,
            'goods_receipt_line_id' => null,
            'line_ids' => [],
        ]);
    });
});
