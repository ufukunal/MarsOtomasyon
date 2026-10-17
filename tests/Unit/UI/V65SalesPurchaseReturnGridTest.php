<?php

it('defines independent sales and purchase return screens using one 10-column document grid', function (): void {
    $routes = file_get_contents(base_path('routes/web.php'));
    $view = file_get_contents(base_path('resources/views/livewire/returns/return-center.blade.php'));
    $table = file_get_contents(base_path('resources/views/components/v65/document-product-table-head.blade.php'));

    expect($routes)->toContain("->name('returns.center')")
        ->toContain("->name('returns.sales')")
        ->toContain("->name('returns.purchases')")
        ->and($view)->toContain("route('returns.sales')")
        ->toContain("route('returns.purchases')")
        ->toContain('<x-v65.document-product-table-head')
        ->toContain('v65-reference-products-card')
        ->toContain('v65-reference-totals-layout')
        ->toContain('v65-reference-inline-summary')
        ->toContain('İade Taslağını Kaydet')
        ->toContain('İadeyi İşle')
        ->and(substr_count($table, '<th scope="col"'))->toBe(10);
});

it('does not offer unsourced product, price or VAT editing in invoice based returns', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/returns/return-center.blade.php'));
    $preview = file_get_contents(base_path('app/Support/Returns/ReturnDraftPreview.php'));
    $action = file_get_contents(base_path('app/Actions/Returns/CreateReturnFromInvoice.php'));

    expect($view)->toContain('data-return-search')
        ->toContain('wire:model.live.debounce.350ms="quantities.')
        ->toContain('wire:model="locationIds.')
        ->toContain('Kaynaktan sabit')
        ->toContain('Satış Fiyatı')
        ->toContain('Alış Fiyatı')
        ->toContain('clearReturnLine(')
        ->not->toContain('wire:model="unit_price"')
        ->and($preview)->toContain('ReturnLineAvailability')
        ->toContain('ProrateFixedDiscount')
        ->toContain('CalculateDocumentTotals')
        ->toContain("line_discount_amount' => '0'")
        ->toContain("bccomp(\$quantity, \$remaining, 3) > 0")
        ->and($action)->toContain('MutationAuthorizer::authorize(\'returns.create\')')
        ->toContain("where('relation_type', 'reversal_of')");
});

it('keeps posted returns immutable while allowing draft quantity and location changes', function (): void {
    $returnCenter = file_get_contents(base_path('app/Livewire/Returns/ReturnCenter.php'));
    $editAction = file_get_contents(base_path('app/Actions/Returns/UpdateReturnDraft.php'));
    $post = file_get_contents(base_path('app/Actions/Returns/PostReturnDocument.php'));
    $view = file_get_contents(base_path('resources/views/livewire/returns/return-center.blade.php'));
    expect($returnCenter)->toContain('public function editDraft(int $id)')
        ->toContain('public function startNewReturn(): void')
        ->toContain('public function selectReturn(int $id)')
        ->toContain('abort_if($this->selectedReturnId !== null && ! $this->editingReturnDraft, 409)')
        ->toContain("'status', 'posted'")
        ->and($editAction)->toContain("status !== 'draft'")
        ->toContain('return_draft_updated')
        ->and($post)->toContain('ReceiveToQuarantine')
        ->toContain("MutationAuthorizer::authorize('returns.update')")
        ->and($view)->toContain('Geçmiş / teknik düzeltme')
        ->toContain('Görüntüleme yetkisi yok');
});
