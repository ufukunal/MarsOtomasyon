<?php

it('keeps the finance operation center Expense enum references valid for Blade PHP compilation', function (): void {
    $blade = file_get_contents(base_path('resources/views/livewire/finance/finance-operation-center.blade.php'));

    expect(substr_count($blade, '\\App\\Enums\\DocumentType::Expense'))->toBe(2)
        ->and($blade)->not->toContain('\\App\\Enums\\\\DocumentType::Expense');
});

it('prints the real PHP lint error and originating Blade view on failed Fresh image verification', function (): void {
    $script = file_get_contents(base_path('ops/uat-fresh-web-update.sh'));

    expect($script)->toContain('if ! php -l "$compiled"; then')
        ->toContain("grep -E 'PATH .+ ENDPATH'")
        ->toContain('IMAGE_CHECK_FAIL compiled Blade syntax:');
});
