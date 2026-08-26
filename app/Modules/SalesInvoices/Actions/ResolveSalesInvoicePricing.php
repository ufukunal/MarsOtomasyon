<?php

namespace App\Modules\SalesInvoices\Actions;

use App\Modules\Core\Models\TaxZeroReason;
use App\Modules\Products\Models\Product;
use App\Modules\Quotes\Pricing\DeterministicTaxCalculator;
use App\Modules\Quotes\Pricing\PriceBasis;
use App\Modules\Quotes\Pricing\TaxCalculationLineInput;
use App\Modules\SalesInvoices\Enums\SalesInvoiceMode;
use App\Modules\SalesOrders\Models\SalesOrder;
use App\Modules\SalesOrders\Models\SalesOrderLine;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;

final readonly class ResolveSalesInvoicePricing
{
    public function __construct(private DeterministicTaxCalculator $calculator) {}

    public function resolve(
        int $companyId,
        SalesInvoiceDraftData $data,
        ResolvedSalesInvoiceSource $source,
    ): ResolvedSalesInvoicePricing {
        if (count($data->lines) !== count($source->lines)) {
            throw new LogicException('Invoice source/pricing line counts are inconsistent.');
        }

        return match ($data->mode) {
            SalesInvoiceMode::Direct => $this->resolveDirect($companyId, $data, $source),
            SalesInvoiceMode::OrderLinked, SalesInvoiceMode::DispatchLinked => $this->resolveLinked($companyId, $data, $source),
        };
    }

    private function resolveDirect(
        int $companyId,
        SalesInvoiceDraftData $data,
        ResolvedSalesInvoiceSource $source,
    ): ResolvedSalesInvoicePricing {
        $inputs = [];
        $metadata = [];

        foreach ($data->lines as $offset => $line) {
            $resolvedLine = $source->lines[$offset];
            $product = Product::query()
                ->with('tax')
                ->where('company_id', $companyId)
                ->whereKey($resolvedLine->productId)
                ->where('status', 'active')
                ->first();
            $tax = $product?->tax;

            if ($product === null || $tax === null || ! (bool) $tax->is_active) {
                throw ValidationException::withMessages([
                    "lines.$offset.product_id" => 'Aktif şirkete ait, aktif vergi tanımlı bir ürün seçilmelidir.',
                ]);
            }

            $naturalTaxRate = (string) $tax->rate;
            if ($line->taxIsZeroed && $this->isZeroRate($naturalTaxRate)) {
                throw ValidationException::withMessages([
                    "lines.$offset.tax_is_zeroed" => 'Doğal yüzde 0 KDV satırında KDV sıfırlama işareti kullanılmaz.',
                ]);
            }

            $taxRate = $line->taxIsZeroed ? '0.000000' : $naturalTaxRate;
            $zeroReason = $this->zeroReason($companyId, $line->taxZeroReasonId, $taxRate, $offset);

            $inputs[] = new TaxCalculationLineInput(
                key: (string) ($offset + 1),
                quantity: $resolvedLine->quantity,
                unitPrice: $line->unitPrice ?? (string) $product->sale_price_net,
                priceBasis: $line->priceBasis ?? PriceBasis::Net,
                taxRate: $taxRate,
                lineDiscountRate: $line->lineDiscountRate ?? '0',
                taxZeroReasonCode: $zeroReason === null ? null : (string) $zeroReason->code,
            );
            $metadata[] = [
                (int) $tax->getKey(),
                (string) $tax->code,
                $line->taxIsZeroed,
                $zeroReason === null ? null : (int) $zeroReason->getKey(),
            ];
        }

        return $this->calculate($inputs, $metadata, $data->documentDiscountRate ?? '0');
    }

    private function resolveLinked(
        int $companyId,
        SalesInvoiceDraftData $data,
        ResolvedSalesInvoiceSource $source,
    ): ResolvedSalesInvoicePricing {
        if (! $source->salesOrder instanceof SalesOrder) {
            throw new LogicException('Linked invoice pricing requires a source sales order.');
        }
        if ($data->documentDiscountRate !== null) {
            throw ValidationException::withMessages([
                'document_discount_rate' => 'Sipariş/irsaliye bağlı faturada belge indirimi kaynak siparişten miras alınır.',
            ]);
        }

        $sourceIds = [];
        foreach ($source->lines as $resolvedLine) {
            if ($resolvedLine->sourceSalesOrderLineId === null) {
                throw new LogicException('Linked invoice source line is missing sales order lineage.');
            }
            $sourceIds[] = $resolvedLine->sourceSalesOrderLineId;
        }

        $orderLines = SalesOrderLine::query()
            ->where('company_id', $companyId)
            ->where('sales_order_id', $source->salesOrder->getKey())
            ->whereIn('id', $sourceIds)
            ->get()
            ->keyBy('id');
        if ($orderLines->count() !== count(array_unique($sourceIds))) {
            throw new LogicException('Linked invoice pricing source order lines are incomplete.');
        }

        $inputs = [];
        $metadata = [];
        foreach ($data->lines as $offset => $line) {
            if ($line->unitPrice !== null || $line->priceBasis !== null || $line->lineDiscountRate !== null
                || $line->taxIsZeroed || $line->taxZeroReasonId !== null) {
                throw ValidationException::withMessages([
                    "lines.$offset" => 'Sipariş/irsaliye bağlı faturada fiyat ve vergi şartları kaynak siparişten miras alınır.',
                ]);
            }

            $resolvedLine = $source->lines[$offset];
            $orderLine = $orderLines->get($resolvedLine->sourceSalesOrderLineId);
            if (! $orderLine instanceof SalesOrderLine) {
                throw new LogicException('Linked invoice source order line could not be resolved.');
            }

            $priceBasisValue = $orderLine->getRawOriginal('price_basis');
            $priceBasis = is_string($priceBasisValue) ? PriceBasis::tryFrom($priceBasisValue) : null;
            if ($priceBasis === null) {
                throw new LogicException('Source sales order price basis is invalid.');
            }

            $inputs[] = new TaxCalculationLineInput(
                key: (string) ($offset + 1),
                quantity: $resolvedLine->quantity,
                unitPrice: (string) $orderLine->unit_price,
                priceBasis: $priceBasis,
                taxRate: (string) $orderLine->tax_rate,
                lineDiscountRate: (string) $orderLine->line_discount_rate,
                taxZeroReasonCode: $orderLine->tax_zero_reason_code === null ? null : (string) $orderLine->tax_zero_reason_code,
            );
            $metadata[] = [
                (int) $orderLine->tax_id,
                (string) $orderLine->tax_code,
                (bool) $orderLine->tax_is_zeroed,
                $orderLine->tax_zero_reason_id === null ? null : (int) $orderLine->tax_zero_reason_id,
            ];
        }

        return $this->calculate($inputs, $metadata, (string) $source->salesOrder->document_discount_rate);
    }

    /**
     * @param  list<TaxCalculationLineInput>  $inputs
     * @param  list<array{int,string,bool,?int}>  $metadata
     */
    private function calculate(array $inputs, array $metadata, string $documentDiscountRate): ResolvedSalesInvoicePricing
    {
        try {
            $calculation = $this->calculator->calculate($inputs, $documentDiscountRate);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['lines' => $exception->getMessage()]);
        }

        $lines = [];
        foreach ($calculation->lines as $offset => $lineResult) {
            [$taxId, $taxCode, $taxIsZeroed, $zeroReasonId] = $metadata[$offset];
            $lines[] = new ResolvedSalesInvoicePricingLine(
                taxId: $taxId,
                taxCode: $taxCode,
                taxIsZeroed: $taxIsZeroed,
                taxZeroReasonId: $zeroReasonId,
                calculation: $lineResult,
            );
        }

        return new ResolvedSalesInvoicePricing(
            documentDiscountRate: $calculation->lines[0]->documentDiscountRate,
            lines: $lines,
            calculation: $calculation,
        );
    }

    private function zeroReason(int $companyId, ?int $reasonId, string $taxRate, int $offset): ?TaxZeroReason
    {
        if ($this->isZeroRate($taxRate)) {
            if ($reasonId === null) {
                throw ValidationException::withMessages([
                    "lines.$offset.tax_zero_reason_id" => 'Yüzde 0 vergi satırında aktif KDV sıfır nedeni zorunludur.',
                ]);
            }

            $reason = TaxZeroReason::query()
                ->where('company_id', $companyId)
                ->whereKey($reasonId)
                ->where('is_active', true)
                ->first();
            if (! $reason instanceof TaxZeroReason) {
                throw ValidationException::withMessages([
                    "lines.$offset.tax_zero_reason_id" => 'Aktif şirkete ait geçerli KDV sıfır nedeni seçilmelidir.',
                ]);
            }

            return $reason;
        }

        if ($reasonId !== null) {
            throw ValidationException::withMessages([
                "lines.$offset.tax_zero_reason_id" => 'KDV sıfır nedeni yalnız yüzde 0 vergi satırında kullanılabilir.',
            ]);
        }

        return null;
    }

    private function isZeroRate(string $rate): bool
    {
        return preg_match('/^0+(?:\.0+)?$/D', trim($rate)) === 1;
    }
}
