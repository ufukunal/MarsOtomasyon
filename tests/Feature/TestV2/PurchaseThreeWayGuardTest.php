<?php

use App\Actions\Purchases\PostSupplierInvoice;
use App\Actions\Purchases\ThreeWayMatchSupplierInvoice;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;

beforeEach(function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2PURMATCH');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);
});

it('v2 three way matcher rejects already posted supplier invoices', function () {
    $invoice = Document::query()->create([
        'document_type' => DocumentType::SupplierInvoice->value,
        'document_date' => '2026-09-01', 'status' => 'posted',
    ]);
    expect(fn () => app(ThreeWayMatchSupplierInvoice::class)->handle($invoice))
        ->toThrow(DomainException::class);
});

it('v2 three way matcher rejects an invoice stock line without receipt lineage', function () {
    $invoice = Document::query()->create([
        'document_type' => DocumentType::SupplierInvoice->value,
        'document_date' => '2026-09-01', 'status' => 'draft',
    ]);
    $product = $this->createTestProduct();
    DocumentLine::query()->create([
        'document_id' => $invoice->id, 'line_no' => 1, 'line_kind' => 'stock',
        'product_id' => $product->id, 'unit_id' => $product->unit_id,
        'quantity' => '2.000', 'conversion_factor' => '1.000000',
        'base_quantity' => '2.000', 'unit_price' => '10.0000', 'line_total' => '20.0000',
    ]);
    expect(fn () => app(ThreeWayMatchSupplierInvoice::class)->handle($invoice))
        ->toThrow(DomainException::class);
});

it('v2 supplier invoice posting rejects a non-invoice document without mutation', function () {
    $other = Document::query()->create([
        'document_type' => DocumentType::SalesOrder->value,
        'document_date' => '2026-09-01', 'status' => 'draft',
    ]);
    expect(fn () => app(PostSupplierInvoice::class)->handle($other, 'v2-post-wrong-type'))
        ->toThrow(DomainException::class);
    expect($other->refresh()->status)->toBe('draft');
});