<?php

use App\Livewire\Pages\Auth\PeriodSelection;
use App\Livewire\Pages\Products\ProductForm;
use App\Livewire\Pages\Stock\TransferDetail;
use App\Livewire\Pages\Stock\WarehouseSlipDetail;
use App\Livewire\Production\RecipeCenter;
use App\Models\Period\Transfer;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('keeps production recipe editing with at least one recipe line', function (): void {
    $form = new RecipeCenter;
    $form->addLine();
    $form->addLine();
    expect($form->lines)->toHaveCount(2);
    $form->removeLine(0);
    expect($form->lines)->toHaveCount(1);
    $form->removeLine(0);
    expect($form->lines)->toHaveCount(1);
});

it('prevents editing transfer line quantities after the transfer leaves draft', function (): void {
    $form = new TransferDetail;
    $form->addLine();
    expect($form->draftLines)->toHaveCount(1);
    $form->transfer = new Transfer(['status' => 'sent']);
    expect(fn () => $form->addLine())->toThrow(HttpException::class);
    expect($form->draftLines)->toHaveCount(1);
});

it('supports warehouse slip draft line editing', function (): void {
    $form = new WarehouseSlipDetail;
    $form->addLine();
    expect($form->draftLines[0]['quantity'])->toBe('1.000');
    $form->removeLine(0);
    expect($form->draftLines)->toBe([]);
});

it('adds and removes configurator options without submitting a product', function (): void {
    $form = new ProductForm;
    $form->addConfigOptionRow();
    expect($form->configOptions)->toHaveCount(1);
    $form->removeConfigOptionRow(0);
    expect($form->configOptions)->toBe([]);
});

it('clears stale period selection when the selected company changes', function (): void {
    $page = new PeriodSelection;
    $page->companyId = 10;
    $page->periodId = 100;
    $page->updatedCompanyId();
    expect($page->periodId)->toBeNull();
});
