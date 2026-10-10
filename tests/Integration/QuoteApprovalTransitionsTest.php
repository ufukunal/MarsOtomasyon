<?php

use App\Actions\Sales\SendQuoteToCustomerReview;
use App\Actions\Sales\SubmitQuoteForInternalApproval;
use App\Models\Period\Document;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('submits a draft quote to internal review, then customer review, with a single document number', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $quote = Document::query()->create([
                'document_type' => 'quote',
                'status' => 'draft',
                'document_date' => '2026-10-10',
            ]);

            $first = app(SubmitQuoteForInternalApproval::class)->handle($quote, 'v4-'.Str::random(16));
            expect($first->status)->toBe('internal_review')
                ->and($first->number)->not->toBeNull();

            $number = $first->number;
            $second = app(SendQuoteToCustomerReview::class)->handle($first, 'v4-'.Str::random(16));
            expect($second->status)->toBe('customer_review')
                ->and($second->number)->toBe($number);

            expect(fn () => app(SubmitQuoteForInternalApproval::class)->handle(
                $second, 'v4-'.Str::random(16),
            ))->toThrow(DomainException::class);
            expect(fn () => app(SendQuoteToCustomerReview::class)->handle(
                $second, 'v4-'.Str::random(16),
            ))->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects customer review requests for a posted sales invoice', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $invoice = Document::query()->create([
                'document_type' => 'sales_invoice',
                'status' => 'draft',
                'document_date' => '2026-10-10',
            ]);

            expect(fn () => app(SendQuoteToCustomerReview::class)->handle($invoice, 'v4-'.Str::random(16)))
                ->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
