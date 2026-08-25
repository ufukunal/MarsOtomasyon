<?php

namespace App\Modules\Quotes\Pricing;

use InvalidArgumentException;

final class DeterministicTaxCalculator
{
    /**
     * @param  list<TaxCalculationLineInput>  $lines
     */
    public function calculate(array $lines, string $documentDiscountRate = '0'): TaxCalculationResult
    {
        $documentDiscount = Decimal6::rate($documentDiscountRate, 'document_discount_rate');
        $seenKeys = [];
        $results = [];
        $baseNetTotal = Decimal6::zero();
        $lineDiscountTotal = Decimal6::zero();
        $documentDiscountTotal = Decimal6::zero();
        $netTotal = Decimal6::zero();
        $taxTotal = Decimal6::zero();
        $grossTotal = Decimal6::zero();

        foreach ($lines as $line) {
            if (! $line instanceof TaxCalculationLineInput) {
                throw new InvalidArgumentException('Tax calculator lines must be TaxCalculationLineInput instances.');
            }

            $key = trim($line->key);

            if ($key === '' || strlen($key) > 64) {
                throw new InvalidArgumentException('line key boş olamaz ve 64 karakteri aşamaz.');
            }

            if (isset($seenKeys[$key])) {
                throw new InvalidArgumentException('line key belge içinde benzersiz olmalıdır.');
            }

            $seenKeys[$key] = true;

            $quantity = Decimal6::positive($line->quantity, 'quantity');
            $unitPrice = Decimal6::nonNegative($line->unitPrice, 'unit_price');
            $taxRate = Decimal6::rate($line->taxRate, 'tax_rate');
            $lineDiscountRate = Decimal6::rate($line->lineDiscountRate, 'line_discount_rate');
            $taxZeroReasonCode = $this->taxZeroReasonCode($line->taxZeroReasonCode, $taxRate);
            $basisAmount = $quantity->multiply($unitPrice);

            if ($line->priceBasis === PriceBasis::Net) {
                $baseNet = $basisAmount;
                $lineDiscountNet = $baseNet->percent($lineDiscountRate);
                $afterLineNet = $baseNet->subtract($lineDiscountNet, 'line_net');
                $documentDiscountNet = $afterLineNet->percent($documentDiscount);
                $net = $afterLineNet->subtract($documentDiscountNet, 'line_net');
                $tax = $net->percent($taxRate);
                $gross = $net->add($tax);
            } else {
                $baseGross = $basisAmount;
                $lineDiscountGross = $baseGross->percent($lineDiscountRate);
                $afterLineGross = $baseGross->subtract($lineDiscountGross, 'line_gross');
                $documentDiscountGross = $afterLineGross->percent($documentDiscount);
                $gross = $afterLineGross->subtract($documentDiscountGross, 'line_gross');

                $baseNet = $baseGross->netFromGross($taxRate);
                $afterLineNet = $afterLineGross->netFromGross($taxRate);
                $net = $gross->netFromGross($taxRate);
                $lineDiscountNet = $baseNet->subtract($afterLineNet, 'line_discount_net');
                $documentDiscountNet = $afterLineNet->subtract($net, 'document_discount_net');
                $tax = $gross->subtract($net, 'line_tax');
            }

            $result = new TaxCalculationLineResult(
                key: $key,
                quantity: $quantity->value(),
                unitPrice: $unitPrice->value(),
                priceBasis: $line->priceBasis,
                taxRate: $taxRate->value(),
                lineDiscountRate: $lineDiscountRate->value(),
                documentDiscountRate: $documentDiscount->value(),
                taxZeroReasonCode: $taxZeroReasonCode,
                baseNet: $baseNet->value(),
                lineDiscountNet: $lineDiscountNet->value(),
                documentDiscountNet: $documentDiscountNet->value(),
                net: $net->value(),
                tax: $tax->value(),
                gross: $gross->value(),
            );

            $results[] = $result;
            $baseNetTotal = $baseNetTotal->add($baseNet);
            $lineDiscountTotal = $lineDiscountTotal->add($lineDiscountNet);
            $documentDiscountTotal = $documentDiscountTotal->add($documentDiscountNet);
            $netTotal = $netTotal->add($net);
            $taxTotal = $taxTotal->add($tax);
            $grossTotal = $grossTotal->add($gross);
        }

        return new TaxCalculationResult(
            lines: $results,
            baseNet: $baseNetTotal->value(),
            lineDiscountNet: $lineDiscountTotal->value(),
            documentDiscountNet: $documentDiscountTotal->value(),
            net: $netTotal->value(),
            tax: $taxTotal->value(),
            gross: $grossTotal->value(),
        );
    }

    private function taxZeroReasonCode(?string $value, Decimal6 $taxRate): ?string
    {
        $value = $value === null ? null : strtoupper(trim($value));
        $value = $value === '' ? null : $value;

        if ($taxRate->isZero()) {
            if ($value === null) {
                throw new InvalidArgumentException('tax_zero_reason_code yüzde 0 vergi satırında zorunludur.');
            }

            if (preg_match('/^[A-Z0-9][A-Z0-9._-]{0,63}$/D', $value) !== 1) {
                throw new InvalidArgumentException('tax_zero_reason_code canonical bir kod olmalıdır.');
            }

            return $value;
        }

        if ($value !== null) {
            throw new InvalidArgumentException('tax_zero_reason_code yalnız yüzde 0 vergi satırında kullanılabilir.');
        }

        return null;
    }
}
