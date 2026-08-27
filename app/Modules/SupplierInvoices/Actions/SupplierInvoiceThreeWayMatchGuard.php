<?php

namespace App\Modules\SupplierInvoices\Actions;

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

            $accepted = $this->acceptedQuantity($companyId, (int) $sourceLine->getKey());
            $returned = $this->returnedQuantity($companyId, (int) $sourceLine->getKey());
            $invoiced = $this->invoicedQuantity($companyId, (int) $sourceLine->getKey());
            $remaining = $this->remainingAcceptedToInvoice($accepted, $returned, $invoiced);

            if ($this->greaterThan((string) $line->quantity, $remaining)) {
                throw ValidationException::withMessages([
                    'quantity_delta' => sprintf(
                        '3-way match engeli: %s satırında faturalanacak %s miktar için yalnız %s net accepted miktar müsait.',
                        (string) $line->product_code,
                        (string) $line->quantity,
                        $remaining,
                    ),
                ]);
            }
        }
    }

    private function acceptedQuantity(int $companyId, int $purchaseOrderLineId): string
    {
        $row = DB::table('goods_receipt_line_quality as quality')
            ->join('goods_receipt_lines as line', function ($join): void {
                $join->on('line.company_id', '=', 'quality.company_id')
                    ->on('line.goods_receipt_id', '=', 'quality.goods_receipt_id')
                    ->on('line.id', '=', 'quality.goods_receipt_line_id');
            })
            ->join('goods_receipts as receipt', function ($join): void {
                $join->on('receipt.company_id', '=', 'line.company_id')
                    ->on('receipt.id', '=', 'line.goods_receipt_id');
            })
            ->where('line.company_id', $companyId)
            ->where('line.purchase_order_line_id', $purchaseOrderLineId)
            ->where('receipt.status', 'finalized')
            ->selectRaw('COALESCE(SUM(quality.accepted_quantity), 0)::numeric(20,6)::text AS quantity')
            ->first();

        return $row === null ? '0.000000' : (string) $row->quantity;
    }

    private function returnedQuantity(int $companyId, int $purchaseOrderLineId): string
    {
        $row = DB::table('purchase_return_lines as line')
            ->join('purchase_returns as purchase_return', function ($join): void {
                $join->on('purchase_return.company_id', '=', 'line.company_id')
                    ->on('purchase_return.id', '=', 'line.purchase_return_id');
            })
            ->where('line.company_id', $companyId)
            ->where('line.purchase_order_line_id', $purchaseOrderLineId)
            ->where('purchase_return.status', 'finalized')
            ->selectRaw('COALESCE(SUM(line.quantity), 0)::numeric(20,6)::text AS quantity')
            ->first();

        return $row === null ? '0.000000' : (string) $row->quantity;
    }

    private function invoicedQuantity(int $companyId, int $purchaseOrderLineId): string
    {
        $value = DB::table('purchase_order_line_progress')
            ->where('company_id', $companyId)
            ->where('purchase_order_line_id', $purchaseOrderLineId)
            ->value('net_invoiced_quantity');

        return $value === null ? '0.000000' : (string) $value;
    }

    private function remainingAcceptedToInvoice(string $accepted, string $returned, string $invoiced): string
    {
        $row = DB::selectOne(
            'SELECT GREATEST(CAST(? AS numeric) - CAST(? AS numeric) - CAST(? AS numeric), 0)::numeric(20,6)::text AS value',
            [$accepted, $returned, $invoiced],
        );

        return $row === null ? '0.000000' : (string) $row->value;
    }

    private function greaterThan(string $left, string $right): bool
    {
        $row = DB::selectOne('SELECT CAST(? AS numeric) > CAST(? AS numeric) AS value', [$left, $right]);

        return $row?->value === true;
    }
}
