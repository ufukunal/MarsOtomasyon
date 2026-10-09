<?php

use App\Actions\Purchases\ApprovePurchaseOrder;
use App\Actions\Purchases\CreateGoodsReceiptFromOrder;
use App\Actions\Purchases\CreateSupplierInvoiceFromReceipts;
use App\Actions\Purchases\PostGoodsReceipt;
use App\Actions\Purchases\PostPayment;
use App\Actions\Purchases\PostSupplierInvoice;
use App\Actions\Purchases\SavePurchaseDocumentDraft;
use App\Actions\Purchases\SubmitPurchaseOrderForApproval;
use App\Enums\DocumentType;
use App\Models\Period\CashAccount;
use App\Models\Period\CashMovement;
use App\Models\Period\Contact;
use App\Models\Period\ContactTransaction;
use App\Models\Period\Document;
use App\Models\Period\StockMovement;

it('v2 purchase order approval through goods receipt supplier invoice and payment balances service-only ledger', function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2FULLPUR');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);
    $supplier = Contact::query()->create(['title' => 'V2 Service Supplier', 'type' => 'legal']);
    $cash = CashAccount::query()->create([
        'code' => 'V2PURCASH', 'name' => 'V2 Supplier Cash', 'currency' => 'TRY', 'is_active' => true,
    ]);
    $order = app(SavePurchaseDocumentDraft::class)->handle(
        DocumentType::PurchaseOrder,
        ['document_date' => '2026-09-01', 'currency' => 'TRY', 'contact_id' => $supplier->id],
        [[
            'line_kind' => 'service', 'description' => 'Maintenance',
            'quantity' => '2.000', 'unit_price' => '50.0000', 'vat_rate' => '20.0000',
        ]],
    );
    $pending = app(SubmitPurchaseOrderForApproval::class)->handle($order, 'v2-purchase-full-submit');
    $approved = app(ApprovePurchaseOrder::class)->handle($pending, 'v2-purchase-full-approve');
    expect($approved->status)->toBe('approved');

    $orderLine = $approved->lines()->firstOrFail();
    $receipt = app(CreateGoodsReceiptFromOrder::class)->handle(
        $approved, [(int) $orderLine->id => '2.000'], [], '2026-09-02', 'v2-purchase-full-receipt',
    );
    $postedReceipt = app(PostGoodsReceipt::class)->handle($receipt, 'v2-purchase-full-receipt-post');
    expect($postedReceipt->status)->toBe('posted')
        ->and(StockMovement::query()->count())->toBe(0);

    $receiptLine = $postedReceipt->lines()->firstOrFail();
    $invoice = app(CreateSupplierInvoiceFromReceipts::class)->handle(
        [(int) $receiptLine->id => '2.000'], '2026-09-03', 'TRY', '1.000000', 'v2-purchase-full-invoice',
    );
    $postedInvoice = app(PostSupplierInvoice::class)->handle($invoice, 'v2-purchase-full-invoice-post');
    expect($postedInvoice->status)->toBe('posted')
        ->and($postedInvoice->grand_total)->toBe('120.0000')
        ->and($supplier->balance())->toBe('-120.0000');

    $payment = app(PostPayment::class)->handle(
        $supplier->id, '120.0000', 'TRY', '1.000000', '2026-09-04',
        'cash', $cash->id, 'v2-purchase-full-pay', $postedInvoice->id,
    );
    expect($payment->status)->toBe('posted')
        ->and($supplier->balance())->toBe('0.0000')
        ->and($cash->balance())->toBe('-120.0000')
        ->and(CashMovement::query()->count())->toBe(1)
        ->and(ContactTransaction::query()->count())->toBe(2);

    $repeated = app(PostPayment::class)->handle(
        $supplier->id, '120.0000', 'TRY', '1.000000', '2026-09-04',
        'cash', $cash->id, 'v2-purchase-full-pay', $postedInvoice->id,
    );
    expect($repeated->id)->toBe($payment->id)
        ->and(CashMovement::query()->count())->toBe(1)
        ->and(Document::query()->where('document_type', 'supplier_invoice')->count())->toBe(1);
});
