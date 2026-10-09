<?php

use App\Actions\Sales\ConfirmSalesOrder;
use App\Actions\Sales\ConvertProformaToInvoice;
use App\Actions\Sales\IssueProforma;
use App\Actions\Sales\PostDispatch;
use App\Actions\Sales\PostSalesInvoice;
use App\Enums\DocumentType;
use App\Models\Period\Document;

beforeEach(function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2SALESGUARD');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);
});

it('v2 sales posting action cannot post a purchase document', function () {
    $wrong = Document::query()->create([
        'document_type' => DocumentType::PurchaseOrder->value,
        'document_date' => '2026-09-01', 'status' => 'draft',
    ]);
    expect(fn () => app(PostSalesInvoice::class)->handle($wrong, 'v2-wrong-post-type'))
        ->toThrow(DomainException::class);
    expect($wrong->refresh()->status)->toBe('draft');
});

it('v2 dispatch posting action cannot post a sales invoice', function () {
    $wrong = Document::query()->create([
        'document_type' => DocumentType::SalesInvoice->value,
        'document_date' => '2026-09-01', 'status' => 'draft',
    ]);
    expect(fn () => app(PostDispatch::class)->handle($wrong, 'v2-wrong-dispatch-type'))
        ->toThrow(DomainException::class);
    expect($wrong->refresh()->status)->toBe('draft');
});

it('v2 order confirmation rejects non-order documents', function () {
    $wrong = Document::query()->create([
        'document_type' => DocumentType::SalesInvoice->value,
        'document_date' => '2026-09-01', 'status' => 'draft',
    ]);
    expect(fn () => app(ConfirmSalesOrder::class)->handle($wrong, 'v2-confirm-wrong-type'))
        ->toThrow(DomainException::class);
    expect($wrong->refresh()->status)->toBe('draft');
});

it('v2 proforma may not be issued from an unapproved quote', function () {
    $quote = Document::query()->create([
        'document_type' => DocumentType::Quote->value,
        'document_date' => '2026-09-01', 'status' => 'draft', 'revision_no' => 1,
    ]);
    expect(fn () => app(IssueProforma::class)->handle($quote, '2026-09-01', 'v2-proforma-unapproved'))
        ->toThrow(DomainException::class);
    expect(Document::query()->where('document_type', DocumentType::Proforma->value)->count())->toBe(0);
});

it('v2 proforma conversion requires posted proforma source', function () {
    $proforma = Document::query()->create([
        'document_type' => DocumentType::Proforma->value,
        'document_date' => '2026-09-01', 'status' => 'draft',
    ]);
    expect(fn () => app(ConvertProformaToInvoice::class)->handle($proforma, [], '2026-09-01', 'v2-proforma-unposted'))
        ->toThrow(DomainException::class);
    expect(Document::query()->where('document_type', DocumentType::SalesInvoice->value)->count())->toBe(0);
});
