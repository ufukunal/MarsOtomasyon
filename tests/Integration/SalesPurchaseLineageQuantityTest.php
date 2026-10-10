<?php

use App\Actions\Documents\ResolveSourceLineage;
use App\Actions\Documents\SourceLineAvailability;
use App\Actions\Purchases\PurchaseLineAvailability;
use App\Models\Period\DocumentLine;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

function marsV4LineageDocument(string $type, string $status): int
{
    return (int) DB::connection('period')->table('documents')->insertGetId([
        'document_type' => $type,
        'document_date' => '2026-10-10',
        'status' => $status,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function marsV4LineageLine(int $documentId, string $quantity, ?int $sourceLine = null): DocumentLine
{
    $id = DB::connection('period')->table('document_lines')->insertGetId([
        'document_id' => $documentId,
        'line_no' => 1,
        'line_kind' => 'service',
        'quantity' => $quantity,
        'unit_price' => '10.0000',
        'line_total' => bcmul($quantity, '10', 4),
        'source_line_id' => $sourceLine,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return DocumentLine::query()->findOrFail($id);
}

it('subtracts dispatched and direct-invoiced quantities but never double-counts dispatch invoices', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $orderId = marsV4LineageDocument('sales_order', 'confirmed');
        $order = marsV4LineageLine($orderId, '10.000');

        $dispatch = marsV4LineageLine(marsV4LineageDocument('dispatch', 'posted'), '3.000', $order->id);
        $invoiceViaDispatch = marsV4LineageLine(
            marsV4LineageDocument('sales_invoice', 'posted'), '2.000', $dispatch->id,
        );
        $directInvoice = marsV4LineageLine(marsV4LineageDocument('sales_invoice', 'posted'), '4.000', $order->id);
        marsV4LineageLine(marsV4LineageDocument('sales_invoice', 'draft'), '1.000', $order->id);

        $availability = app(SourceLineAvailability::class);
        $lineage = app(ResolveSourceLineage::class)->handle($invoiceViaDispatch);

        expect($lineage['origin_order_line_id'])->toBe($order->id)
            ->and($lineage['dispatch_line_id'])->toBe($dispatch->id)
            ->and($lineage['has_dispatch'])->toBeTrue()
            ->and($availability->orderRemaining($order))->toBe('3.000')
            ->and($availability->dispatchRemaining($dispatch))->toBe('1.000');

        $reversalId = marsV4LineageDocument('sales_invoice', 'posted');
        DB::connection('period')->table('document_relations')->insert([
            'source_document_id' => $reversalId,
            'target_document_id' => $directInvoice->document_id,
            'relation_type' => 'reversal_of',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect($availability->orderRemaining($order))->toBe('7.000');
    });
});

it('calculates remaining purchase receipts and supplier invoicing separately, honoring reversals', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $purchase = marsV4LineageLine(marsV4LineageDocument('purchase_order', 'approved'), '10.000');
        $receipt = marsV4LineageLine(marsV4LineageDocument('goods_receipt', 'posted'), '6.000', $purchase->id);
        $invoice = marsV4LineageLine(marsV4LineageDocument('supplier_invoice', 'posted'), '2.000', $receipt->id);

        $availability = app(PurchaseLineAvailability::class);
        expect($availability->orderReceiptRemaining($purchase))->toBe('4.000')
            ->and($availability->receiptInvoiceRemaining($receipt))->toBe('4.000');

        $reversalId = marsV4LineageDocument('supplier_invoice', 'posted');
        DB::connection('period')->table('document_relations')->insert([
            'source_document_id' => $reversalId,
            'target_document_id' => $invoice->document_id,
            'relation_type' => 'reversal_of',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        expect($availability->receiptInvoiceRemaining($receipt))->toBe('6.000');
    });
});
