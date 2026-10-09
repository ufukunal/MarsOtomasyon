<?php

use App\Actions\Documents\SaveSalesDocumentDraft;
use App\Actions\Finance\PostCollection;
use App\Actions\Sales\ApproveQuote;
use App\Actions\Sales\ConfirmSalesOrder;
use App\Actions\Sales\ConvertQuoteToSalesOrder;
use App\Actions\Sales\CreateDispatchFromOrder;
use App\Actions\Sales\CreateInvoiceFromDispatches;
use App\Actions\Sales\PostDispatch;
use App\Actions\Sales\PostSalesInvoice;
use App\Enums\DocumentType;
use App\Models\Period\CashAccount;
use App\Models\Period\CashMovement;
use App\Models\Period\Contact;
use App\Models\Period\ContactTransaction;
use App\Models\Period\Document;
use App\Models\Period\DocumentRelation;
use App\Models\Period\StockMovement;

it('v2 quote approval through order dispatch invoice and collection balances service-only financial ledger', function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2FULLSALE');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);
    $contact = Contact::query()->create(['title' => 'V2 Service Client', 'type' => 'legal']);
    $cash = CashAccount::query()->create([
        'code' => 'V2FULLCASH', 'name' => 'V2 Sales Cash', 'currency' => 'TRY', 'is_active' => true,
    ]);

    $quote = app(SaveSalesDocumentDraft::class)->handle(
        DocumentType::Quote,
        ['document_date' => '2026-09-01', 'contact_id' => $contact->id],
        [[
            'line_kind' => 'service', 'description' => 'Annual support',
            'quantity' => '2.000', 'unit_price' => '50.0000', 'vat_rate' => '20.0000',
        ]],
    );
    expect($quote->grand_total)->toBe('120.0000')
        ->and($quote->lines()->count())->toBe(1);

    // A source quote needs an issued number and review state for ApproveQuote.
    $quote->update(['number' => 'V2-QUOTE-SVC-00001', 'status' => 'internal_review']);
    $approved = app(ApproveQuote::class)->handle($quote->fresh(), 'v2-full-sale-approve');
    expect($approved->status)->toBe('approved');

    $order = app(ConvertQuoteToSalesOrder::class)->handle($approved, 'v2-full-sale-order');
    $confirmed = app(ConfirmSalesOrder::class)->handle($order, 'v2-full-sale-confirm');
    expect($confirmed->status)->toBe('confirmed')
        ->and($confirmed->number)->not->toBeNull();

    $sourceLine = $confirmed->lines()->firstOrFail();
    $dispatch = app(CreateDispatchFromOrder::class)->handle(
        $confirmed, [(int) $sourceLine->id => '2.000'], [], '2026-09-02', 'v2-full-sale-dispatch',
    );
    $postedDispatch = app(PostDispatch::class)->handle($dispatch, 'v2-full-sale-dispatch-post');
    expect($postedDispatch->status)->toBe('posted');

    $dispatchLine = $postedDispatch->lines()->firstOrFail();
    $invoice = app(CreateInvoiceFromDispatches::class)->handle(
        [(int) $dispatchLine->id => '2.000'], '2026-09-03', 'v2-full-sale-invoice',
    );
    $postedInvoice = app(PostSalesInvoice::class)->handle($invoice, 'v2-full-sale-invoice-post');
    expect($postedInvoice->status)->toBe('posted')
        ->and($postedInvoice->grand_total)->toBe('120.0000')
        ->and($contact->balance())->toBe('120.0000')
        ->and(StockMovement::query()->count())->toBe(0);

    $collection = app(PostCollection::class)->handle(
        $contact->id, '120.0000', '2026-09-04', 'cash', $cash->id,
        'v2-full-sale-collection', $postedInvoice->id,
    );
    expect($collection->status)->toBe('posted')
        ->and($contact->balance())->toBe('0.0000')
        ->and($cash->balance())->toBe('120.0000')
        ->and(CashMovement::query()->count())->toBe(1)
        ->and(ContactTransaction::query()->count())->toBe(2)
        ->and(DocumentRelation::query()->where('relation_type', 'collection_source')->count())->toBe(1);

    $again = app(PostCollection::class)->handle(
        $contact->id, '120.0000', '2026-09-04', 'cash', $cash->id,
        'v2-full-sale-collection', $postedInvoice->id,
    );
    expect($again->id)->toBe($collection->id)
        ->and(CashMovement::query()->count())->toBe(1)
        ->and(ContactTransaction::query()->count())->toBe(2)
        ->and(Document::query()->where('document_type', 'sales_invoice')->count())->toBe(1);
});