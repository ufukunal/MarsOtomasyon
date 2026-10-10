<?php

use App\Actions\Purchases\CreateGoodsReceiptFromOrder;
use App\Actions\Purchases\CreateSupplierInvoiceFromReceipts;
use App\Actions\Returns\CreateReturnFromInvoice;
use App\Actions\Returns\PostReturnDocument;
use App\Actions\Sales\ConvertQuoteToSalesOrder;
use App\Actions\Sales\CreateDispatchFromOrder;
use App\Actions\Sales\CreateInvoiceFromOrder;
use App\Enums\DocumentType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('refuses converting unapproved quotes and sales orders before producing destination documents', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $quoteId = marsV4ConversionDoc('quote', 'internal_review', 1);
            $orderId = marsV4ConversionDoc('sales_order', 'draft');
            $quote = \App\Models\Period\Document::query()->findOrFail($quoteId);
            $order = \App\Models\Period\Document::query()->findOrFail($orderId);
            $before = DB::connection('period')->table('documents')->count();

            expect(fn () => app(ConvertQuoteToSalesOrder::class)->handle($quote, 'v4-'.Str::random(18)))
                ->toThrow(DomainException::class);
            expect(fn () => app(CreateDispatchFromOrder::class)->handle(
                $order, [], [], '2026-10-10', 'v4-'.Str::random(18),
            ))->toThrow(DomainException::class);
            expect(fn () => app(CreateInvoiceFromOrder::class)->handle(
                $order, [], [], '2026-10-10', 'v4-'.Str::random(18),
            ))->toThrow(DomainException::class);
            expect(DB::connection('period')->table('documents')->count())->toBe($before);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('blocks goods receipts from draft purchase orders and supplier invoices without posted receipts', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $id = marsV4ConversionDoc('purchase_order', 'draft');
            $order = \App\Models\Period\Document::query()->findOrFail($id);
            expect(fn () => app(CreateGoodsReceiptFromOrder::class)->handle(
                $order, [], [], '2026-10-10', 'v4-'.Str::random(18),
            ))->toThrow(DomainException::class);
            expect(fn () => app(CreateSupplierInvoiceFromReceipts::class)->handle(
                [], '2026-10-10', 'TRY', '1', 'v4-'.Str::random(18),
            ))->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('requires the right source and draft status before opening or posting sales returns', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $invoiceId = marsV4ConversionDoc('sales_invoice', 'draft');
            $invoice = \App\Models\Period\Document::query()->findOrFail($invoiceId);
            expect(fn () => app(CreateReturnFromInvoice::class)->handle(
                $invoice, DocumentType::SalesReturn, [], [], '2026-10-10', 'v4-'.Str::random(18),
            ))->toThrow(DomainException::class);
            expect(fn () => app(CreateReturnFromInvoice::class)->handle(
                $invoice, DocumentType::SalesOrder, [], [], '2026-10-10', 'v4-'.Str::random(18),
            ))->toThrow(DomainException::class);
            expect(fn () => app(PostReturnDocument::class)->handle(
                $invoice, 'v4-'.Str::random(18),
            ))->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

function marsV4ConversionDoc(string $type, string $status, int $revision = 0): int
{
    return (int) DB::connection('period')->table('documents')->insertGetId([
        'document_type' => $type,
        'document_date' => '2026-10-10',
        'status' => $status,
        'revision_no' => $revision,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}
