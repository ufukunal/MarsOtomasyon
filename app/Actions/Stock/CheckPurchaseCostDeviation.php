<?php

namespace App\Actions\Stock;

use App\DataObjects\CostDeviationWarning;
use App\Models\Company;
use App\Models\Period\Product;
use App\Models\Period\ProductCost;
use App\Support\Audit\AuditContext;
use App\Support\Period\PeriodContext;
use DomainException;

final class CheckPurchaseCostDeviation
{
    public function handle(
        int $productId,
        string $incomingUnitCost,
        bool $accepted = false,
    ): ?CostDeviationWarning {
        if (bccomp($incomingUnitCost, '0', 4) < 0) {
            throw new DomainException('Alış birim maliyeti negatif olamaz.');
        }

        $cost = ProductCost::query()
            ->where('product_id', $productId)
            ->first();

        if (! $cost || bccomp((string) $cost->moving_average, '0', 4) <= 0) {
            return null;
        }

        $referenceCost = (string) $cost->moving_average;
        $difference = bccomp($incomingUnitCost, $referenceCost, 4) >= 0
            ? bcsub($incomingUnitCost, $referenceCost, 8)
            : bcsub($referenceCost, $incomingUnitCost, 8);

        $deviation = bcdiv(
            bcmul($difference, '100', 8),
            $referenceCost,
            4,
        );

        $companyId = PeriodContext::companyId();

        if (! $companyId) {
            throw new DomainException('Maliyet sapması için aktif şirket bağlamı gereklidir.');
        }

        $company = Company::query()->findOrFail($companyId);
        $threshold = (string) $company->cost_deviation_threshold;

        if (bccomp($deviation, $threshold, 4) < 0) {
            return null;
        }

        $product = Product::query()->findOrFail($productId);
        $canViewCost = auth()->user()?->can('cost.view') ?? false;
        $message = $canViewCost
            ? sprintf(
                '%s için alış maliyeti %s, mevcut %s maliyetten %%%s sapıyor.',
                $product->code,
                bcadd($incomingUnitCost, '0', 4),
                bcadd($referenceCost, '0', 4),
                bcadd($deviation, '0', 2),
            )
            : sprintf(
                '%s için alış maliyeti mevcut maliyetten %%%s sapıyor.',
                $product->code,
                bcadd($deviation, '0', 2),
            );

        $warning = new CostDeviationWarning(
            deviationPercent: bcadd($deviation, '0', 4),
            message: $message,
        );

        if ($accepted) {
            PeriodContext::ensureWritable();

            AuditContext::period(
                'Alış maliyet sapma uyarısına rağmen işlem sürdürüldü.',
                [
                    'product_id' => $product->id,
                    'product_code' => $product->code,
                    'reference_cost' => bcadd($referenceCost, '0', 4),
                    'incoming_cost' => bcadd($incomingUnitCost, '0', 4),
                    'deviation_percent' => $warning->deviationPercent,
                    'threshold_percent' => bcadd($threshold, '0', 4),
                ],
                $product,
                'cost_deviation_accepted',
            );
        }

        return $warning;
    }
}
