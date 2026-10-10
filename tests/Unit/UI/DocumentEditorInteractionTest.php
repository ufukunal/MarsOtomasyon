<?php

use App\Livewire\Sales\SalesOrderEditor;
use App\Livewire\Purchases\PurchaseOrderEditor;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('updates all sale line VAT percentages and resets them without a database', function (): void {
    $page = new SalesOrderEditor;
    $page->lines = [
        ['vat_rate' => '0', 'quantity' => '2'],
        ['vat_rate' => '10', 'quantity' => '1'],
    ];
    $page->bulkVatRate = '20';
    $page->applyVatToAll();
    expect(array_column($page->lines, 'vat_rate'))->toBe(['20.0000', '20.0000']);
    $page->clearVat();
    expect(array_column($page->lines, 'vat_rate'))->toBe(['0.0000', '0.0000']);
});

it('blocks invalid VAT percentages in both sales and purchasing editors', function (string $class, string $rate): void {
    $page = new $class;
    $page->bulkVatRate = $rate;
    $page->lines = [['vat_rate' => '20']];
    expect(fn () => $page->applyVatToAll())->toThrow(HttpException::class);
    expect($page->lines[0]['vat_rate'])->toBe('20');
})->with([
    [SalesOrderEditor::class, '-1'],
    [SalesOrderEditor::class, '101'],
    [PurchaseOrderEditor::class, '-0.0001'],
    [PurchaseOrderEditor::class, '101'],
]);

it('keeps purchase line arithmetic and initial form shape deterministic', function (): void {
    $page = new PurchaseOrderEditor;
    $page->addLine();
    expect($page->lines)->toHaveCount(1)
        ->and($page->lines[0]['quantity'])->toBe('1.000');
    $page->bulkVatRate = '10';
    $page->applyVatToAll();
    expect($page->lines[0]['vat_rate'])->toBe('10.0000');
    $page->removeLine(0);
    expect($page->lines)->toBe([]);
});
