<?php

use App\Actions\Documents\SaveSalesDocumentDraft;
use App\Actions\Sales\ConvertProformaToInvoice;
use App\Actions\Sales\IssueProforma;
use App\Enums\DocumentType;
use App\Models\Period\Contact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('issues a quote-based proforma and converts it into an invoice draft without losing source lineage', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $contact = Contact::query()->create(['title' => 'V4 Proforma Buyer', 'type' => 'legal']);
            $quote = app(SaveSalesDocumentDraft::class)->handle(
                DocumentType::Quote,
                ['contact_id' => $contact->id, 'document_date' => '2026-10-10'],
                [[
                    'line_kind' => 'service',
                    'description' => 'V4 service',
                    'quantity' => '2.000',
                    'unit_price' => '10.0000',
                    'vat_rate' => '20',
                ]],
            );
            $quote->status = 'approved';
            $quote->number = 'V4-Q-'.Str::random(8);
            $quote->save();

            $key = 'v4-proforma-'.Str::random(18);
            $action = app(IssueProforma::class);
            $proforma = $action->handle($quote, '2026-10-10', $key);

            expect($proforma->status)->toBe('posted')
                ->and($proforma->document_type)->toBe(DocumentType::Proforma)
                ->and($proforma->lines)->toHaveCount(1)
                ->and($action->handle($quote, '2026-10-10', $key)->id)->toBe($proforma->id);

            $invoice = app(ConvertProformaToInvoice::class)->handle(
                $proforma, [], '2026-10-10', 'v4-invoice-'.Str::random(16),
            );

            expect($invoice->status)->toBe('draft')
                ->and($invoice->document_type)->toBe(DocumentType::SalesInvoice)
                ->and($invoice->lines)->toHaveCount(1)
                ->and((int) $invoice->lines[0]->source_line_id)->toBe((int) $proforma->lines[0]->id)
                ->and(DB::connection('period')->table('document_relations')
                    ->where('source_document_id', $proforma->id)
                    ->where('target_document_id', $invoice->id)
                    ->where('relation_type', 'proforma_to_invoice')->count())->toBe(1);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('does not issue proforma documents from an unapproved quote', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $quote = app(SaveSalesDocumentDraft::class)->handle(
                DocumentType::Quote,
                ['document_date' => '2026-10-10'],
                [['line_kind' => 'service', 'description' => 'Local', 'quantity' => '1', 'unit_price' => '5']],
            );
            $before = DB::connection('period')->table('documents')->count();

            expect(fn () => app(IssueProforma::class)->handle(
                $quote, '2026-10-10', 'v4-rejected-'.Str::random(10),
            ))->toThrow(DomainException::class);

            expect(DB::connection('period')->table('documents')->count())->toBe($before);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
