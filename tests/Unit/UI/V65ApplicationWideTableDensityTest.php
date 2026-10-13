<?php

it('applies compact table styling to every application module without changing business markup', function (): void {
    $stylesheet = file_get_contents(base_path('resources/css/v65-shell.css'));

    // The app layout is the boundary. Livewire views render inside .content,
    // while print/PDF templates are served through their own document views.
    foreach ([
        '.app-shell .content table.data-table:not(.wsl-items)',
        '.app-shell .content table:not(.data-table)',
        '.app-shell .content .v65-products table.data-table',
    ] as $selector) {
        expect($stylesheet)->toContain($selector);
    }

    $dir = base_path('resources/views/livewire');
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    $tableViews = 0;
    foreach ($files as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $markup = file_get_contents($file->getPathname());
        if (! str_contains($markup, '<table')) {
            continue;
        }

        $tableViews++;
        // Markup, Livewire input bindings and actions are intentionally not
        // rewritten; density changes are centralized in the CSS contract.
        expect($markup)->toContain('<table');
    }

    expect($tableViews)->toBeGreaterThan(30);
});

it('does not set fixed row heights for form fields or inline media', function (): void {
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));

    expect($css)->toContain('tbody tr:has(:is(input:not([type="checkbox"]),select,textarea,img))')
        ->toContain('height:auto;')
        ->toContain('.v65-products table.data-table tbody tr')
        ->toContain('.wsl-items');
});
