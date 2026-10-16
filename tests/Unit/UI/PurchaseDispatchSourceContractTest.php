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
