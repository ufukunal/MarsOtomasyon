<?php

namespace App\Actions\Sales;

use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use DomainException;

final class ApproveQuote
{
    public function handle(Document $quote, string $idempotencyKey): Document
    {
        MutationAuthorizer::authorize('sales.quote.approve');

        return IdempotencyKey::run(
            $idempotencyKey,
            'quote.approve:'.$quote->id,
            function () use ($quote): Document {
                $locked = Document::query()->lockForUpdate()->findOrFail($quote->id);

                if ($locked->document_type !== DocumentType::Quote
                    || ! in_array($locked->status, ['internal_review', 'customer_review'], true)
                    || $locked->number === null) {
                    throw new DomainException('Teklif bu durumda onaylanamaz.');
                }

                $locked->status = 'approved';
                $locked->version = (int) $locked->version + 1;
                $locked->save();

                AuditContext::period(
                    'Teklif onaylandı.',
                    ['quote_id' => $locked->id, 'number' => $locked->number],
                    $locked,
                    'quote_approved',
                );

                return $locked->refresh();
            },
        );
    }
}
