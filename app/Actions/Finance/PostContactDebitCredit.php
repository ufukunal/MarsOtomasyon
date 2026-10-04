<?php

namespace App\Actions\Finance;

use App\Actions\Documents\PostDocument;
use App\DataObjects\Documents\DocumentPostingContext;
use App\Enums\DocumentType;
use App\Models\Period\Contact;
use App\Models\Period\Document;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use DomainException;

final class PostContactDebitCredit
{
    public function __construct(private readonly PostDocument $postDocument) {}

    public function handle(
        int $contactId,
        string $direction,
        string $amount,
        string $documentDate,
        string $reason,
        string $idempotencyKey,
        ?string $note = null,
    ): Document {
        MutationAuthorizer::authorize('contacts.update');

        return IdempotencyKey::run(
            $idempotencyKey,
            'contact-debit-credit.create',
            function () use (
                $contactId,
                $direction,
                $amount,
                $documentDate,
                $reason,
                $idempotencyKey,
                $note,
            ): Document {
                if (! in_array($direction, ['debit', 'credit'], true)) {
                    throw new DomainException('Cari fiş yönü debit veya credit olmalıdır.');
                }

                $reason = trim($reason);

                if ($reason === '') {
                    throw new DomainException('Cari borç/alacak fişi gerekçesi zorunludur.');
                }

                $normalized = bcadd($amount, '0', 4);

                if (bccomp($normalized, '0', 4) <= 0) {
                    throw new DomainException('Cari fiş tutarı pozitif olmalıdır.');
                }

                $contact = Contact::query()->findOrFail($contactId);
                $actor = auth()->user();

                $document = Document::query()->create([
                    'document_type' => DocumentType::ContactDebitCredit->value,
                    'revision_no' => 0,
                    'document_date' => $documentDate,
                    'contact_id' => $contact->id,
                    'currency' => 'TRY',
                    'exchange_rate' => '1.000000',
                    'status' => 'draft',
                    'discount_rate' => '0.0000',
                    'discount_amount' => '0.0000',
                    'subtotal' => $normalized,
                    'tax_base' => $normalized,
                    'vat_amount' => '0.0000',
                    'rounding_difference' => '0.0000',
                    'grand_total' => $normalized,
                    'notes' => $note,
                    'created_by' => $actor?->id,
                    'created_by_name' => $actor?->name,
                ]);

                return $this->postDocument->handle(
                    $document,
                    hash('sha256', $idempotencyKey.':post'),
                    new DocumentPostingContext(
                        contactDirection: $direction,
                        reason: $reason,
                    ),
                );
            },
        );
    }
}
