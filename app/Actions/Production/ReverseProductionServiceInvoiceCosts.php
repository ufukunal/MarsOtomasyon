<?php

namespace App\Actions\Production;

use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\ProductionServiceAllocation;
use App\Models\Period\ProductionServiceInvoice;
use Illuminate\Support\Facades\DB;

final class ReverseProductionServiceInvoiceCosts
{
    public function __construct(private readonly ApplyInventoryCostAdjustment $adjustment) {}

    public function handle(Document $invoice, string $adjustmentDate): void
    {
        if ($invoice->document_type !== DocumentType::SupplierInvoice) {
            return;
        }

        $allocations = ProductionServiceAllocation::query()
            ->with('completion.order')
            ->where('purchase_invoice_id', $invoice->id)
            ->orderBy('production_completion_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($allocations as $allocation) {
            $applied = bcadd((string) $allocation->applied_amount_base, '0', 4);

            if (bccomp($applied, '0', 4) !== 0) {
                $this->adjustment->handle(
                    (int) $allocation->completion->order->product_id,
                    (int) $allocation->production_completion_id,
                    (int) $allocation->id,
                    $adjustmentDate,
                    (string) $allocation->quantity_basis,
                    bcmul($applied, '-1', 4),
                );
            }

            $allocation->allocated_amount_base = '0.0000';
            $allocation->applied_amount_base = '0.0000';
            $allocation->save();
        }

        DB::connection('period')->table('production_service_invoices')
            ->where('purchase_invoice_id', $invoice->id)
            ->delete();
    }
}
