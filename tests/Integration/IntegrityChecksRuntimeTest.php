<?php

use App\Support\Integrity\IntegrityResult;
use Tests\Support\IsolatedPostgres;

it('executes each integrity checker over a disposable, freshly migrated accounting period', function (string $class): void {
    IsolatedPostgres::withActivePeriod(function () use ($class): void {
        $outcome = app($class)->run();

        expect($outcome)->toBeInstanceOf(IntegrityResult::class)
            ->and($outcome->checked)->toBeGreaterThanOrEqual(0)
            ->and($outcome->mismatchCount())->toBeGreaterThanOrEqual(0);
    });
})->with([
    'CarryIntegrityCheck' => ['App\\Support\\Integrity\\Checks\\CarryIntegrityCheck'],
    'ChannelIntegrityCheck' => ['App\\Support\\Integrity\\Checks\\ChannelIntegrityCheck'],
    'ChannelOrderIntegrityCheck' => ['App\\Support\\Integrity\\Checks\\ChannelOrderIntegrityCheck'],
    'ContactBalanceCheck' => ['App\\Support\\Integrity\\Checks\\ContactBalanceCheck'],
    'CostIntegrityCheck' => ['App\\Support\\Integrity\\Checks\\CostIntegrityCheck'],
    'DocumentTemplateIntegrityCheck' => ['App\\Support\\Integrity\\Checks\\DocumentTemplateIntegrityCheck'],
    'DocumentTotalCheck' => ['App\\Support\\Integrity\\Checks\\DocumentTotalCheck'],
    'FilesIntegrityCheck' => ['App\\Support\\Integrity\\Checks\\FilesIntegrityCheck'],
    'FinanceIntegrityCheck' => ['App\\Support\\Integrity\\Checks\\FinanceIntegrityCheck'],
    'ImportIntegrityCheck' => ['App\\Support\\Integrity\\Checks\\ImportIntegrityCheck'],
    'NumberSeriesCheck' => ['App\\Support\\Integrity\\Checks\\NumberSeriesCheck'],
    'PartialDocumentCheck' => ['App\\Support\\Integrity\\Checks\\PartialDocumentCheck'],
    'PrintProvenanceIntegrityCheck' => ['App\\Support\\Integrity\\Checks\\PrintProvenanceIntegrityCheck'],
    'ProductionIntegrityCheck' => ['App\\Support\\Integrity\\Checks\\ProductionIntegrityCheck'],
    'PurchaseMatchCheck' => ['App\\Support\\Integrity\\Checks\\PurchaseMatchCheck'],
    'QuarantineBalanceCheck' => ['App\\Support\\Integrity\\Checks\\QuarantineBalanceCheck'],
    'RecipeIntegrityCheck' => ['App\\Support\\Integrity\\Checks\\RecipeIntegrityCheck'],
    'ReportPresetIntegrityCheck' => ['App\\Support\\Integrity\\Checks\\ReportPresetIntegrityCheck'],
    'ReservationBalanceCheck' => ['App\\Support\\Integrity\\Checks\\ReservationBalanceCheck'],
    'ReturnIntegrityCheck' => ['App\\Support\\Integrity\\Checks\\ReturnIntegrityCheck'],
    'StockBalanceCheck' => ['App\\Support\\Integrity\\Checks\\StockBalanceCheck'],
    'UnitIntegrityCheck' => ['App\\Support\\Integrity\\Checks\\UnitIntegrityCheck'],
]);
