<?php

namespace App\Actions\Sales;

use App\Actions\Numbering\GenerateDocumentNumber;
use App\Actions\Periods\EnsurePeriodOpen;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use Carbon\CarbonImmutable;
use DomainException;

final class SendQuoteToCustomerReview
{
    public function __construct(
        private readonly EnsurePeriodOpen $ensurePeriodOpen,
        private readonly GenerateDocumentNumber $numbers,
    ) {}

    public function handle(Document $quote, string $idempotencyKey): Document
    {
        MutationAuthorizer::authorize('quotes.update');

        return IdempotencyKey::run(
            $idempotencyKey,
            'quote.customer-review:'.$quote->id,
            function () use ($quote): Document {
                $locked = Document::query()->lockForUpdate()->findOrFail($quote->id);

                if ($locked->document_type !== DocumentType::Quote
                    || ! in_array($locked->status, ['draft', 'internal_review'], true)) {
                    throw new DomainException('Teklif müşteri incelemesine gönderilemez.');
                }

                $this->ensurePeriodOpen->handle(CarbonImmutable::parse($locked->document_date));

                if ($locked->number === null) {
                    $locked->number = $this->numbers->handle('quote', $locked->document_date->year);
                }

                $locked->status = 'customer_review';
                $locked->version = (int) $locked->version + 1;
                $locked->save();

                AuditContext::period(
                    'Teklif müşteri incelemesine gönderildi.',
                    ['quote_id' => $locked->id, 'number' => $locked->number],
                    $locked,
                    'quote_customer_review',
                );

                return $locked->refresh();
            },
        );
    }
}
