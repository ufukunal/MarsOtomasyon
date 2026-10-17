<?php

it('defines precisely the eight requested purchasing and selling screen variants', function (): void {
    $screens = require base_path('config/document_prototype.php');

    expect(array_keys($screens))->toBe([
        'quote', 'sales_order', 'dispatch', 'sales_invoice',
        'purchase_quote', 'purchase_order', 'purchase_dispatch', 'supplier_invoice',
    ]);

    foreach ($screens as $key => $screen) {
        foreach (['title', 'description', 'save', 'date', 'party', 'price', 'hint', 'stages'] as $field) {
            expect($screen)->toHaveKey($field);
        }

        expect(count($screen['stages']))->toBe(3);
    }
});

it('uses the same functional form, header, empty entry line and totals on both sides', function (): void {
    foreach (['sales', 'purchases'] as $side) {
        $blade = file_get_contents(base_path("resources/views/livewire/{$side}/document-editor.blade.php"));

        expect($blade)->toContain('v65-reference-info-fields')
            ->toContain('v65-reference-totals-layout')
            ->toContain('<x-v65.document-prototype-heading')
            ->toContain('<x-v65.document-prototype-toolbar')
            ->toContain('<x-v65.document-prototype-footer')
            ->toContain('v65-reference-entry-row')
            ->toContain('data-v65-fast-product-search')
            ->toContain('wire:model.live="uiMeta.default_location_id"')
            ->toContain('wire:submit="save"')
            ->toContain(':compact="true"');

        $head = [];
        expect(preg_match('/<table class="data-table v65-doc-table v65-prototype-lines">\\s*<thead><tr>(.*?)<\\/tr><\\/thead>/s', $blade, $head))->toBe(1);
        expect(substr_count($head[1], '<th'))->toBe(10);
    }
});

it('keeps the four purchase screen-specific fields and source relations', function (): void {
    $blade = file_get_contents(base_path('resources/views/livewire/purchases/document-editor.blade.php'));

    foreach ([
        'Teklif Geçerlilik', 'Tedarikçi Teklif No',
        'Beklenen Teslim', 'Kabul Deposu', 'Kaynak Satınalma Siparişi',
        'Tedarikçi İrsaliye No', 'Tedarikçi Fatura No',
        'Kaynak Mal Kabul', 'Vade Tarihi',
    ] as $label) {
        expect($blade)->toContain($label);
    }

    // An incoming relation is presented, never fabricated from unsaved UI metadata.
    expect($blade)->toContain('<x-v65.document-sources :document="$document" />')
        ->toContain('Mal Kabul Kalemleri')
        ->toContain('instanceof \\App\\Livewire\\Purchases\\GoodsReceiptEditor')
        ->toContain('@if($isGoodsReceipt)');
});

it('maintains the four sales screen-specific fields and real linked source references', function (): void {
    $blade = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));

    foreach ([
        'Teklif Geçerlilik Tarihi', 'Teslim Yeri', 'Müşteri Referans No',
        'Beklenen Sevk', 'Sevkiyat Deposu', 'Kaynak Satış Siparişi',
        'Kaynak Sevk / İrsaliye', 'Vade Tarihi',
    ] as $label) {
        expect($blade)->toContain($label);
    }

    expect($blade)->toContain('<x-v65.document-sources :document="$document" />');
});

it('does not post mock products or reset already saved financial documents', function (): void {
    $trait = file_get_contents(base_path('app/Livewire/Concerns/WithPrototypeDocumentControls.php'));
    $js = file_get_contents(base_path('resources/js/app.js'));
    $summary = file_get_contents(base_path('resources/views/components/v65/document-summary.blade.php'));

    expect($trait)->toContain("Product::query()->where('is_active', true)")
        ->toContain("abort_unless(\$this->document === null, 409)")
        ->toContain("abort_unless(\$this->document === null || \$this->document->status === 'draft', 409)")
        ->toContain("->limit(12)->get()")
        ->toContain("public function isPrototypeEntryRow")
        ->and($js)->toContain("data-v65-reference-quick-input")
        ->toContain('data-v65-fast-product-search')
        ->and($summary)->toContain('wire:model.blur="additionalDiscountAmount"')
        ->toContain('@unless($compact)')
        ->toContain('Belge Toplamları');
});

it('keeps sale and purchase posting actions separate from presentation settings', function (): void {
    foreach (['Sales', 'Purchases'] as $side) {
        $name = $side === 'Sales' ? 'BaseSalesDocumentEditor' : 'BasePurchaseDocumentEditor';
        $class = file_get_contents(base_path("app/Livewire/{$side}/{$name}.php"));
        expect($class)->toContain('use WithPrototypeDocumentControls;')
            ->toContain('use WithFastDocumentLines;')
            ->toContain('public function save(')
            ->toContain("'ui_meta' => \$this->uiMeta");
    }
});
