<?php

use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
use App\Support\Integrity\IntegrityRunner;

it('registers all 22 business integrity checks with executable result contracts', function (string $class): void {
    $reflection = new ReflectionClass($class);

    expect($reflection->implementsInterface(IntegrityCheck::class))->toBeTrue()
        ->and($reflection->getMethod('name')->isPublic())->toBeTrue()
        ->and($reflection->getMethod('run')->isPublic())->toBeTrue();
})->with([
    [\App\Support\Integrity\Checks\CarryIntegrityCheck::class],
    [\App\Support\Integrity\Checks\ChannelIntegrityCheck::class],
    [\App\Support\Integrity\Checks\ChannelOrderIntegrityCheck::class],
    [\App\Support\Integrity\Checks\ContactBalanceCheck::class],
    [\App\Support\Integrity\Checks\CostIntegrityCheck::class],
    [\App\Support\Integrity\Checks\DocumentTemplateIntegrityCheck::class],
    [\App\Support\Integrity\Checks\DocumentTotalCheck::class],
    [\App\Support\Integrity\Checks\FilesIntegrityCheck::class],
    [\App\Support\Integrity\Checks\FinanceIntegrityCheck::class],
    [\App\Support\Integrity\Checks\ImportIntegrityCheck::class],
    [\App\Support\Integrity\Checks\NumberSeriesCheck::class],
    [\App\Support\Integrity\Checks\PartialDocumentCheck::class],
    [\App\Support\Integrity\Checks\PrintProvenanceIntegrityCheck::class],
    [\App\Support\Integrity\Checks\ProductionIntegrityCheck::class],
    [\App\Support\Integrity\Checks\PurchaseMatchCheck::class],
    [\App\Support\Integrity\Checks\QuarantineBalanceCheck::class],
    [\App\Support\Integrity\Checks\RecipeIntegrityCheck::class],
    [\App\Support\Integrity\Checks\ReportPresetIntegrityCheck::class],
    [\App\Support\Integrity\Checks\ReservationBalanceCheck::class],
    [\App\Support\Integrity\Checks\ReturnIntegrityCheck::class],
    [\App\Support\Integrity\Checks\StockBalanceCheck::class],
    [\App\Support\Integrity\Checks\UnitIntegrityCheck::class],
]);

it('runs an injected integrity check without database persistence when explicitly requested', function (): void {
    $fixture = new class implements IntegrityCheck
    {
        public int $runs = 0;

        public function name(): string
        {
            return 'v4-disposable';
        }

        public function run(): IntegrityResult
        {
            $this->runs++;

            return new IntegrityResult(
                checked: 3,
                mismatches: [['id' => 2, 'expected' => '2', 'actual' => '3']],
                durationMs: 1,
            );
        }
    };

    $outcome = (new IntegrityRunner)->run($fixture, persist: false);

    expect($fixture->runs)->toBe(1)
        ->and($outcome->checked)->toBe(3)
        ->and($outcome->mismatchCount())->toBe(1)
        ->and($outcome->mismatches[0]['id'])->toBe(2);
});
