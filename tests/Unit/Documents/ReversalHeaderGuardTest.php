<?php

use App\Actions\Documents\VerifyReversal;
use App\Enums\DocumentType;
use App\Models\Period\Document;

it('rejects a reversal of a different document type before reading the ledger', function (): void {
    $original = new Document(['document_type' => DocumentType::SalesInvoice->value, 'grand_total' => '50.0000']);
    $reversed = new Document(['document_type' => DocumentType::Collection->value, 'grand_total' => '50.0000']);

    expect(fn () => app(VerifyReversal::class)->handle($original, $reversed))
        ->toThrow(DomainException::class);
});

it('rejects an unequal reversal amount before loading relations', function (string $originalAmount, string $reversalAmount): void {
    $original = new Document(['document_type' => DocumentType::SalesInvoice->value, 'grand_total' => $originalAmount]);
    $reversed = new Document(['document_type' => DocumentType::SalesInvoice->value, 'grand_total' => $reversalAmount]);

    expect(fn () => app(VerifyReversal::class)->handle($original, $reversed))
        ->toThrow(DomainException::class);
})->with([
    ['50.0000', '49.9999'],
    ['50.0000', '50.0001'],
    ['0.0000', '1.0000'],
    ['100.0000', '0.0000'],
]);
