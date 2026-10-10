<?php

use App\Actions\Documents\ReverseDocument;
use App\Actions\Sales\PostDispatch;
use App\Actions\Sales\PostSalesInvoice;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('refuses to post a sales invoice using the dispatch business action', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $invoice = new Document(['document_type' => DocumentType::SalesInvoice->value]);
            expect(fn () => app(PostDispatch::class)->handle($invoice, 'v4-'.Str::random(18)))
                ->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('refuses to post a dispatch using the sales invoice business action', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $dispatch = new Document(['document_type' => DocumentType::Dispatch->value]);
            expect(fn () => app(PostSalesInvoice::class)->handle($dispatch, 'v4-'.Str::random(18)))
                ->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('requires an auditable nonblank reason before reversing any document type', function (DocumentType $kind): void {
    IsolatedPostgres::withActivePeriod(function () use ($kind): void {
        AuthorizedPeriod::login();

        try {
            $document = new Document(['document_type' => $kind->value]);
            expect(fn () => app(ReverseDocument::class)->handle(
                $document, '2026-10-10', "\n\t  ", 'v4-'.Str::random(18),
            ))->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
})->with([
    DocumentType::SalesInvoice,
    DocumentType::Dispatch,
    DocumentType::GoodsReceipt,
    DocumentType::SupplierInvoice,
]);
