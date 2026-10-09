<?php

use App\Actions\Production\SaveProductionOrderDraft;
use App\Exceptions\PeriodYearMismatchException;
use App\Models\Period\ProductionOrder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

beforeEach(function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2PRODVAL');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);
});

it('v2 production refuses planned output of zero or less before saving work order', function (string $amount) {
    expect(fn () => app(SaveProductionOrderDraft::class)->handle([
        'document_date' => '2026-09-01', 'product_id' => 1, 'recipe_id' => 1,
        'planned_quantity' => $amount,
    ]))->toThrow(DomainException::class);
    expect(ProductionOrder::query()->count())->toBe(0);
})->with(['0', '-0.001']);

it('v2 production refuses creation in a different accounting year before saving work order', function () {
    expect(fn () => app(SaveProductionOrderDraft::class)->handle([
        'document_date' => '2025-09-01', 'planned_quantity' => '1.000',
    ]))->toThrow(PeriodYearMismatchException::class);
    expect(ProductionOrder::query()->count())->toBe(0);
});

it('v2 production refuses missing output product even with otherwise positive amount', function () {
    expect(fn () => app(SaveProductionOrderDraft::class)->handle([
        'document_date' => '2026-09-01', 'product_id' => 999999,
        'recipe_id' => 1, 'planned_quantity' => '1.000',
    ]))->toThrow(ModelNotFoundException::class);
    expect(ProductionOrder::query()->count())->toBe(0);
});

it('v2 unsaved production work order computes remaining output exactly', function () {
    $order = new ProductionOrder([
        'planned_quantity' => '10.000', 'completed_quantity' => '3.750', 'cancelled_quantity' => '1.250',
    ]);
    expect($order->remainingQuantity())->toBe('5.000');
});
