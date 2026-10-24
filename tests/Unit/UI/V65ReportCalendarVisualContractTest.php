<?php

it('renders a responsive cash-flow calendar without removing the canonical report table', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/reporting/report-center.blade.php'));
    $css = file_get_contents(base_path('resources/css/v65-reporting.css'));
    $layout = file_get_contents(base_path('resources/views/layouts/app.blade.php'));
    $vite = file_get_contents(base_path('vite.config.js'));

    expect($view)->toContain("@if(\$selectedReport->key === 'finance.cash_flow_calendar' && \$result->rows !== [])")
        ->toContain('v65-cashflow-calendar')
        ->toContain('v65-cashflow-grid')
        ->toContain('v65-cashflow-day-closing')
        ->toContain("cash_alert")
        ->toContain('Nakit açığı')
        ->toContain('<table class="data-table report-table">')
        ->and($css)->toContain('.app-shell .v65-cashflow-grid')
        ->toContain('@media (max-width: 480px)')
        ->toContain('@media print')
        ->and($layout)->toContain("'resources/css/v65-reporting.css'")
        ->and($vite)->toContain("'resources/css/v65-reporting.css'");
});

it('includes all theme stylesheet entrypoints in the production Vite manifest inputs', function (): void {
    $vite = file_get_contents(base_path('vite.config.js'));
    $layout = file_get_contents(base_path('resources/views/layouts/app.blade.php'));

    foreach (['v65-reporting.css', 'v65-themes.css'] as $style) {
        expect($vite)->toContain('resources/css/'.$style)
            ->and($layout)->toContain('resources/css/'.$style);
    }
});
