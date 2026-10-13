<?php

it('preselects the originating active contact only when creating sales documents', function (): void {
    $editor = file_get_contents(base_path('app/Livewire/Sales/BaseSalesDocumentEditor.php'));
    $view = file_get_contents(base_path('resources/views/livewire/pages/contacts/contact-detail.blade.php'));

    expect($editor)->toContain("request()->query('cari')")
        ->toContain("where('is_active', true)")
        ->toContain('whereKey((int) $selectedCari)')
        ->toContain('if ($id !== null)')
        ->toContain('$this->loadDocument($document)')
        ->and($view)->toContain("route('sales.quotes.edit', ['cari' => \$contact->id])")
        ->toContain("route('sales.orders.edit', ['cari' => \$contact->id])")
        ->toContain("route('sales.invoices.edit', ['cari' => \$contact->id])");
});
