<?php

use App\Actions\Returns\PostReturnDocument;
use App\Actions\Returns\ReverseReturnDocument;
use App\Enums\DocumentType;
use App\Models\Period\Document;

beforeEach(function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2RETGUARD');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);
});

it('v2 return posting rejects a sales invoice instead of inventing a return', function () {
    $invoice = Document::query()->create([
        'document_type' => DocumentType::SalesInvoice->value,
        'document_date' => '2026-09-01', 'status' => 'draft',
    ]);
    expect(fn () => app(PostReturnDocument::class)->handle($invoice, 'v2-ret-wrong-kind'))
        ->toThrow(DomainException::class);
    expect($invoice->refresh()->status)->toBe('draft');
});

it('v2 return reversal requires an explicit reason and preserves original draft', function () {
    $return = Document::query()->create([
        'document_type' => DocumentType::SalesReturn->value,
        'document_date' => '2026-09-01', 'status' => 'draft',
    ]);
    expect(fn () => app(ReverseReturnDocument::class)->handle($return, '2026-09-01', '', 'v2-ret-blank-reason'))
        ->toThrow(DomainException::class);
    expect($return->refresh()->status)->toBe('draft');
});

it('v2 return reversal rejects a draft with otherwise valid reversal data', function () {
    $return = Document::query()->create([
        'document_type' => DocumentType::SalesReturn->value,
        'document_date' => '2026-09-01', 'status' => 'draft',
    ]);
    expect(fn () => app(ReverseReturnDocument::class)->handle($return, '2026-09-01', 'Correction', 'v2-ret-draft-reverse'))
        ->toThrow(DomainException::class);
    expect(Document::query()->where('document_type', DocumentType::SalesReturn->value)->count())->toBe(1);
});
