<?php

it('shows a permission-restricted TRY invoice risk summary without double-counting posted documents', function (): void {
    $editor = file_get_contents(base_path('app/Livewire/Sales/SalesInvoiceEditor.php'));
    $panel = file_get_contents(base_path('resources/views/components/v65/contact-risk-overview.blade.php'));
    $view = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));

    expect($editor)->toContain("auth()->user()?->can('contacts.view')")
        ->toContain("'is_posted' =>")
        ->toContain("status !== 'draft'")
        ->toContain('bcadd($balance, $amount, 4)')
        ->and($panel)->toContain('Cari Finansal Durum (TRY)')
        ->toContain('Cari borçlu')
        ->toContain('Cari alacaklı')
        ->toContain('teminat ve diğer risk unsurları')
        ->and($view)->toContain('<x-v65.contact-risk-overview');
});

it('exposes discount amounts and clearly provisional line VAT without claiming final allocated VAT', function (): void {
    $component = file_get_contents(base_path('resources/views/components/v65/document-line-amounts.blade.php'));

    expect($component)->toContain('$calculation?->discountAmount')
        ->toContain("['line_discount_amount']")
        ->toContain('Money::of((string) $net)->percent(');

    foreach ([
        'resources/views/livewire/sales/document-editor.blade.php',
        'resources/views/livewire/purchases/document-editor.blade.php',
    ] as $path) {
        $view = file_get_contents(base_path($path));
        expect($view)->toContain('İskonto Tutarı')
            ->toContain('Satır KDV*')
            ->toContain('Satır Net / Matrah')
            ->toContain('<x-v65.document-line-amounts')
            ->toContain('belge genel iskontosu uygulanmadan önceki');
    }
});

it('uses the shared confirmation modal for document approval, posting and reversals', function (): void {
    $component = file_get_contents(base_path('resources/views/components/v65/document-confirm.blade.php'));
    $modal = file_get_contents(base_path('resources/views/components/v65/modal.blade.php'));

    expect($component)->toContain("['post', 'reverse', 'confirm', 'approve', 'submitApproval', 'cancelRemaining']")
        ->toContain('data-v65-modal-open')
        ->toContain('wire:click="{{ $action }}"')
        ->toContain('wire:loading.attr="disabled"')
        ->toContain('data-v65-modal-close')
        ->toContain(':disabled="$dirty"')
        ->toContain("@error('document')")
        ->toContain('Son kaydedilen toplam')
        ->and($modal)->toContain('aria-labelledby');

    foreach ([
        'resources/views/livewire/sales/document-editor.blade.php',
        'resources/views/livewire/purchases/document-editor.blade.php',
    ] as $path) {
        expect(file_get_contents(base_path($path)))->toContain('<x-v65.document-confirm')
            ->toContain(':dirty="$documentDirty"')
            ->toContain('action="post"')
            ->toContain('action="reverse"');
    }
});

it('provides per-field error messages, deterministic invalid focus and search feedback', function (): void {
    $js = file_get_contents(base_path('resources/js/app.js'));
    $errorComponent = file_get_contents(base_path('resources/views/components/v65/input-error.blade.php'));

    expect($errorComponent)->toContain('@error($field)')
        ->toContain('role="alert"')
        ->and($js)->toContain("Livewire.hook('morphed'")
        ->toContain('focusedDocumentErrors')
        ->toContain('[aria-invalid="true"]');

    foreach ([
        'app/Livewire/Sales/BaseSalesDocumentEditor.php',
        'app/Livewire/Purchases/BasePurchaseDocumentEditor.php',
    ] as $path) {
        expect(file_get_contents(base_path($path)))->toContain('$this->validate([')
            ->toContain("'lines.*.quantity'")
            ->toContain("'lines.*.vat_rate'");
    }

    foreach ([
        'resources/views/livewire/sales/document-editor.blade.php',
        'resources/views/livewire/purchases/document-editor.blade.php',
    ] as $path) {
        $view = file_get_contents(base_path($path));
        expect($view)->toContain('<x-v65.input-error')
            ->toContain('data-v65-document-errors')
            ->toContain('aria-invalid="{{ $errors->has(')
            ->toContain('Ürün bulunamadı')
            ->toContain('Aramaya uygun cari bulunamadı')
            ->toContain('v65-doc-mobile-scroll-hint');
    }
});

it('uses the current purchase currency for an unsaved financial preview', function (): void {
    $summary = file_get_contents(base_path('resources/views/components/v65/document-summary.blade.php'));
    $purchase = file_get_contents(base_path('resources/views/livewire/purchases/document-editor.blade.php'));

    expect($summary)->toContain("'currency' => null")
        ->toContain('$currency ?: ($document?->currency ?:')
        ->and($purchase)->toContain(':currency="$currency"')
        ->toContain('wire:model.blur="currency"');
});
