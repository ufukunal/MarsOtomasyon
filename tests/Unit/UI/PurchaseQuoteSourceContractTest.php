<?php

use App\Actions\Purchases\ApprovePurchaseQuote;
use App\Actions\Purchases\ConvertPurchaseQuoteToOrder;
use App\Enums\DocumentType;

it('defines non-posting purchase quotes and guarded conversion to purchase orders', function (): void {
    expect(DocumentType::PurchaseQuote->value)->toBe('purchase_quote')
        ->and(DocumentType::PurchaseQuote->isLineCalculated())->toBeTrue();

    $approve = file_get_contents(base_path('app/Actions/Purchases/ApprovePurchaseQuote.php'));
    $convert = file_get_contents(base_path('app/Actions/Purchases/ConvertPurchaseQuoteToOrder.php'));
    $posting = file_get_contents(base_path('app/Actions/Documents/ResolveDocumentPostingProfile.php'));

    expect(class_exists(ApprovePurchaseQuote::class))->toBeTrue()
        ->and(class_exists(ConvertPurchaseQuoteToOrder::class))->toBeTrue()
        ->and($approve)->toContain("\$locked->status = 'approved'")
        ->and($convert)->toContain("'purchase_orders.create'")
        ->toContain("'purchase_quote_to_order'")
        ->and($posting)->not->toContain('DocumentType::PurchaseQuote =>');
});


it('limits quote-to-order conversion to a period-open approved quotation with saved lines', function (): void {
    $convert = file_get_contents(base_path('app/Actions/Purchases/ConvertPurchaseQuoteToOrder.php'));
    $view = file_get_contents(base_path('resources/views/livewire/purchases/document-editor.blade.php'));

    expect($convert)->toContain('$locked->lines->isEmpty()')
        ->toContain('EnsurePeriodOpen')
        ->toContain("'purchase_quote_to_order'")
        ->and($view)->toContain("@can('purchase_quotes.update')")
        ->toContain("@can('purchase_orders.create')")
        ->toContain('action="convertToOrder"');
});
