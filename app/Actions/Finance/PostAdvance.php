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

final class PostAdvance
{
    public function __construct(private readonly PostDocument $postDocument) {}

    public function handle(
        string $operation,
        int $contactId,
        string $amount,
        string $documentDate,
        string $accountType,
        int $accountId,
        string $currency,
        string $exchangeRate,
        string $idempotencyKey,
        ?string $note = null,
    ): Document {
        MutationAuthorizer::authorize('advances.create');

        return IdempotencyKey::run(
            $idempotencyKey,
            'advance.'.$operation,
            function () use (
                $operation,
                $contactId,
                $amount,
                $documentDate,
                $accountType,
                $accountId,
                $currency,
                $exchangeRate,
                $idempotencyKey,
                $note,
            ): Document {
                if (! in_array($operation, ['give', 'return'], true)) {
                    throw new DomainException('Avans işlem türü give veya return olmalıdır.');
                }

                $normalized = bcadd($amount, '0', 4);
                $rate = strtoupper($currency) === 'TRY'
                    ? '1.000000'
                    : bcadd($exchangeRate, '0', 6);

                if (bccomp($normalized, '0', 4) <= 0 || bccomp($rate, '0', 6) <= 0) {
                    throw new DomainException('Avans tutarı veya kuru geçersiz.');
                }

                $contact = Contact::query()->findOrFail($contactId);
                $type = $operation === 'give'
                    ? DocumentType::Advance
                    : DocumentType::AdvanceReturn;
                $actor = auth()->user();

                $document = Document::query()->create([
                    'document_type' => $type->value,
                    'revision_no' => 0,
                    'document_date' => $documentDate,
                    'contact_id' => $contact->id,
                    'currency' => strtoupper($currency),
                    'exchange_rate' => $rate,
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

                $contactAmountTry = bcadd(bcmul($normalized, $rate, 8), '0', 4);

                return $this->postDocument->handle(
                    $document,
                    hash('sha256', $idempotencyKey.':post'),
                    new DocumentPostingContext(
                        accountType: $accountType,
                        accountId: $accountId,
                        contactAmount: $contactAmountTry,
                        contactCurrency: 'TRY',
                    ),
                );
            },
        );
    }
}
