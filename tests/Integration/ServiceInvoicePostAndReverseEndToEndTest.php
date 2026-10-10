<?php

use App\Actions\Documents\ReverseDocument;
use App\Actions\Documents\SaveSalesDocumentDraft;
use App\Actions\Purchases\PostSupplierInvoice;
use App\Actions\Purchases\SavePurchaseDocumentDraft;
use App\Actions\Sales\PostSalesInvoice;
use App\Enums\DocumentType;
use App\Exceptions\Documents\AlreadyReversedException;
use App\Models\Period\Contact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('posts and reverses a service-only sales invoice with balanced customer ledger movements', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $customer = Contact::query()->create(['title' => 'V4 End-to-end Customer', 'type' => 'legal']);
            $draft = app(SaveSalesDocumentDraft::class)->handle(
                DocumentType::SalesInvoice,
                ['contact_id' => $customer->id, 'document_date' => '2026-10-10'],
                [[
                    'line_kind' => 'service',
                    'description' => 'V4 test service',
                    'quantity' => '2.000',
                    'unit_price' => '50.0000',
                    'vat_rate' => '20',
                ]],
            );

            $key = 'v4-sale-post-'.Str::random(16);
            $post = app(PostSalesInvoice::class);
            $invoice = $post->handle($draft, $key);

            expect($invoice->status)->toBe('posted')
                ->and($invoice->number)->not->toBeNull()
                ->and((string) $invoice->grand_total)->toBe('120.0000')
                ->and($post->handle($draft, $key)->id)->toBe($invoice->id);

            $db = DB::connection('period');
            expect($db->table('contact_transactions')->where('document_id', $invoice->id)->value('direction'))
                ->toBe('debit');

            $reverse = app(ReverseDocument::class);
            $correction = $reverse->handle(
                $invoice, '2026-10-10', 'Duplicate local sale', 'v4-reverse-'.Str::random(16),
            );
            expect($correction->status)->toBe('posted');
            expect($db->table('contact_transactions')->where('document_id', $correction->id)->value('direction'))
                ->toBe('credit');
            expect((string) $db->table('contact_transactions')->where('document_id', $correction->id)
                ->value('amount'))->toBe('120.0000');

            expect($db->table('document_relations')
                ->where('source_document_id', $correction->id)
                ->where('target_document_id', $invoice->id)
                ->where('relation_type', 'reversal_of')->count())->toBe(1);

            expect(fn () => $reverse->handle(
                $invoice, '2026-10-10', 'Second reversal', 'v4-reverse-'.Str::random(16),
            ))->toThrow(AlreadyReversedException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('posts a service-only supplier invoice without creating a physical stock movement', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $supplier = Contact::query()->create(['title' => 'V4 End-to-end Supplier', 'type' => 'legal']);
            $invoice = app(SavePurchaseDocumentDraft::class)->handle(
                DocumentType::SupplierInvoice,
                [
                    'contact_id' => $supplier->id,
                    'document_date' => '2026-10-10',
                    'currency' => 'TRY',
                    'exchange_rate' => '1.000000',
                ],
                [[
                    'line_kind' => 'service',
                    'description' => 'V4 supplier services',
                    'quantity' => '1.000',
                    'unit_price' => '70.0000',
                    'vat_rate' => '20',
                ]],
            );
            $posted = app(PostSupplierInvoice::class)->handle(
                $invoice, 'v4-purchase-post-'.Str::random(16),
            );

            expect($posted->status)->toBe('posted')
                ->and((string) $posted->grand_total)->toBe('84.0000');

            $db = DB::connection('period');
            expect($db->table('contact_transactions')->where('document_id', $posted->id)->value('direction'))
                ->toBe('credit');
            expect($db->table('stock_movements')->where('document_id', $posted->id)->count())->toBe(0);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
