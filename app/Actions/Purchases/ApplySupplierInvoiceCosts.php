<?php

namespace App\Actions\Purchases;

use App\Actions\Stock\UpdateMovingAverage;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\Product;
use App\Models\Period\ProductCost;
use App\Models\Period\PurchaseMatch;
use App\Models\Period\StockMovement;
use App\Support\Audit\AuditContext;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ApplySupplierInvoiceCosts
{
    public function __construct(
        private readonly CheckPurchasePriceDeviation $deviation,
        private readonly UpdateMovingAverage $updateMovingAverage,
    ) {}

    public function handle(Document $invoice, bool $deviationAccepted): void
    {
        $locked = Document::query()->with('lines')->lockForUpdate()->findOrFail($invoice->id);

        if ($locked->document_type !== DocumentType::SupplierInvoice || $locked->status !== 'posted') {
            throw new DomainException('Maliyet yalnız kesinleşmiş alış faturasıyla güncellenebilir.');
        }

        $netAmounts = $this->netLineAmounts($locked);

        foreach ($locked->lines as $line) {
            if ($line->line_kind !== 'stock' || $line->product_id === null) {
                continue;
            }

            $match = PurchaseMatch::query()
                ->where('supplier_invoice_line_id', $line->id)
                ->lockForUpdate()
                ->firstOrFail();

            $baseQuantity = bcadd((string) $line->base_quantity, '0', 3);

            if (bccomp($baseQuantity, '0', 3) <= 0) {
                throw new DomainException('Alış faturası stok satırı temel miktarı geçersiz.');
            }

            $unitCostDocumentCurrency = bcdiv($netAmounts[(int) $line->id], $baseQuantity, 8);
            $unitCostTry = bcadd(
                bcmul($unitCostDocumentCurrency, (string) $locked->exchange_rate, 8),
                '0',
                4,
            );

            $priceDeviation = $this->deviation->handle((int) $line->product_id, $unitCostTry);
            $product = Product::query()->findOrFail((int) $line->product_id);

            if ($priceDeviation->exceedsThreshold && ! $deviationAccepted) {
                throw new DomainException(sprintf(
                    '%s alış fiyatı önceki alış fiyatından %s%% sapıyor. Devam etmek için sapma uyarısı kabul edilmelidir.',
                    $product->code,
                    $priceDeviation->deviationRate,
                ));
            }

            DB::connection('period')->table('product_costs')->insertOrIgnore([
                'product_id' => $line->product_id,
                'last_purchase_price' => '0.0000',
                'moving_average' => '0.0000',
                'import_cost' => '0.0000',
                'production_cost' => '0.0000',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $cost = ProductCost::query()
                ->where('product_id', $line->product_id)
                ->lockForUpdate()
                ->firstOrFail();
            $previousAverage = (string) $cost->moving_average;
            $previousLastPurchase = (string) $cost->last_purchase_price;
            $previousLastPurchaseAt = $cost->last_purchase_at?->toDateTimeString();

            $receiptLine = $match->goodsReceiptLine()
                ->with('document')
                ->firstOrFail();
            $receiptMovement = StockMovement::query()
                ->where('document_type', DocumentType::GoodsReceipt->value)
                ->where('document_id', $receiptLine->document_id)
                ->where('product_id', $line->product_id)
                ->where('location_id', $receiptLine->location_id)
                ->where('direction', 'in')
                ->orderBy('id')
                ->first();

            if (! $receiptMovement) {
                throw new DomainException('Alış faturası maliyeti için mal kabul stok hareketi bulunamadı.');
            }

            $provisionalUnitCost = bcadd((string) $receiptMovement->unit_cost, '0', 4);
            $unitDelta = bcsub($unitCostTry, $provisionalUnitCost, 8);
            $valueDelta = bcadd(bcmul($baseQuantity, $unitDelta, 8), '0', 4);
            $newAverage = $this->updateMovingAverage->applyValueDelta(
                (int) $line->product_id,
                $valueDelta,
                $unitCostTry,
                $locked->document_date->toDateString(),
            );

            $match->update([
                'provisional_unit_cost_try' => $provisionalUnitCost,
                'cost_unit_try' => $unitCostTry,
                'previous_moving_average' => $previousAverage,
                'previous_last_purchase_price' => $previousLastPurchase,
                'previous_last_purchase_at' => $previousLastPurchaseAt,
                'cost_value_delta' => $valueDelta,
                'new_moving_average' => $newAverage,
            ]);

            if ($priceDeviation->exceedsThreshold) {
                AuditContext::period(
                    'Alış fiyat sapma uyarısı kabul edildi.',
                    [
                        'supplier_invoice_id' => $locked->id,
                        'product_id' => $line->product_id,
                        'product_code' => $product->code,
                        'previous_price' => $priceDeviation->previousPrice,
                        'incoming_price' => $priceDeviation->incomingPrice,
                        'deviation_rate' => $priceDeviation->deviationRate,
                    ],
                    $locked,
                    'purchase_price_deviation_accepted',
                );
            }
        }
    }

    /** @return array<int,string> */
    private function netLineAmounts(Document $invoice): array
    {
        $result = [];
        $allocated = '0.00000000';
        $lines = $invoice->lines->values();
        $last = $lines->count() - 1;

        foreach ($lines as $index => $line) {
            if (bccomp((string) $invoice->subtotal, '0', 4) === 0) {
                $share = '0.00000000';
            } elseif ($index === $last) {
                $share = bcsub((string) $invoice->discount_amount, $allocated, 8);
            } else {
                $share = bcdiv(
                    bcmul((string) $invoice->discount_amount, (string) $line->line_total, 8),
                    (string) $invoice->subtotal,
                    8,
                );
                $allocated = bcadd($allocated, $share, 8);
            }

            $result[(int) $line->id] = bcsub((string) $line->line_total, $share, 8);
        }

        return $result;
    }
}
