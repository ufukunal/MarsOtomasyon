<?php

namespace App\Support\Production;

use DomainException;

final class ProductionCostCalculator
{
    /** @param list<array{quantity:string,unit_cost:string}> $rows */
    public function materialCost(array $rows): string
    {
        $total = '0.0000';

        foreach ($rows as $row) {
            $quantity = bcadd($row['quantity'], '0', 3);
            $unitCost = bcadd($row['unit_cost'], '0', 4);

            if (bccomp($quantity, '0', 3) < 0 || bccomp($unitCost, '0', 4) < 0) {
                throw new DomainException('Production cost satır miktarı/maliyeti negatif olamaz.');
            }

            $total = bcadd(
                $total,
                bcmul($quantity, $unitCost, 8),
                4,
            );
        }

        return $total;
    }

    public function unitCost(string $materialCost, string $serviceCost, string $completedQuantity): string
    {
        $total = bcadd($materialCost, $serviceCost, 4);
        $quantity = bcadd($completedQuantity, '0', 3);

        if (bccomp($quantity, '0', 3) <= 0 || bccomp($total, '0', 4) < 0) {
            throw new DomainException('Production unit cost girdileri geçersiz.');
        }

        return bcdiv($total, $quantity, 4);
    }

    public function projectedAverage(
        string $currentQuantity,
        string $currentAverage,
        string $incomingQuantity,
        string $incomingUnitCost,
    ): string {
        $currentQuantity = bcadd($currentQuantity, '0', 3);
        $incomingQuantity = bcadd($incomingQuantity, '0', 3);

        if (bccomp($incomingQuantity, '0', 3) <= 0) {
            throw new DomainException('Production output miktarı pozitif olmalıdır.');
        }

        if (bccomp($currentQuantity, '0', 3) <= 0) {
            return bcadd($incomingUnitCost, '0', 4);
        }

        $currentValue = bcmul($currentQuantity, $currentAverage, 8);
        $incomingValue = bcmul($incomingQuantity, $incomingUnitCost, 8);

        return bcdiv(
            bcadd($currentValue, $incomingValue, 8),
            bcadd($currentQuantity, $incomingQuantity, 3),
            4,
        );
    }
}
