<?php

use App\Actions\Documents\SaveSalesDocumentDraft;
use App\Actions\Returns\CreateReturnFromInvoice;
use App\Actions\Returns\PostReturnDocument;
use App\Actions\Sales\ConfirmSalesOrder;
use App\Actions\Sales\PostSalesInvoice;
use App\Enums\DocumentType;
use App\Models\Period\Contact;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('confirms an order and posts a sales invoice with both percentage and fixed discounts', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $contact = Contact::query()->create([
                'title' => 'Fixed discount integration '.Str::random(8),
                'type' => 'legal',
            ]);
            $header = [
                'contact_id' => $contact->id,
                'document_date' => '2026-10-10',
                'discount_rate' => '10',
                'additional_discount_amount' => '25',
            ];
            $lines = [[
                'line_kind' => 'service',
                'description' => 'Discounted service',
                'quantity' => '1.000',
                'unit_price' => '100.0000',
                'vat_rate' => '20',
            ]];

            $savedOrder = app(SaveSalesDocumentDraft::class)->handle(
                DocumentType::SalesOrder, $header, $lines,
            );
            $order = app(ConfirmSalesOrder::class)->handle(
                $savedOrder, 'discount-order-'.Str::random(18),
            );
            expect($order->status)->toBe('confirmed')
                ->and((string) $order->additional_discount_amount)->toBe('25.0000')
                ->and((string) $order->grand_total)->toBe('78.0000');

            $savedInvoice = app(SaveSalesDocumentDraft::class)->handle(
                DocumentType::SalesInvoice, $header, $lines,
            );
            $invoice = app(PostSalesInvoice::class)->handle(
                $savedInvoice, 'discount-invoice-'.Str::random(18),
            );
            expect($invoice->status)->toBe('posted')
                ->and((string) $invoice->additional_discount_amount)->toBe('25.0000')
                ->and((string) $invoice->grand_total)->toBe('78.0000');

            $line = $invoice->lines()->firstOrFail();
            $return = app(CreateReturnFromInvoice::class)->handle(
                $invoice, DocumentType::SalesReturn,
                [$line->id => '1.000'], [], '2026-10-10',
                'discount-return-'.Str::random(18),
            );
            expect((string) $return->additional_discount_amount)->toBe('25.0000')
                ->and((string) $return->grand_total)->toBe('78.0000');

            $postedReturn = app(PostReturnDocument::class)->handle(
                $return, 'discount-return-post-'.Str::random(18),
            );
            expect($postedReturn->status)->toBe('posted')
                ->and((string) $postedReturn->grand_total)->toBe('78.0000');
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
