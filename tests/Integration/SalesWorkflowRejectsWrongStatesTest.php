<?php

use App\Actions\Sales\CancelSalesOrderRemaining;
use App\Actions\Sales\ConvertProformaToInvoice;
use App\Actions\Sales\IssueProforma;
use App\Models\Period\Document;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

function marsV4SalesInvalidSource(string $kind, string $status): Document
{
    return Document::query()->create([
        'document_type' => $kind,
        'status' => $status,
        'document_date' => '2026-10-10',
    ]);
}

it('refuses cancelling the remainder of an order that has never been confirmed', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $order = marsV4SalesInvalidSource('sales_order', 'draft');
            expect(fn () => app(CancelSalesOrderRemaining::class)->handle(
                $order, 'v4-'.Str::random(20),
            ))->toThrow(DomainException::class);
            expect($order->fresh()->status)->toBe('draft');
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects converting an unposted proforma to an invoice before producing any document', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $proforma = marsV4SalesInvalidSource('proforma', 'draft');
            expect(fn () => app(ConvertProformaToInvoice::class)->handle(
                $proforma, [], '2026-10-10', 'v4-'.Str::random(20),
            ))->toThrow(DomainException::class);
            expect(Document::query()->where('document_type', 'sales_invoice')->count())->toBe(0);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects issuing a proforma from a quote that has not been approved', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $quote = marsV4SalesInvalidSource('quote', 'draft');
            expect(fn () => app(IssueProforma::class)->handle(
                $quote, '2026-10-10', 'v4-'.Str::random(20),
            ))->toThrow(DomainException::class);
            expect(Document::query()->where('document_type', 'proforma')->count())->toBe(0);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
