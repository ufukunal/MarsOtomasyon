<?php

use App\Actions\Purchases\SavePurchaseDocumentDraft;
use App\Enums\DocumentType;
use App\Models\Period\Contact;
use Illuminate\Support\Facades\DB;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('saves purchase-order lines with calculated totals and currency rates', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $supplier = Contact::query()->create([
                'title' => 'V4 purchase supplier', 'type' => 'legal',
            ]);
            $order = app(SavePurchaseDocumentDraft::class)->handle(
                DocumentType::PurchaseOrder,
                [
                    'contact_id' => $supplier->id,
                    'document_date' => '2026-10-10',
                    'currency' => 'EUR',
                    'exchange_rate' => '38.000000',
                ],
                [[
                    'line_kind' => 'service', 'description' => 'Consulting',
                    'quantity' => '3.000', 'unit_price' => '10.0000', 'vat_rate' => '20',
                ]],
            );

            expect($order->document_type)->toBe(DocumentType::PurchaseOrder)
                ->and($order->currency)->toBe('EUR')
                ->and($order->exchange_rate)->toBe('38.000000')
                ->and($order->subtotal)->toBe('30.0000')
                ->and($order->vat_amount)->toBe('6.0000')
                ->and($order->grand_total)->toBe('36.0000');

            expect($order->lines)->toHaveCount(1);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects a nonpositive exchange rate and leaves the purchase ledger untouched', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $supplier = Contact::query()->create([
                'title' => 'V4 purchasing rate test', 'type' => 'legal',
            ]);
            $before = DB::connection('period')->table('documents')->count();
            expect(fn () => app(SavePurchaseDocumentDraft::class)->handle(
                DocumentType::PurchaseOrder,
                ['contact_id' => $supplier->id, 'currency' => 'EUR', 'exchange_rate' => '0'],
                [['line_kind' => 'service', 'quantity' => '1', 'unit_price' => '20']],
            ))->toThrow(DomainException::class);

            expect(DB::connection('period')->table('documents')->count())->toBe($before);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
