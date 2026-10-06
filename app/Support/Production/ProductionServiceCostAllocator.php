<?php

namespace App\Support\Production;

use App\Actions\Production\ApplyInventoryCostAdjustment;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\Period\ProductionCompletion;
use App\Models\Period\ProductionOrder;
use App\Models\Period\ProductionServiceAllocation;
use App\Models\Period\ProductionServiceInvoice;
use DomainException;

final class ProductionServiceCostAllocator
{
    public function __construct(private readonly ApplyInventoryCostAdjustment $adjustment) {}

    /**
     * @return array{service_cost:string,shares:list<array{invoice_id:int,line_id:int,amount:string}>}
     */
    public function prepareNewCompletion(
        ProductionOrder $order,
        string $completedQuantity,
        string $adjustmentDate,
    ): array {
        if ($order->production_type !== 'subcontract') {
            return ['service_cost' => '0.0000', 'shares' => []];
        }

        $mappings = ProductionServiceInvoice::query()
            ->with('invoice.lines')
            ->where('production_order_id', $order->id)
            ->orderBy('purchase_invoice_id')
            ->get();

        $serviceCost = '0.0000';
        $shares = [];

        foreach ($mappings as $mapping) {
            $invoice = $mapping->invoice;
            $this->assertPostedServiceInvoice($invoice);

            foreach ($invoice->lines->where('line_kind', 'service')->sortBy('id') as $line) {
                $share = $this->rebalanceLine(
                    $invoice,
                    $line,
                    (int) $order->id,
                    $completedQuantity,
                    $adjustmentDate,
                );

                if (bccomp($share, '0', 4) > 0) {
                    $shares[] = [
                        'invoice_id' => (int) $invoice->id,
                        'line_id' => (int) $line->id,
                        'amount' => $share,
                    ];
                    $serviceCost = bcadd($serviceCost, $share, 4);
                }
            }
        }

        return ['service_cost' => $serviceCost, 'shares' => $shares];
    }

    /** @param list<array{invoice_id:int,line_id:int,amount:string}> $shares */
    public function persistNewCompletion(ProductionCompletion $completion, array $shares): void
    {
        foreach ($shares as $share) {
            ProductionServiceAllocation::query()->create([
                'production_order_id' => $completion->production_order_id,
                'purchase_invoice_id' => $share['invoice_id'],
                'purchase_invoice_line_id' => $share['line_id'],
                'production_completion_id' => $completion->id,
                'quantity_basis' => $completion->completed_quantity,
                'allocated_amount_base' => $share['amount'],
                'applied_amount_base' => $share['amount'],
            ]);
        }
    }

    public function recalculateInvoice(Document $invoice, ?string $adjustmentDate = null): void
    {
        $this->assertPostedServiceInvoice($invoice);
        $date = $adjustmentDate ?? $invoice->document_date->toDateString();

        foreach ($invoice->lines->where('line_kind', 'service')->sortBy('id') as $line) {
            $this->rebalanceLine($invoice, $line, null, '0.000', $date);
        }
    }

    public function recalculateOrderInvoices(ProductionOrder $order, string $adjustmentDate): void
    {
        $invoiceIds = ProductionServiceInvoice::query()
            ->where('production_order_id', $order->id)
            ->orderBy('purchase_invoice_id')
            ->pluck('purchase_invoice_id');

        foreach ($invoiceIds as $invoiceId) {
            $invoice = Document::query()->with('lines')->findOrFail((int) $invoiceId);
            $this->recalculateInvoice($invoice, $adjustmentDate);
        }
    }

    private function rebalanceLine(
        Document $invoice,
        DocumentLine $line,
        ?int $virtualOrderId,
        string $virtualQuantity,
        string $adjustmentDate,
    ): string {
        $amountBase = $this->serviceLineAmountBase($invoice, $line);
        $orderIds = ProductionServiceInvoice::query()
            ->where('purchase_invoice_id', $invoice->id)
            ->orderBy('production_order_id')
            ->pluck('production_order_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($orderIds === []) {
            return '0.0000';
        }

        $completions = ProductionCompletion::query()
            ->whereIn('production_order_id', $orderIds)
            ->whereNull('reversal_of_id')
            ->whereDoesntHave('reversals')
            ->orderBy('production_order_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $totalQuantity = $completions->reduce(
            fn (string $sum, ProductionCompletion $completion): string => bcadd(
                $sum,
                (string) $completion->completed_quantity,
                3,
            ),
            '0.000',
        );
        $virtualQuantity = bcadd($virtualQuantity, '0', 3);

        if ($virtualOrderId !== null && bccomp($virtualQuantity, '0', 3) > 0) {
            if (! in_array($virtualOrderId, $orderIds, true)) {
                throw new DomainException('Yeni completion için hizmet faturası production order bağı bulunamadı.');
            }

            $totalQuantity = bcadd($totalQuantity, $virtualQuantity, 3);
        }

        if (bccomp($totalQuantity, '0', 3) <= 0) {
            return '0.0000';
        }

        $allocated = '0.0000';
        $eligibleCount = $completions->count()
            + ($virtualOrderId !== null && bccomp($virtualQuantity, '0', 3) > 0 ? 1 : 0);
        $position = 0;
        $virtualShare = '0.0000';

        foreach ($completions as $completion) {
            $position++;
            $target = $position === $eligibleCount
                ? bcsub($amountBase, $allocated, 4)
                : bcadd(
                    bcmul(
                        $amountBase,
                        bcdiv((string) $completion->completed_quantity, $totalQuantity, 10),
                        10,
                    ),
                    '0',
                    4,
                );
            $allocated = bcadd($allocated, $target, 4);

            $allocation = ProductionServiceAllocation::query()->firstOrCreate(
                [
                    'purchase_invoice_line_id' => $line->id,
                    'production_completion_id' => $completion->id,
                ],
                [
                    'production_order_id' => $completion->production_order_id,
                    'purchase_invoice_id' => $invoice->id,
                    'quantity_basis' => $completion->completed_quantity,
                    'allocated_amount_base' => $target,
                    'applied_amount_base' => '0.0000',
                ],
            );

            $applied = bcadd((string) $allocation->applied_amount_base, '0', 4);
            $delta = bcsub($target, $applied, 4);

            if (bccomp($delta, '0', 4) !== 0) {
                $this->adjustment->handle(
                    (int) $completion->order()->value('product_id'),
                    (int) $completion->id,
                    (int) $allocation->id,
                    $adjustmentDate,
                    (string) $completion->completed_quantity,
                    $delta,
                );
            }

            $allocation->quantity_basis = $completion->completed_quantity;
            $allocation->allocated_amount_base = $target;
            $allocation->applied_amount_base = $target;
            $allocation->save();
        }

        if ($virtualOrderId !== null && bccomp($virtualQuantity, '0', 3) > 0) {
            $position++;
            $virtualShare = $position === $eligibleCount
                ? bcsub($amountBase, $allocated, 4)
                : bcadd(
                    bcmul(
                        $amountBase,
                        bcdiv($virtualQuantity, $totalQuantity, 10),
                        10,
                    ),
                    '0',
                    4,
                );
        }

        return $virtualShare;
    }

    private function serviceLineAmountBase(Document $invoice, DocumentLine $target): string
    {
        $allocatedDiscount = '0.00000000';
        $lines = $invoice->lines->sortBy('id')->values();
        $last = $lines->count() - 1;

        foreach ($lines as $index => $line) {
            if (bccomp((string) $invoice->subtotal, '0', 4) === 0) {
                $discountShare = '0.00000000';
            } elseif ($index === $last) {
                $discountShare = bcsub((string) $invoice->discount_amount, $allocatedDiscount, 8);
            } else {
                $discountShare = bcdiv(
                    bcmul((string) $invoice->discount_amount, (string) $line->line_total, 8),
                    (string) $invoice->subtotal,
                    8,
                );
                $allocatedDiscount = bcadd($allocatedDiscount, $discountShare, 8);
            }

            if ((int) $line->id !== (int) $target->id) {
                continue;
            }

            $net = bcsub((string) $line->line_total, $discountShare, 8);

            return bcadd(
                bcmul($net, (string) $invoice->exchange_rate, 8),
                '0',
                4,
            );
        }

        throw new DomainException('Fason hizmet fatura satırı bulunamadı.');
    }

    private function assertPostedServiceInvoice(Document $invoice): void
    {
        if ($invoice->document_type !== DocumentType::SupplierInvoice
            || $invoice->status !== 'posted'
            || $invoice->lines->where('line_kind', 'service')->isEmpty()) {
            throw new DomainException('Fason hizmet kaynağı service satırlı kesinleşmiş alış faturası olmalıdır.');
        }
    }
}
