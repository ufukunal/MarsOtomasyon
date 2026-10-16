<?php

namespace App\Actions\Purchases;

use App\Actions\Numbering\GenerateDocumentNumber;
use App\Actions\Periods\EnsurePeriodOpen;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use Carbon\CarbonImmutable;
use DomainException;

final class ApprovePurchaseQuote
{
    public function __construct(
        private readonly GenerateDocumentNumber $numbers,
        private readonly EnsurePeriodOpen $period,
    ) {}

    public function handle(Document $quote, string $key): Document
    {
        MutationAuthorizer::authorize('purchase_quotes.approve');

        return IdempotencyKey::run($key, 'purchase-quote.approve:'.$quote->id, function () use ($quote): Document {
            $locked = Document::query()->with('lines')->lockForUpdate()->findOrFail($quote->id);
            if ($locked->document_type !== DocumentType::PurchaseQuote || $locked->status !== 'draft'
                || $locked->lines->isEmpty()) {
                throw new DomainException('Yalnız satırlı taslak alış teklifi onaylanabilir.');
            }
            $this->period->handle(CarbonImmutable::parse($locked->document_date));
            $locked->number = $this->numbers->handle('purchase_quote', $locked->document_date->year);
            $locked->status = 'approved';
            $locked->version = (int) $locked->version + 1;
            $locked->save();

            AuditContext::period('Alış teklifi onaylandı.', ['purchase_quote_id' => $locked->id], $locked, 'purchase_quote_approved');

            return $locked->refresh();
        });
    }
}
