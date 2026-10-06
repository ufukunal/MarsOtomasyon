<?php

namespace App\Support\Operations;

use App\Support\Integrity\Checks\ChannelIntegrityCheck;
use App\Support\Integrity\Checks\ChannelOrderIntegrityCheck;
use App\Support\Integrity\Checks\ContactBalanceCheck;
use App\Support\Integrity\Checks\CostIntegrityCheck;
use App\Support\Integrity\Checks\DocumentTotalCheck;
use App\Support\Integrity\Checks\FilesIntegrityCheck;
use App\Support\Integrity\Checks\FinanceIntegrityCheck;
use App\Support\Integrity\Checks\ImportIntegrityCheck;
use App\Support\Integrity\Checks\NumberSeriesCheck;
use App\Support\Integrity\Checks\PartialDocumentCheck;
use App\Support\Integrity\Checks\PrintProvenanceIntegrityCheck;
use App\Support\Integrity\Checks\ProductionIntegrityCheck;
use App\Support\Integrity\Checks\PurchaseMatchCheck;
use App\Support\Integrity\Checks\QuarantineBalanceCheck;
use App\Support\Integrity\Checks\RecipeIntegrityCheck;
use App\Support\Integrity\Checks\ReservationBalanceCheck;
use App\Support\Integrity\Checks\ReturnIntegrityCheck;
use App\Support\Integrity\Checks\StockBalanceCheck;
use App\Support\Integrity\Checks\UnitIntegrityCheck;
use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityRunner;
use RuntimeException;

final class ArchivePeriodIntegrityVerifier
{
    public function __construct(private readonly IntegrityRunner $runner) {}

    public function verify(): int
    {
        $checked = 0;
        $mismatches = 0;

        foreach ($this->checks() as $checkClass) {
            $result = $this->runner->run(app($checkClass), false);
            $checked += $result->checked;
            $mismatches += $result->mismatchCount();
        }

        if ($mismatches > 0) {
            throw new RuntimeException(
                "Archive restore integrity doğrulaması başarısız: {$mismatches} mismatch."
            );
        }

        return $checked;
    }

    /** @return list<class-string<IntegrityCheck>> */
    private function checks(): array
    {
        return [
            StockBalanceCheck::class,
            CostIntegrityCheck::class,
            ReservationBalanceCheck::class,
            QuarantineBalanceCheck::class,
            UnitIntegrityCheck::class,
            DocumentTotalCheck::class,
            PartialDocumentCheck::class,
            PurchaseMatchCheck::class,
            ContactBalanceCheck::class,
            FinanceIntegrityCheck::class,
            ReturnIntegrityCheck::class,
            ImportIntegrityCheck::class,
            RecipeIntegrityCheck::class,
            ProductionIntegrityCheck::class,
            ChannelIntegrityCheck::class,
            ChannelOrderIntegrityCheck::class,
            PrintProvenanceIntegrityCheck::class,
            NumberSeriesCheck::class,
            FilesIntegrityCheck::class,
        ];
    }
}
