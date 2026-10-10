<?php

use App\Support\Reporting\Export\ReportExportDataset;
use App\Support\Reporting\ReportColumnDefinition;

it('holds immutable export rows, filters and dataset version', function (): void {
    $data = new ReportExportDataset('sales', 'Sales', [new ReportColumnDefinition('amount', 'Amount')],
        [['amount' => '10.00']], ['amount' => '10.00'], 1, ['year' => 2026], [], 3);
    expect($data->rows[0]['amount'])->toBe('10.00')
        ->and($data->totalRows)->toBe(1)
        ->and($data->definitionVersion)->toBe(3);
});
