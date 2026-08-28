<?php

namespace App\Modules\SupplierInvoices\Actions;

use App\Modules\PurchaseOrders\Models\PurchaseOrder;
use App\Modules\PurchaseOrders\Models\PurchaseOrderLine;
use App\Modules\SupplierInvoices\Models\SupplierInvoiceLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SupplierInvoiceThreeWayMatchGuard
{
    /** @param Collection<int, SupplierInvoiceLine> $lines */
    public function assertInvoiceable(int $companyId, Collection $lines): void
    {
        /** @var SupplierInvoiceLine $line */
        foreach ($lines as $line) {
            $sourceLine = PurchaseOrderLine::query()
                ->where('company_id', $companyId)
                ->whereKey($line->purchase_order_line_id)
                ->lockForUpdate()
                ->first();

            if (! $sourceLine instanceof PurchaseOrderLine) {
                throw ValidationException::withMessages([
                    'lines' => 'Alış faturası kaynak satınalma siparişi satırı bulunamadı.',
                ]);
            }

            $order = PurchaseOrder::query()
                ->where('company_id', $companyId)
                ->whereKey($sourceLine->purchase_order_id)
                ->lockForUpdate()
                ->first();
            if (! $order instanceof PurchaseOrder || ! $order->isOpen()) {
                throw ValidationException::withMessages([
                    'lines' => 'Alış faturası yalnız açık satınalma siparişi üzerinden kesinleştirilebilir.',
                ]);
            }

            $remaining = $this->remainingReceivedToInvoice($companyId, (int) $sourceLine->getKey());

            if ($this->greaterThan((string) $line->quantity, $remaining)) {
                throw ValidationException::withMessages([
                    'quantity_delta' => sprintf(
                        '3-way match engeli: %s satırında faturalanacak %s miktar için yalnız %s net kabul edilmiş ve henüz faturalanmamış miktar müsait.',
                        (string) $line->product_code,
                        (string) $line->quantity,
                        $remaining,
                    ),
                ]);
            }
        }
    }

    private function remainingReceivedToInvoice(int $companyId, int $purchaseOrderLineId): string
    {
        $row = DB::table('purchase_order_line_progress')
            ->where('company_id', $companyId)
            ->where('purchase_order_line_id', $purchaseOrderLineId)
            ->selectRaw(
                'GREATEST(CAST(net_received_quantity AS numeric) - CAST(net_invoiced_quantity AS numeric), 0)::numeric(20,6)::text AS quantity'
            )
            ->first();

        return $row === null ? '0.000000' : (string) $row->quantity;
    }

    private function greaterThan(string $left, string $right): bool
    {
        $row = DB::selectOne('SELECT CAST(? AS numeric) > CAST(? AS numeric) AS value', [$left, $right]);

        return $row?->value === true;
    }
}
