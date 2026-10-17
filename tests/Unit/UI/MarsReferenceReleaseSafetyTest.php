<?php

it('does not expose contact titles in the document edit center without contacts.view', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/pages/documents/document-edit-center.blade.php'));
    expect($view)->toContain("auth()->user()?->can('contacts.view')")
        ->toContain('Görüntüleme yetkisi yok');
});

it('checks and migrates every required Fresh UAT period column before web promotion', function (): void {
    $migrate = file_get_contents(base_path('ops/uat-fresh-period-migrations.sh'));
    $promotion = file_get_contents(base_path('ops/uat-fresh-web-update.sh'));
    foreach ([
        '0001_12_02_000010_add_document_additional_discount.php',
        '0001_12_02_000020_create_contact_transfers.php',
        '0001_12_02_000030_add_document_ui_meta.php',
        '0001_12_02_000040_add_contact_transfer_reference.php',
    ] as $migration) {
        expect(substr_count($migrate, $migration))->toBe(2);
    }
    foreach (["column_name='additional_discount_amount'", "column_name='ui_meta'", "column_name='reference'"] as $column) {
        expect($migrate)->toContain($column);
        expect($promotion)->toContain($column);
    }
    expect($promotion)->toContain("Do not deploy web only.")
        ->and($migrate)->toContain('FRESH_PERIOD_MIGRATIONS=PASS');
});
