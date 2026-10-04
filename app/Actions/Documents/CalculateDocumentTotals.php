<?php

namespace App\Actions\Documents;

use App\DataObjects\Documents\DocumentLineCalculation;
use App\DataObjects\Documents\DocumentTotals;
use App\Support\Money\Money;
use DomainException;

final class CalculateDocumentTotals
{
    /**
     * @param  list<array{quantity:string,unit_price:string,line_discount_rate?:string,line_discount_amount?:string,vat_rate?:string}>  $lines
     */
    public function handle(array $lines, string $discountRate = '0', string $discountAmount = '0'): DocumentTotals
    {
        if ($lines === []) {
            throw new DomainException('Satırlı belge hesaplamasında en az bir satır zorunludur.');
        }

        $calculatedLines = [];
        $subtotal = Money::of('0');
        $groups = [];

        foreach ($lines as $line) {
            $quantity = bcadd($line['quantity'], '0', 3);
            $unitPrice = bcadd($line['unit_price'], '0', 4);
            $vatRate = bcadd($line['vat_rate'] ?? '0', '0', 4);

            if (bccomp($quantity, '0', 3) <= 0 || bccomp($unitPrice, '0', 4) < 0 || bccomp($vatRate, '0', 4) < 0) {
                throw new DomainException('Belge satırı miktar/fiyat/KDV değerleri geçersiz.');
            }

            $gross = Money::of($unitPrice)->times($quantity);
            [$lineRate, $lineDiscount] = $this->normalizeDiscount(
                $gross->amount,
                $line['line_discount_rate'] ?? '0',
                $line['line_discount_amount'] ?? '0',
            );

            if (bccomp($lineDiscount, $gross->amount, 4) > 0) {
                throw new DomainException('Satır iskontosu satır brüt tutarını aşamaz.');
            }

            $lineTotal = $gross->minus(Money::of($lineDiscount));
            $subtotal = $subtotal->plus($lineTotal);
            $groups[$vatRate] = Money::of($groups[$vatRate] ?? '0')->plus($lineTotal)->amount;

            $calculatedLines[] = new DocumentLineCalculation(
                gross: $gross->amount,
                discountRate: $lineRate,
                discountAmount: $lineDiscount,
                lineTotal: $lineTotal->amount,
                vatRate: $vatRate,
            );
        }

        [$documentRate, $documentDiscount] = $this->normalizeDiscount(
            $subtotal->amount,
            $discountRate,
            $discountAmount,
        );

        if (bccomp($documentDiscount, $subtotal->amount, 4) > 0) {
            throw new DomainException('Belge iskontosu subtotal tutarını aşamaz.');
        }

        $taxBase = $subtotal->minus(Money::of($documentDiscount));
        $vat = Money::of('0');
        $allocated = '0.0000000000';
        $groupKeys = array_keys($groups);
        $last = array_key_last($groupKeys);

        foreach ($groupKeys as $index => $rate) {
            $groupSubtotal = $groups[$rate];

            if (bccomp($subtotal->amount, '0', 4) === 0) {
                $share = '0.0000000000';
            } elseif ($index === $last) {
                $share = bcsub($documentDiscount, $allocated, 10);
            } else {
                $share = bcdiv(
                    bcmul($documentDiscount, $groupSubtotal, 10),
                    $subtotal->amount,
                    10,
                );
                $allocated = bcadd($allocated, $share, 10);
            }

            $groupBase = bcsub($groupSubtotal, $share, 10);
            $groupVat = Money::of($this->round(
                bcdiv(bcmul($groupBase, $rate, 10), '100', 10),
                2,
            ));
            $vat = $vat->plus($groupVat);
        }

        $exactGrand = $taxBase->plus($vat);
        $grandTotal = Money::of($this->round($exactGrand->amount, 2));
        $roundingDifference = $grandTotal->minus($exactGrand);

        return new DocumentTotals(
            lines: $calculatedLines,
            discountRate: $documentRate,
            discountAmount: $documentDiscount,
            subtotal: $subtotal->amount,
            taxBase: $taxBase->amount,
            vatAmount: $vat->amount,
            roundingDifference: $roundingDifference->amount,
            grandTotal: $grandTotal->amount,
        );
    }

    /** @return array{0:string,1:string} */
    private function normalizeDiscount(string $base, string $rate, string $amount): array
    {
        $rate = bcadd($rate, '0', 4);
        $amount = Money::of($amount)->amount;

        if (bccomp($rate, '0', 4) < 0 || bccomp($rate, '100', 4) > 0 || bccomp($amount, '0', 4) < 0) {
            throw new DomainException('İskonto yüzde/tutar değeri geçersiz.');
        }

        $rateProvided = bccomp($rate, '0', 4) !== 0;
        $amountProvided = bccomp($amount, '0', 4) !== 0;

        if ($rateProvided) {
            $expectedAmount = Money::of($base)->percent($rate)->amount;

            if ($amountProvided && bccomp($expectedAmount, $amount, 4) !== 0) {
                throw new DomainException('İskonto yüzde ve tutar değerleri birbiriyle uyumlu değil.');
            }

            return [$rate, $expectedAmount];
        }

        if ($amountProvided) {
            if (bccomp($base, '0', 4) === 0) {
                throw new DomainException('Sıfır tutarda iskonto tutarı kullanılamaz.');
            }

            $derivedRate = $this->round(
                bcdiv(bcmul($amount, '100', 10), $base, 10),
                4,
            );

            return [$derivedRate, $amount];
        }

        return ['0.0000', '0.0000'];
    }

    private function round(string $value, int $scale): string
    {
        $workScale = max($scale + 6, 10);
        $factor = bcpow('10', (string) $scale, 0);
        $scaled = bcmul($value, $factor, $workScale);
        $offset = bccomp($scaled, '0', $workScale) < 0 ? '-0.5' : '0.5';
        $integer = bcadd($scaled, $offset, 0);

        return bcadd(bcdiv($integer, $factor, $scale), '0', 4);
    }
}
