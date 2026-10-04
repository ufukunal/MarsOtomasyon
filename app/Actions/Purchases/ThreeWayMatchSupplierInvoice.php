<?php

namespace App\Actions\Purchases;

use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\Period\PurchaseMatch;
use DomainException;

final class ThreeWayMatchSupplierInvoice
{
    public function __construct(
        private readonly ResolvePurchaseLineage $lineage,
        private readonly PurchaseLineAvailability $availability,
    ) {}

    /** @return list<PurchaseMatch> */
    public function handle(Document $invoice): array
    {
        $locked = Document::query()->with('lines')->lockForUpdate()->findOrFail($invoice->id);

        if ($locked->document_type !== DocumentType::SupplierInvoice || $locked->status !== 'draft') {
            throw new DomainException('Üçlü eşleştirme yalnız taslak alış faturasında yapılabilir.');
        }

        $actor = auth()->user();
        $matches = [];

        foreach ($locked->lines as $invoiceLine) {
            if ($invoiceLine->source_line_id === null) {
                throw new DomainException('Alış faturası satırında mal kabul kaynağı zorunludur.');
            }

            $receiptLine = DocumentLine::query()
                ->with('document')
                ->lockForUpdate()
                ->findOrFail((int) $invoiceLine->source_line_id);

            if ($receiptLine->document->document_type !== DocumentType::GoodsReceipt
                || $receiptLine->document->status !== 'posted') {
                throw new DomainException('Alış faturası satırı kesinleşmiş mal kabulle eşleşmelidir.');
            }

            $lineage = $this->lineage->handle($invoiceLine);

            if ($lineage['purchase_order_line_id'] === null
                || $lineage['goods_receipt_line_id'] === null) {
                throw new DomainException('Üçlü eşleştirme kaynak zinciri eksik.');
            }

            $orderLine = DocumentLine::query()
                ->with('document')
                ->lockForUpdate()
                ->findOrFail($lineage['purchase_order_line_id']);

            if ($orderLine->document->document_type !== DocumentType::PurchaseOrder
                || $orderLine->document->status !== 'approved') {
                throw new DomainException('Üçlü eşleştirme için onaylı satınalma siparişi zorunludur.');
            }

            if ((int) $orderLine->document->contact_id !== (int) $locked->contact_id
                || (int) $receiptLine->document->contact_id !== (int) $locked->contact_id) {
                throw new DomainException('Sipariş, mal kabul ve alış faturası tedarikçisi eşleşmiyor.');
            }

            if ($orderLine->document->currency !== $locked->currency) {
                throw new DomainException('Sipariş ve alış faturası para birimi eşleşmiyor.');
            }

            $remaining = $this->availability->receiptInvoiceRemaining($receiptLine);

            if (bccomp((string) $invoiceLine->quantity, $remaining, 3) > 0) {
                throw new DomainException('Alış faturası miktarı mal kabul kalanını aşıyor.');
            }

            $orderPrice = bcadd((string) $orderLine->unit_price, '0', 4);
            $invoicePrice = bcadd((string) $invoiceLine->unit_price, '0', 4);
            $variance = $this->priceVariance($orderPrice, $invoicePrice);

            $matches[] = PurchaseMatch::query()->updateOrCreate(
                ['supplier_invoice_line_id' => $invoiceLine->id],
                [
                    'purchase_order_line_id' => $orderLine->id,
                    'goods_receipt_line_id' => $receiptLine->id,
                    'product_id' => $invoiceLine->product_id,
                    'matched_quantity' => $invoiceLine->quantity,
                    'order_unit_price' => $orderPrice,
                    'invoice_unit_price' => $invoicePrice,
                    'price_variance_rate' => $variance,
                    'created_by' => $actor?->id,
                    'created_by_name' => $actor?->name,
                ],
            );
        }

        return $matches;
    }

    private function priceVariance(string $orderPrice, string $invoicePrice): string
    {
        if (bccomp($orderPrice, '0', 4) === 0) {
            return bccomp($invoicePrice, '0', 4) === 0 ? '0.0000' : '100.0000';
        }

        return bcdiv(
            bcmul(bcsub($invoicePrice, $orderPrice, 8), '100', 8),
            $orderPrice,
            4,
        );
    }
}
