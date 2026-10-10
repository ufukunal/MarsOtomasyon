<?php

use App\Livewire\Imports\ImportCenter;
use App\Livewire\Reporting\ReportCenter;
use App\Livewire\Returns\ReturnCenter;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('bounds report pagination to the supported page sizes without querying data', function (): void {
    $page = new ReportCenter;
    $page->page = 8;
    $page->setPerPage(25);
    expect($page->perPage)->toBe(25)
        ->and($page->page)->toBe(1);

    expect(fn () => $page->setPerPage(500))->toThrow(HttpException::class);
    expect(fn () => $page->goToPage(0))->toThrow(HttpException::class);
    expect($page->perPage)->toBe(25);
});

it('clears old filters and resets pagination for a new report query', function (): void {
    $page = new ReportCenter;
    $page->filterValues = ['status' => 'posted'];
    $page->page = 9;
    $page->queueMessage = 'previous';

    $page->clearFilters();

    expect($page->filterValues)->toBe([])
        ->and($page->page)->toBe(1)
        ->and($page->queueMessage)->toBeNull();
});

it('resets imported shipment carry selection when the source period changes', function (): void {
    $page = new ImportCenter;
    $page->carrySourceImportFileId = 123;
    $page->updatedCarrySourcePeriodId();
    expect($page->carrySourceImportFileId)->toBeNull();
});

it('clears previously selected return quantities when switching return mode', function (): void {
    $page = new ReturnCenter;
    $page->sourceInvoiceId = 1;
    $page->selectedReturnId = 5;
    $page->quantities = [101 => '2.000'];
    $page->locationIds = [101 => 9];

    $page->updatedType();

    expect($page->sourceInvoiceId)->toBeNull()
        ->and($page->selectedReturnId)->toBeNull()
        ->and($page->quantities)->toBe([])
        ->and($page->locationIds)->toBe([]);
});
