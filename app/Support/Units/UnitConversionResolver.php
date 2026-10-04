<?php

namespace App\Support\Units;

use App\Models\Period\UnitConversion;
use DomainException;

final class UnitConversionResolver
{
    public function factor(int $fromUnitId, int $toUnitId): string
    {
        if ($fromUnitId === $toUnitId) {
            return '1.000000';
        }

        $direct = UnitConversion::query()
            ->where('from_unit_id', $fromUnitId)
            ->where('to_unit_id', $toUnitId)
            ->value('factor');

        if ($direct !== null) {
            return bcadd((string) $direct, '0', 6);
        }

        $reverse = UnitConversion::query()
            ->where('from_unit_id', $toUnitId)
            ->where('to_unit_id', $fromUnitId)
            ->value('factor');

        if ($reverse !== null) {
            $quotient = bcdiv('1', (string) $reverse, 7);

            return bcadd($quotient, '0.0000005', 6);
        }

        throw new DomainException('Birim dönüşümü tanımlı değil.');
    }
}
