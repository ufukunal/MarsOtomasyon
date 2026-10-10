<?php

use App\Support\Integrity\Checks\CarryIntegrityCheck;
use App\Support\Integrity\Checks\ChannelIntegrityCheck;
use App\Support\Integrity\Checks\ChannelOrderIntegrityCheck;
use App\Support\Integrity\Checks\ContactBalanceCheck;
use App\Support\Integrity\Checks\CostIntegrityCheck;
use App\Support\Integrity\Checks\DocumentTemplateIntegrityCheck;
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
use App\Support\Integrity\Checks\ReportPresetIntegrityCheck;
use App\Support\Integrity\Checks\ReservationBalanceCheck;
use App\Support\Integrity\Checks\ReturnIntegrityCheck;
use App\Support\Integrity\Checks\StockBalanceCheck;
use App\Support\Integrity\Checks\UnitIntegrityCheck;
use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
use App\Support\Integrity\IntegrityRunner;

it('registers all 22 business integrity checks with executable result contracts', function (string $class): void {
    $reflection = new ReflectionClass($class);

    expect($reflection->implementsInterface(IntegrityCheck::class))->toBeTrue()
        ->and($reflection->getMethod('name')->isPublic())->toBeTrue()
        ->and($reflection->getMethod('run')->isPublic())->toBeTrue();
})->with([
    [CarryIntegrityCheck::class],
    [ChannelIntegrityCheck::class],
    [ChannelOrderIntegrityCheck::class],
    [ContactBalanceCheck::class],
    [CostIntegrityCheck::class],
    [DocumentTemplateIntegrityCheck::class],
    [DocumentTotalCheck::class],
    [FilesIntegrityCheck::class],
    [FinanceIntegrityCheck::class],
    [ImportIntegrityCheck::class],
    [NumberSeriesCheck::class],
    [PartialDocumentCheck::class],
    [PrintProvenanceIntegrityCheck::class],
    [ProductionIntegrityCheck::class],
    [PurchaseMatchCheck::class],
    [QuarantineBalanceCheck::class],
    [RecipeIntegrityCheck::class],
    [ReportPresetIntegrityCheck::class],
    [ReservationBalanceCheck::class],
    [ReturnIntegrityCheck::class],
    [StockBalanceCheck::class],
    [UnitIntegrityCheck::class],
]);

it('runs an injected integrity check without database persistence when explicitly requested', function (): void {
    $fixture = Mockery::mock(IntegrityCheck::class);
    $fixture->shouldReceive('run')->once()->andReturn(new IntegrityResult(
        checked: 3,
        mismatches: [['id' => 2, 'expected' => '2', 'actual' => '3']],
        durationMs: 1,
    ));

    $outcome = (new IntegrityRunner)->run($fixture, persist: false);

    expect($outcome->checked)->toBe(3)
        ->and($outcome->mismatchCount())->toBe(1)
        ->and($outcome->mismatches[0]['id'])->toBe(2);
});
