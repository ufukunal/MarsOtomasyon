<?php

use App\Actions\Documents\SaveSalesDocumentDraft;
use App\Actions\Sales\ConfirmSalesOrder;
use App\Actions\Sales\ConvertQuoteToSalesOrder;
use App\Enums\DocumentType;
use App\Models\Period\Contact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('turns an approved quote into an auditable sales order and confirms it exactly once', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $contact = Contact::query()->create([
                'title' => 'V4 quote customer', 'type' => 'legal', 'risk_limit' => '0',
            ]);
            $quote = app(SaveSalesDocumentDraft::class)->handle(
                DocumentType::Quote,
                ['contact_id' => $contact->id, 'document_date' => '2026-10-10'],
                [[
                    'line_kind' => 'service', 'description' => 'Consulting',
                    'quantity' => '2.000', 'unit_price' => '50.0000', 'vat_rate' => '20',
                ]],
            );
            expect($quote->status)->toBe('draft')
                ->and($quote->lines)->toHaveCount(1)
                ->and($quote->grand_total)->toBe('120.0000');

            $quote->status = 'approved';
            $quote->number = 'Q-V4-'.Str::random(10);
            $quote->save();

            $action = app(ConvertQuoteToSalesOrder::class);
            $key = 'v4-'.Str::random(20);
            $order = $action->handle($quote, $key);
            $replay = $action->handle($quote, $key);

            expect($replay->id)->toBe($order->id)
                ->and($quote->refresh()->status)->toBe('converted')
                ->and($order->lines)->toHaveCount(1)
                ->and($order->lines[0]->source_line_id)->toBe($quote->lines[0]->id)
                ->and($order->status)->toBe('draft');

            $confirmed = app(ConfirmSalesOrder::class)->handle($order, 'v4-'.Str::random(20));
            expect($confirmed->status)->toBe('confirmed')
                ->and($confirmed->number)->not->toBeNull()
                ->and($confirmed->grand_total)->toBe('120.0000');

            expect(DB::connection('period')->table('documents')
                ->where('document_type', 'sales_order')->count())->toBe(1);
            expect(DB::connection('period')->table('document_relations')
                ->where('relation_type', 'quote_to_order')->count())->toBe(1);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('blocks sales order confirmation exceeding the customer credit limit without explicit acceptance', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $contact = Contact::query()->create([
                'title' => 'V4 risk-limited buyer', 'type' => 'legal', 'risk_limit' => '40',
            ]);
            $order = app(SaveSalesDocumentDraft::class)->handle(
                DocumentType::SalesOrder,
                ['contact_id' => $contact->id, 'document_date' => '2026-10-10'],
                [[
                    'line_kind' => 'service', 'description' => 'Service',
                    'quantity' => '1.000', 'unit_price' => '80.0000', 'vat_rate' => '0',
                ]],
            );
            expect(fn () => app(ConfirmSalesOrder::class)->handle(
                $order, 'v4-'.Str::random(20),
            ))->toThrow(DomainException::class);

            expect($order->refresh()->status)->toBe('draft');

            $approved = app(ConfirmSalesOrder::class)->handle(
                $order, 'v4-'.Str::random(20), true,
            );
            expect($approved->status)->toBe('confirmed');
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
