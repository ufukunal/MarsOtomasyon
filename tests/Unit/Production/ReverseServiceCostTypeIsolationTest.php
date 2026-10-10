<?php

use App\Actions\Production\ReverseProductionServiceInvoiceCosts;
use App\Actions\Purchases\ReverseSupplierInvoiceCosts;
use App\Models\Period\Document;

it('does not touch a production cost ledger for sales invoices', function (): void {
    $subject = (new ReflectionClass(ReverseProductionServiceInvoiceCosts::class))->newInstanceWithoutConstructor();

    expect($subject->handle(new Document(['document_type' => 'sales_invoice']), '2026-10-10'))->toBeNull();
});

it('does not reverse supplier costs for other document categories', function (string $type): void {
    $subject = (new ReflectionClass(ReverseSupplierInvoiceCosts::class))->newInstanceWithoutConstructor();

    expect($subject->handle(new Document(['document_type' => $type]))->toBeNull();
})->with(['sales_invoice', 'payment', 'collection', 'sales_return']);
