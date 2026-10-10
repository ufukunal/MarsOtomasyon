<?php

use App\Actions\Purchases\ApplySupplierInvoiceCosts;
use App\Actions\Purchases\PostGoodsReceipt;
use App\Actions\Purchases\PostSupplierInvoice;
use App\Models\Period\Document;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('rejects posting a sales invoice through the goods receipt action', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $wrong = new Document(['document_type' => 'sales_invoice', 'status' => 'draft']);
            expect(fn () => app(PostGoodsReceipt::class)->handle(
                $wrong, 'v4-'.Str::random(20),
            ))->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('refuses posting a purchase order as a supplier invoice', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $wrong = Document::query()->create([
                'document_type' => 'purchase_order',
                'status' => 'draft',
                'document_date' => '2026-10-10',
            ]);
            expect(fn () => app(PostSupplierInvoice::class)->handle(
                $wrong, 'v4-'.Str::random(20),
            ))->toThrow(DomainException::class);
            expect($wrong->fresh()->status)->toBe('draft');
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('does not apply cost changes to an unposted supplier invoice', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $invoice = Document::query()->create([
                'document_type' => 'supplier_invoice',
                'status' => 'draft',
                'document_date' => '2026-10-10',
            ]);
            expect(fn () => app(ApplySupplierInvoiceCosts::class)->handle(
                $invoice, deviationAccepted: false,
            ))->toThrow(DomainException::class);
            expect($invoice->fresh()->status)->toBe('draft');
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
