<?php

use App\Actions\Purchases\ApprovePurchaseOrder;
use App\Actions\Purchases\SendPurchaseOrderToSupplier;
use App\Actions\Purchases\SubmitPurchaseOrderForApproval;
use App\Actions\Returns\ReverseReturnDocument;
use App\Actions\Sales\ApproveQuote;
use App\Actions\Sales\CreateQuoteRevision;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

function marsTestWorkflowDocument(string $type, string $status, ?string $number = null, int $revision = 0): Document
{
    return Document::query()->create([
        'document_type' => $type,
        'status' => $status,
        'number' => $number,
        'revision_no' => $revision,
        'document_date' => '2026-10-10',
    ]);
}

it('approves reviewed quotes exactly once and refuses draft quotes', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $quote = marsTestWorkflowDocument('quote', 'internal_review', 'Q-V4-'.Str::random(8), 1);
            $action = app(ApproveQuote::class);
            $first = $action->handle($quote, 'v4-'.Str::random(16));

            expect($first->status)->toBe('approved')
                ->and((int) $first->version)->toBe(2);
            expect(fn () => $action->handle($quote, 'v4-'.Str::random(16)))
                ->toThrow(DomainException::class);

            $draft = marsTestWorkflowDocument('quote', 'draft', null, 1);
            expect(fn () => $action->handle($draft, 'v4-'.Str::random(16)))
                ->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('requires purchase order lines for submission and forbids sending an unapproved order', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $order = marsTestWorkflowDocument('purchase_order', 'draft');

            expect(fn () => app(SubmitPurchaseOrderForApproval::class)
                ->handle($order, 'v4-'.Str::random(16)))
                ->toThrow(DomainException::class);
            expect(fn () => app(SendPurchaseOrderToSupplier::class)
                ->handle($order, 'v4-'.Str::random(16)))
                ->toThrow(DomainException::class);
            expect($order->refresh()->status)->toBe('draft');
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('moves an approved purchase order to sent without duplicating the document', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $order = marsTestWorkflowDocument('purchase_order', 'pending_approval');
            $approved = app(ApprovePurchaseOrder::class)->handle($order, 'v4-'.Str::random(16));
            expect($approved->status)->toBe('approved');
            expect($approved->number)->not->toBeNull();

            $sent = app(SendPurchaseOrderToSupplier::class)->handle($approved, 'v4-'.Str::random(16));
            expect($sent->status)->toBe('sent')
                ->and($sent->id)->toBe($order->id);
            expect(DB::connection('period')->table('documents')->whereKey($order->id)->count())->toBe(1);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects revisions of unnumbered quotes and return reversals without an audit reason', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $quote = marsTestWorkflowDocument('quote', 'draft', null, 1);
            expect(fn () => app(CreateQuoteRevision::class)
                ->handle($quote, 'v4-'.Str::random(16)))
                ->toThrow(DomainException::class);

            $ret = marsTestWorkflowDocument(DocumentType::SalesReturn->value, 'posted');
            expect(fn () => app(ReverseReturnDocument::class)
                ->handle($ret, '2026-10-10', ' ', 'v4-'.Str::random(16)))
                ->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
