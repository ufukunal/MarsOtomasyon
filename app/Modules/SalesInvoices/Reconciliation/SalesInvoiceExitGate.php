<?php

namespace App\Modules\SalesInvoices\Reconciliation;

use App\Modules\Accounts\Models\AccountTransaction;
use App\Modules\Dispatches\Models\DispatchLine;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Enums\StockReservationStatus;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Quotes\Pricing\Decimal6;
use App\Modules\SalesInvoices\Enums\SalesInvoiceMode;
use App\Modules\SalesInvoices\Enums\SalesInvoiceStatus;
use App\Modules\SalesInvoices\Models\SalesInvoice;
use App\Modules\SalesInvoices\Models\SalesInvoiceLine;
use App\Modules\SalesOrders\Enums\SalesOrderProgressType;
use App\Modules\SalesOrders\Models\SalesOrderLine;
use App\Modules\SalesOrders\Models\SalesOrderLineProgress;
use App\Modules\SalesOrders\Models\SalesOrderLineProgressEffect;
use App\Modules\SalesOrders\Models\SalesOrderReservationGeneration;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class SalesInvoiceExitGate
{
    public function assertConsistent(SalesInvoice $invoice): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Sales invoice exit gate must run inside a database transaction.');
        }

        $locked = SalesInvoice::query()
            ->where('company_id', $invoice->company_id)
            ->whereKey($invoice->getKey())
            ->lockForUpdate()
            ->first();

        if (! $locked instanceof SalesInvoice) {
            throw new LogicException('Sales invoice exit gate source invoice could not be locked.');
        }

        $status = $locked->statusEnum();
        if (! in_array($status, [SalesInvoiceStatus::Finalized, SalesInvoiceStatus::Cancelled], true)) {
            throw new LogicException('Sales invoice exit gate only accepts finalized or cancelled invoices.');
        }

        $lines = $locked->lines()->lockForUpdate()->get();
        if ($lines->isEmpty()) {
            throw new LogicException('Sales invoice exit gate requires at least one invoice line.');
        }

        $this->assertAccountEffect($locked, $status);
        $mode = $locked->modeEnum();

        /** @var SalesInvoiceLine $line */
        foreach ($lines as $line) {
            if ($line->source_sales_order_line_id !== null) {
                $this->assertProgressEffect($line, SalesOrderProgressType::Invoiced, 'progress.invoice', $status);
            }

            if ($mode === SalesInvoiceMode::Direct) {
                $this->assertInvoiceStockEffect($line, $status);
            } elseif ($mode === SalesInvoiceMode::OrderLinked) {
                $this->assertOrderLinkedLine($line, $status);
            } else {
                $this->assertDispatchLinkedStockEffect($line);
            }
        }
    }

    private function assertAccountEffect(SalesInvoice $invoice, SalesInvoiceStatus $status): void
    {
        $originals = AccountTransaction::query()
            ->where('company_id', $invoice->company_id)
            ->where('account_id', $invoice->account_id)
            ->where('source_type', 'sales_invoice')
            ->where('source_id', (string) $invoice->getKey())
            ->where('effect_type', 'account.sales_invoice')
            ->whereNull('reversal_of_transaction_id')
            ->get();

        if ($originals->count() !== 1) {
            throw new LogicException('Sales invoice exit gate requires exactly one account posting.');
        }

        $original = $originals->first();
        if (! $original instanceof AccountTransaction
            || (string) $original->currency_code !== (string) $invoice->currency_code
            || (string) $original->signed_amount !== Decimal6::positive((string) $invoice->gross_total, 'gross_total')->value()
            || (string) $original->getRawOriginal('posting_date') !== (string) $invoice->getRawOriginal('invoice_date')) {
            throw new LogicException('Sales invoice account posting does not reconcile with the invoice snapshot.');
        }

        $reversals = AccountTransaction::query()
            ->where('company_id', $invoice->company_id)
            ->where('account_id', $invoice->account_id)
            ->where('source_type', 'sales_invoice')
            ->where('source_id', (string) $invoice->getKey())
            ->where('effect_type', 'account.sales_invoice.reverse')
            ->get();

        if ($status === SalesInvoiceStatus::Finalized) {
            if ($reversals->isNotEmpty()) {
                throw new LogicException('Finalized sales invoice cannot already have an account reversal.');
            }

            return;
        }

        if ($reversals->count() !== 1) {
            throw new LogicException('Cancelled sales invoice exit gate requires exactly one account reversal.');
        }

        $reversal = $reversals->first();
        if (! $reversal instanceof AccountTransaction
            || (int) $reversal->reversal_of_transaction_id !== (int) $original->getKey()
            || (string) $reversal->currency_code !== (string) $invoice->currency_code
            || (string) $reversal->signed_amount !== $this->negative((string) $invoice->gross_total, 'gross_total')) {
            throw new LogicException('Sales invoice account reversal does not exactly reverse the invoice posting.');
        }
    }

    private function assertOrderLinkedLine(SalesInvoiceLine $line, SalesInvoiceStatus $status): void
    {
        if ($line->source_sales_order_id === null || $line->source_sales_order_line_id === null) {
            throw new LogicException('Order-linked invoice line is missing source order lineage.');
        }

        $this->assertInvoiceStockEffect($line, $status);
        $this->assertProgressEffect($line, SalesOrderProgressType::Dispatched, 'progress.dispatch', $status);
        $this->assertOrderReservationProjection($line);
    }

    private function assertInvoiceStockEffect(SalesInvoiceLine $line, SalesInvoiceStatus $status): void
    {
        $originals = StockMovement::query()
            ->where('company_id', $line->company_id)
            ->where('source_type', 'sales_invoice_line')
            ->where('source_id', (string) $line->getKey())
            ->where('effect_type', 'stock.out')
            ->where('movement_type', StockMovementType::InvoiceOut->value)
            ->whereNull('reversal_of_movement_id')
            ->get();

        if ($originals->count() !== 1) {
            throw new LogicException('Sales invoice exit gate requires exactly one invoice stock out per physical invoice line.');
        }

        $original = $originals->first();
        if (! $original instanceof StockMovement
            || (int) $original->product_id !== (int) $line->product_id
            || (int) $original->warehouse_id !== (int) $line->warehouse_id
            || (int) $original->location_id !== (int) $line->location_id
            || (string) $original->quantity_delta !== $this->negative((string) $line->quantity, 'quantity')) {
            throw new LogicException('Sales invoice stock out does not reconcile with its invoice line.');
        }

        $reversals = StockMovement::query()
            ->where('company_id', $line->company_id)
            ->where('source_type', 'sales_invoice_line')
            ->where('source_id', (string) $line->getKey())
            ->where('effect_type', 'stock.out.reverse')
            ->get();

        if ($status === SalesInvoiceStatus::Finalized) {
            if ($reversals->isNotEmpty()) {
                throw new LogicException('Finalized sales invoice cannot already have a stock reversal.');
            }

            return;
        }

        if ($reversals->count() !== 1) {
            throw new LogicException('Cancelled sales invoice exit gate requires exactly one stock reversal per physical line.');
        }

        $reversal = $reversals->first();
        if (! $reversal instanceof StockMovement
            || (int) $reversal->reversal_of_movement_id !== (int) $original->getKey()
            || (string) $reversal->getRawOriginal('movement_type') !== StockMovementType::AdjustmentIn->value
            || (int) $reversal->product_id !== (int) $line->product_id
            || (int) $reversal->warehouse_id !== (int) $line->warehouse_id
            || (int) $reversal->location_id !== (int) $line->location_id
            || (string) $reversal->quantity_delta !== Decimal6::positive((string) $line->quantity, 'quantity')->value()) {
            throw new LogicException('Sales invoice stock reversal does not exactly reverse its invoice stock out.');
        }
    }

    private function assertDispatchLinkedStockEffect(SalesInvoiceLine $line): void
    {
        if ($line->source_dispatch_id === null || $line->source_dispatch_line_id === null) {
            throw new LogicException('Dispatch-linked invoice line is missing source dispatch lineage.');
        }

        $invoiceMovements = StockMovement::query()
            ->where('company_id', $line->company_id)
            ->where('source_type', 'sales_invoice_line')
            ->where('source_id', (string) $line->getKey())
            ->get();

        if ($invoiceMovements->isNotEmpty()) {
            throw new LogicException('Dispatch-linked invoice exit gate forbids a second invoice stock effect.');
        }

        $dispatchLine = DispatchLine::query()
            ->where('company_id', $line->company_id)
            ->where('dispatch_id', $line->source_dispatch_id)
            ->whereKey($line->source_dispatch_line_id)
            ->sharedLock()
            ->first();

        if (! $dispatchLine instanceof DispatchLine) {
            throw new LogicException('Dispatch-linked invoice source dispatch line could not be reconciled.');
        }

        $movements = StockMovement::query()
            ->where('company_id', $line->company_id)
            ->where('source_type', 'dispatch_line')
            ->where('source_id', (string) $dispatchLine->getKey())
            ->where('effect_type', 'stock.out')
            ->where('movement_type', StockMovementType::DispatchOut->value)
            ->whereNull('reversal_of_movement_id')
            ->get();

        if ($movements->count() !== 1) {
            throw new LogicException('Dispatch-linked invoice exit gate requires exactly one source dispatch stock out.');
        }

        $movement = $movements->first();
        if (! $movement instanceof StockMovement
            || (int) $movement->product_id !== (int) $dispatchLine->product_id
            || (int) $movement->warehouse_id !== (int) $dispatchLine->warehouse_id
            || (int) $movement->location_id !== (int) $dispatchLine->location_id
            || (string) $movement->quantity_delta !== $this->negative((string) $dispatchLine->quantity, 'dispatch_quantity')) {
            throw new LogicException('Dispatch-linked invoice source dispatch stock out is inconsistent.');
        }
    }

    private function assertProgressEffect(
        SalesInvoiceLine $line,
        SalesOrderProgressType $progressType,
        string $effectType,
        SalesInvoiceStatus $status,
    ): void {
        if ($line->source_sales_order_id === null || $line->source_sales_order_line_id === null) {
            throw new LogicException('Linked invoice progress reconciliation requires source order lineage.');
        }

        $originals = SalesOrderLineProgressEffect::query()
            ->where('company_id', $line->company_id)
            ->where('sales_order_id', $line->source_sales_order_id)
            ->where('sales_order_line_id', $line->source_sales_order_line_id)
            ->where('progress_type', $progressType->value)
            ->where('source_type', 'sales_invoice_line')
            ->where('source_id', (string) $line->getKey())
            ->where('effect_type', $effectType)
            ->whereNull('reversal_of_progress_effect_id')
            ->get();

        if ($originals->count() !== 1) {
            throw new LogicException('Sales invoice exit gate requires exactly one linked order progress effect: '.$effectType);
        }

        $original = $originals->first();
        if (! $original instanceof SalesOrderLineProgressEffect
            || (string) $original->quantity_delta !== Decimal6::positive((string) $line->quantity, 'quantity')->value()) {
            throw new LogicException('Sales invoice linked order progress does not reconcile with its invoice line: '.$effectType);
        }

        $reversalEffect = $effectType.'.reverse';
        $reversals = SalesOrderLineProgressEffect::query()
            ->where('company_id', $line->company_id)
            ->where('sales_order_id', $line->source_sales_order_id)
            ->where('sales_order_line_id', $line->source_sales_order_line_id)
            ->where('progress_type', $progressType->value)
            ->where('source_type', 'sales_invoice_line')
            ->where('source_id', (string) $line->getKey())
            ->where('effect_type', $reversalEffect)
            ->get();

        if ($status === SalesInvoiceStatus::Finalized) {
            if ($reversals->isNotEmpty()) {
                throw new LogicException('Finalized sales invoice cannot already have linked progress reversal: '.$effectType);
            }

            return;
        }

        if ($reversals->count() !== 1) {
            throw new LogicException('Cancelled sales invoice exit gate requires exactly one linked progress reversal: '.$effectType);
        }

        $reversal = $reversals->first();
        if (! $reversal instanceof SalesOrderLineProgressEffect
            || (int) $reversal->reversal_of_progress_effect_id !== (int) $original->getKey()
            || (string) $reversal->quantity_delta !== $this->negative((string) $line->quantity, 'quantity')) {
            throw new LogicException('Sales invoice linked progress reversal is not exact: '.$effectType);
        }
    }

    private function assertOrderReservationProjection(SalesInvoiceLine $line): void
    {
        $orderLine = SalesOrderLine::query()
            ->where('company_id', $line->company_id)
            ->where('sales_order_id', $line->source_sales_order_id)
            ->whereKey($line->source_sales_order_line_id)
            ->sharedLock()
            ->first();

        if (! $orderLine instanceof SalesOrderLine) {
            throw new LogicException('Order-linked invoice exit gate source order line could not be reconciled.');
        }

        if ($orderLine->warehouse_id === null && $orderLine->location_id === null) {
            return;
        }

        $logicalLineKey = $orderLine->logical_line_key;
        if (! is_string($logicalLineKey)
            || $logicalLineKey === ''
            || $orderLine->warehouse_id === null
            || $orderLine->location_id === null) {
            throw new LogicException('Allocated order line reservation identity is incomplete.');
        }

        $projection = SalesOrderLineProgress::query()
            ->where('company_id', $line->company_id)
            ->where('sales_order_id', $line->source_sales_order_id)
            ->where('sales_order_line_id', $line->source_sales_order_line_id)
            ->first();

        if (! $projection instanceof SalesOrderLineProgress) {
            throw new LogicException('Order-linked invoice exit gate progress projection is missing.');
        }

        $targetValue = $projection->getAttribute('dispatch_remaining_quantity');
        if (! is_string($targetValue)) {
            throw new LogicException('Order-linked invoice dispatch remaining projection is invalid.');
        }

        $target = Decimal6::nonNegative($targetValue, 'dispatch_remaining_quantity')->value();
        $activeGenerations = SalesOrderReservationGeneration::query()
            ->where('company_id', $line->company_id)
            ->where('sales_order_id', $line->source_sales_order_id)
            ->where('logical_line_key', $logicalLineKey)
            ->whereNull('released_at')
            ->lockForUpdate()
            ->get();

        if ($target === Decimal6::zero()->value()) {
            if ($activeGenerations->isNotEmpty()) {
                throw new LogicException('Order-linked invoice reservation must be empty when dispatch remaining is zero.');
            }

            return;
        }

        if ($activeGenerations->count() !== 1) {
            throw new LogicException('Order-linked invoice exit gate requires exactly one active reservation generation.');
        }

        $generation = $activeGenerations->first();
        if (! $generation instanceof SalesOrderReservationGeneration
            || (int) $generation->product_id !== (int) $orderLine->product_id
            || (int) $generation->warehouse_id !== (int) $orderLine->warehouse_id
            || (int) $generation->location_id !== (int) $orderLine->location_id
            || (string) $generation->quantity !== $target) {
            throw new LogicException('Order-linked invoice active reservation generation does not match dispatch remaining projection.');
        }

        $reservation = StockReservation::query()
            ->where('company_id', $line->company_id)
            ->whereKey($generation->stock_reservation_id)
            ->first();

        if (! $reservation instanceof StockReservation
            || $reservation->statusEnum() !== StockReservationStatus::Active
            || (int) $reservation->product_id !== (int) $orderLine->product_id
            || (int) $reservation->warehouse_id !== (int) $orderLine->warehouse_id
            || (int) $reservation->location_id !== (int) $orderLine->location_id
            || (string) $reservation->quantity !== $target) {
            throw new LogicException('Order-linked invoice active stock reservation does not match progress projection.');
        }
    }

    private function negative(string $value, string $field): string
    {
        return '-'.Decimal6::positive($value, $field)->value();
    }
}
