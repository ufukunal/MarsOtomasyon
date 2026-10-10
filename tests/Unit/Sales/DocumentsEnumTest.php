<?php

use App\Enums\DocumentType;

it('categorizes all document types into line-calculated and header-amount modes', function (): void {
    foreach (DocumentType::cases() as $type) {
        expect($type->isLineCalculated() xor $type->isHeaderAmount())->toBeTrue();
    }
    expect(DocumentType::SalesInvoice->isLineCalculated())->toBeTrue()
        ->and(DocumentType::PurchaseOrder->isLineCalculated())->toBeTrue()
        ->and(DocumentType::SalesReturn->isLineCalculated())->toBeTrue()
        ->and(DocumentType::Collection->isHeaderAmount())->toBeTrue()
        ->and(DocumentType::Payment->isHeaderAmount())->toBeTrue();
});
