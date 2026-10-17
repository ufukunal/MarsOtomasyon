<?php

it('makes all posted stock movement source documents navigable for authorized correction', function (): void {
    $navigation = file_get_contents(base_path('app/Support/Documents/DocumentEditNavigation.php'));
    $movement = file_get_contents(base_path('app/Livewire/Pages/Stock/StockMovementDetail.php'));
    $reservation = file_get_contents(base_path('app/Livewire/Pages/Stock/ReservationDetail.php'));
    $reservationView = file_get_contents(base_path('resources/views/livewire/pages/stock/reservation-detail.blade.php'));

    foreach ([
        'quote', 'sales_order', 'dispatch', 'sales_invoice',
        'purchase_quote', 'purchase_order', 'purchase_dispatch',
        'goods_receipt', 'supplier_invoice', 'proforma',
        'sales_return', 'purchase_return',
        'collection', 'payment', 'contact_debit_credit', 'expense',
        'advance', 'advance_return',
        'transfer', 'warehouse_slip', 'stock_count',
    ] as $type) {
        expect($navigation)->toContain("'{$type}' =>");
    }

    expect(substr_count($navigation, " => ['route' =>"))->toBe(21);

    expect($navigation)->toContain("auth()->check()")
        ->toContain("auth()->user()->can(\$destination['permission'])")
        ->toContain("route(\$destination['route'], ['id' => \$id])")
        ->and($movement)->toContain('DocumentEditNavigation::url(')
        ->and($reservation)->toContain('DocumentEditNavigation::url(')
        ->and($reservationView)->toContain('href="{{ $sourceUrl }}"');
});

it('keeps all correction paths separate from unchangeable ledger and stock history', function (): void {
    $stock = file_get_contents(base_path('app/Models/Period/StockMovement.php'));
    $copy = file_get_contents(base_path('app/Actions/Documents/CreateEditableDocumentCopy.php'));
    $securities = file_get_contents(base_path('app/Actions/Finance/ReplaceSecurity.php'));
    $return = file_get_contents(base_path('app/Livewire/Returns/ReturnCenter.php'));
    $financial = file_get_contents(base_path('app/Livewire/Concerns/WithFinancialRecordCorrection.php'));
    $warehouse = file_get_contents(base_path('app/Livewire/Pages/Warehouse/TransportSlipEditor.php'));

    expect($stock)->toContain("static::updating(")
        ->toContain("static::deleting(")
        ->and($copy)->toContain("Kesinleşmiş belge önce Ters Kayıt ile düzeltilmelidir")
        ->and($securities)->toContain("MutationAuthorizer::authorize(")
        ->and($return)->toContain('public function editDraft(')
        ->toContain('public function reverse(')
        ->and($financial)->toContain('financialCorrectionReversed(')
        ->and($warehouse)->toContain('public function duplicate()');
});
