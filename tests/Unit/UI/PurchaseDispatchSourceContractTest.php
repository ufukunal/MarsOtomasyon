<?php

use App\Enums\DocumentType;

it('keeps a supplier dispatch distinct from an inventory goods receipt', function (): void {
    expect(DocumentType::PurchaseDispatch->value)->toBe('purchase_dispatch')
        ->and(DocumentType::PurchaseDispatch->isLineCalculated())->toBeTrue();

    $postingProfile = file_get_contents(base_path('app/Actions/Documents/ResolveDocumentPostingProfile.php'));
    $confirm = file_get_contents(base_path('app/Actions/Purchases/ConfirmPurchaseDispatch.php'));
    $receipt = file_get_contents(base_path('app/Actions/Purchases/CreateGoodsReceiptFromPurchaseDispatch.php'));

    expect($postingProfile)->not->toContain('DocumentType::PurchaseDispatch =>')
        ->and($confirm)->toContain("locked->status = 'confirmed'")
        ->toContain("'purchase_dispatches.update'")
        ->and($receipt)->toContain("'purchase_dispatch_to_goods_receipt'")
        ->toContain("'goods_receipts.create'");
});

it('checks supplier dispatch allocations and hides actions without permissions', function (): void {
    $availability = file_get_contents(base_path('app/Actions/Purchases/PurchaseLineAvailability.php'));
    $dispatch = file_get_contents(base_path('app/Actions/Purchases/CreatePurchaseDispatchFromOrder.php'));
    $confirm = file_get_contents(base_path('app/Actions/Purchases/ConfirmPurchaseDispatch.php'));
    $view = file_get_contents(base_path('resources/views/livewire/purchases/document-editor.blade.php'));

    expect($availability)->toContain('function orderDispatchRemaining(')
        ->toContain("'purchase_dispatch_to_goods_receipt'")
        ->and($dispatch)->toContain('orderDispatchRemaining($source)')
        ->and($confirm)->toContain('orderDispatchRemaining($sourceLine)')
        ->and($view)->toContain("@can('purchase_dispatches.create')")
        ->toContain("@can('purchase_dispatches.update')")
        ->toContain("@can('goods_receipts.create')");
});

it('prevents a direct receipt from bypassing reserved supplier-dispatch quantities', function (): void {
    $direct = file_get_contents(base_path('app/Actions/Purchases/CreateGoodsReceiptFromOrder.php'));
    $fromDispatch = file_get_contents(base_path('app/Actions/Purchases/CreateGoodsReceiptFromPurchaseDispatch.php'));

    expect($direct)->toContain('bool $fromSupplierDispatch = false')
        ->toContain('orderDispatchRemaining($source)')
        ->and($fromDispatch)->toContain('null, true)');
});
