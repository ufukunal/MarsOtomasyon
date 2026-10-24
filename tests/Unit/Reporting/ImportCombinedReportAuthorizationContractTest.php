<?php

use App\Support\Reporting\Queries\ImportConversionReportQuery;

it('requires sales and stock permission for combined import metrics in both report access paths', function (): void {
    $definition = (new ImportConversionReportQuery)->definition();
    $columns = array_map(static fn ($column): string => $column->key, $definition->columns);

    expect($definition->key)->toBe('imports.conversion')
        ->and($columns)->toContain('cumulative_sales', 'cumulative_fire', 'cumulative_scrap');

    foreach ([
        'app/Support/Reporting/ReportEngine.php',
        'app/Support/Reporting/ReportCatalog.php',
    ] as $path) {
        $source = file_get_contents(base_path($path));

        expect($source)->toContain("['imports.sales', 'imports.conversion']")
            ->toContain("['imports.loss', 'imports.conversion']");
    }
});
