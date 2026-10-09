<?php

use App\Actions\Purchases\ApprovePurchaseOrder;
use App\Actions\Purchases\SavePurchaseDocumentDraft;
use App\Actions\Purchases\SubmitPurchaseOrderForApproval;
use App\Enums\DocumentType;
use App\Models\Period\Contact;
use App\Models\Period\Document;

it('v2 purchase draft submit and approval produces exactly one approved order number', function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2PURCHASE');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);
    $supplier = Contact::query()->create(['title' => 'V2 Supplier', 'type' => 'legal']);

    $draft = app(SavePurchaseDocumentDraft::class)->handle(
        DocumentType::PurchaseOrder,
        [
            'contact_id' => $supplier->id,
            'currency' => 'TRY',
            'document_date' => '2026-09-02',
        ],
        [[
            'line_kind' => 'service',
            'description' => 'V2 freight',
            'quantity' => '2.000',
            'unit_price' => '100.0000',
            'vat_rate' => '20.0000',
        ]],
    );

    expect($draft->status)->toBe('draft')
        ->and($draft->lines()->count())->toBe(1)
        ->and($draft->grand_total)->toBe('240.0000');

    $submitted = app(SubmitPurchaseOrderForApproval::class)->handle($draft, 'v2-purchase-submit');
    expect($submitted->status)->toBe('pending_approval');

    $approver = app(ApprovePurchaseOrder::class);
    $approved = $approver->handle($submitted, 'v2-purchase-approve');

    expect($approved->status)->toBe('approved')
        ->and($approved->number)->not->toBeNull()
        ->and(Document::query()->whereKey($approved->id)->value('number'))->toBe($approved->number);

    $repeat = $approver->handle($submitted, 'v2-purchase-approve');
    expect($repeat->id)->toBe($approved->id)
        ->and($repeat->number)->toBe($approved->number)
        ->and(Document::query()->where('document_type', 'purchase_order')->count())->toBe(1);
});

it('v2 purchase rejects approval of draft orders and never generates a number', function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2PURREFUSE');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);
    $supplier = Contact::query()->create(['title' => 'V2 Supplier', 'type' => 'legal']);
    $draft = app(SavePurchaseDocumentDraft::class)->handle(
        DocumentType::PurchaseOrder,
        ['contact_id' => $supplier->id, 'currency' => 'TRY', 'document_date' => '2026-09-02'],
        [['line_kind' => 'service', 'description' => 'Service', 'quantity' => '1.000', 'unit_price' => '10.0000']],
    );

    expect(fn () => app(ApprovePurchaseOrder::class)->handle($draft, 'v2-purchase-invalid'))
        ->toThrow(DomainException::class);

    expect($draft->refresh()->status)->toBe('draft')
        ->and($draft->number)->toBeNull();
});
