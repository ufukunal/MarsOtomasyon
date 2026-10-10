<?php

use App\Actions\Numbering\GenerateDocumentNumber;
use App\Exceptions\PeriodYearMismatchException;
use Tests\Support\IsolatedPostgres;

it('creates sequential document numbers inside the period database only', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $number = app(GenerateDocumentNumber::class);
        $first = $number->handle('sales_invoice', 2026);
        $second = $number->handle('sales_invoice', 2026);
        expect($first)->toEndWith('-2026-00001')
            ->and($second)->toEndWith('-2026-00002');
    });
});

it('rejects numbering in a year outside the active period', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        expect(fn () => app(GenerateDocumentNumber::class)->handle('sales_invoice', 2025))
            ->toThrow(PeriodYearMismatchException::class);
    });
});
