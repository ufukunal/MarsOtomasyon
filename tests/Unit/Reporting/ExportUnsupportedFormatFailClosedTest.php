<?php

use App\Support\Reporting\Export\ReportExportDatasetFactory;
use App\Support\Reporting\ReportRequest;

it('rejects unsupported export formats before resolving reports or running any SQL', function (string $format): void {
    $factory = (new ReflectionClass(ReportExportDatasetFactory::class))->newInstanceWithoutConstructor();

    expect(fn () => $factory->make('sales.invoices', new ReportRequest, $format))
        ->toThrow(DomainException::class);
})->with([
    'HTML' => ['html'],
    'command' => ['shell'],
    'extension' => ['../csv'],
    'blank' => [''],
    'docx' => ['docx'],
]);

it('rejects invalid report sort directions before opening an export query', function (string $direction): void {
    expect(fn () => new \App\Support\Reporting\ReportSort('total', $direction))
        ->toThrow(DomainException::class);
})->with(['up', 'down', 'ASC;DELETE', 'ascending']);
