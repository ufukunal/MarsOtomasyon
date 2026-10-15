<?php

it('separates persisted document currency from unsaved purchasing preview currency', function (): void {
    $summary = file_get_contents(base_path('resources/views/components/v65/document-summary.blade.php'));

    expect($summary)->toContain('Never pair persisted totals with an unsaved currency selection.')
        ->toContain('$isPreview')
        ->toContain(': ($document?->currency ?: ($currency ?:');
});

it('preserves document export decimals instead of converting them to PHP float', function (): void {
    $list = file_get_contents(base_path('app/Livewire/Concerns/WithV65DocumentList.php'));

    expect($list)->toContain("setCellValueExplicit('E'.\$i, (string) \$document->grand_total, DataType::TYPE_STRING)")
        ->not->toContain('(float) $document->grand_total');
});

it('redacts linked source-document numbers from users lacking the source permission', function (): void {
    $sources = file_get_contents(base_path('resources/views/components/v65/document-sources.blade.php'));
    $controller = file_get_contents(base_path('app/Http/Controllers/PrintBusinessDocumentController.php'));
    $print = file_get_contents(base_path('resources/views/printing/business-document.blade.php'));

    expect($sources)->toContain('if (! $source || ! $routeAndPermission || ! auth()->user()?->can(')
        ->toContain('$visibleSources->isNotEmpty()')
        ->and($controller)->toContain('if ($permission === null || ! auth()->user()?->can($permission))')
        ->toContain("'sourceReferences' => \$sourceReferences")
        ->and($print)->toContain('$sourceReferences->isNotEmpty()')
        ->not->toContain('$document->incomingRelations as $relation');
});

it('prevents unapproved CI test runs and one-off Pint branch creation', function (): void {
    $quality = file_get_contents(base_path('.github/workflows/v65-source-quality.yml'));
    $isolated = file_get_contents(base_path('.github/workflows/v65-isolated-test-run.yml'));

    expect($quality)->toContain('workflow_dispatch:')->not->toContain("\n  push:");
    expect($isolated)->toContain('workflow_dispatch:')
        ->toContain('confirm_isolated_test == true')
        ->not->toContain("\n  push:");
    expect(file_exists(base_path('.github/workflows/v65-pint-repair.yml')))->toBeFalse();
});
