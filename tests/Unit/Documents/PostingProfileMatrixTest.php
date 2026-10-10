<?php

use App\Actions\Documents\ResolveDocumentPostingProfile;
use App\DataObjects\Documents\DocumentPostingContext;
use App\Enums\DocumentType;
use App\Models\Period\Document;

it('maps each journal-postable document to its exact inventory, receivable and cashflow effects', function (DocumentType $type, array $effects): void {
    $document = new Document(['document_type' => $type->value]);
    $context = $type === DocumentType::ContactDebitCredit
        ? new DocumentPostingContext(contactDirection: 'debit')
        : null;

    $profile = (new ResolveDocumentPostingProfile)->handle($document, $context);

    expect([
        $profile->calculationMode,
        $profile->stockOut,
        $profile->stockIn,
        $profile->contactDirection,
        $profile->financialIn,
        $profile->financialOut,
    ])->toBe($effects);
})->with([
    'dispatch' => [DocumentType::Dispatch, ['line_calculated', true, false, null, false, false]],
    'sales invoice' => [DocumentType::SalesInvoice, ['line_calculated', true, false, 'debit', false, false]],
    'goods receipt' => [DocumentType::GoodsReceipt, ['line_calculated', false, true, null, false, false]],
    'supplier invoice' => [DocumentType::SupplierInvoice, ['line_calculated', false, false, 'credit', false, false]],
    'collection' => [DocumentType::Collection, ['header_amount', false, false, 'credit', true, false]],
    'payment' => [DocumentType::Payment, ['header_amount', false, false, 'debit', false, true]],
    'expense' => [DocumentType::Expense, ['line_calculated', false, false, null, false, true]],
    'advance' => [DocumentType::Advance, ['header_amount', false, false, 'debit', false, true]],
    'advance return' => [DocumentType::AdvanceReturn, ['header_amount', false, false, 'credit', true, false]],
    'contact debit' => [DocumentType::ContactDebitCredit, ['header_amount', false, false, 'debit', false, false]],
]);

it('rejects sales/purchase drafts which cannot post directly as ledger documents', function (DocumentType $type): void {
    $document = new Document(['document_type' => $type->value]);
    expect(fn () => (new ResolveDocumentPostingProfile)->handle($document))
        ->toThrow(DomainException::class);
})->with([
    DocumentType::Quote,
    DocumentType::SalesOrder,
    DocumentType::PurchaseOrder,
    DocumentType::Proforma,
    DocumentType::SalesReturn,
    DocumentType::PurchaseReturn,
]);

it('requires an explicit debit or credit direction for manual contact movements', function (): void {
    $document = new Document(['document_type' => DocumentType::ContactDebitCredit->value]);
    expect(fn () => (new ResolveDocumentPostingProfile)->handle($document))
        ->toThrow(DomainException::class);
    expect(fn () => (new ResolveDocumentPostingProfile)->handle($document,
        new DocumentPostingContext(contactDirection: 'neither')))
        ->toThrow(DomainException::class);
});
