<?php

namespace App\Actions\Finance;

use App\DataObjects\Documents\DocumentPostingContext;
use App\Enums\DocumentType;
use App\Actions\Documents\PostDocument;
use App\Models\Period\BankAccount;
use App\Models\Period\CashAccount;
use App\Models\Period\Contact;
use App\Models\Period\Document;
use App\Models\Period\DocumentRelation;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use DomainException;

final class PostCollection
{
    public function __construct(private readonly PostDocument $postDocument) {}

    public function handle(
        int $contactId,
        string $amount,
        string $documentDate,
        string $accountType,
        int $accountId,
        string $idempotencyKey,
        ?int $sourceInvoiceId = null,
        ?string $note = null,
    ): Document {
        MutationAuthorizer::authorize('collections.create');

        return IdempotencyKey::run(
            $idempotencyKey,
            'collection.create',
            function () use (
                $contactId,
                $amount,
                $documentDate,
                $accountType,
                $accountId,
                $idempotencyKey,
                $sourceInvoiceId,
                $note,
            ): Document {
                $normalized = bcadd($amount, '0', 4);

                if (bccomp($normalized, '0', 4) <= 0) {
                    throw new DomainException('Tahsilat tutarı pozitif olmalıdır.');
                }

                $contact = Contact::query()->findOrFail($contactId);

                if (! in_array($accountType, ['cash', 'bank'], true)) {
                    throw new DomainException('Tahsilat hesabı cash veya bank olmalıdır.');
                }

                if ($accountType === 'cash') {
                    CashAccount::query()->where('is_active', true)->findOrFail($accountId);
                } else {
                    BankAccount::query()->where('is_active', true)->findOrFail($accountId);
                }

                $sourceInvoice = null;

                if ($sourceInvoiceId !== null) {
                    $sourceInvoice = Document::query()->findOrFail($sourceInvoiceId);

                    if ($sourceInvoice->document_type !== DocumentType::SalesInvoice
                        || $sourceInvoice->status !== 'posted'
                        || (int) $sourceInvoice->contact_id !== $contactId) {
                        throw new DomainException('Tahsilat kaynak faturası geçersiz.');
                    }
                }

                $actor = auth()->user();
                $document = Document::query()->create([
                    'document_type' => DocumentType::Collection->value,
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

                if ($sourceInvoice !== null) {
                    DocumentRelation::query()->create([
                        'source_document_id' => $document->id,
                        'target_document_id' => $sourceInvoice->id,
                        'relation_type' => 'collection_source',
                        'created_by' => $actor?->id,
                        'created_by_name' => $actor?->name,
                    ]);
                }

                return $this->postDocument->handle(
                    $document,
                    hash('sha256', $idempotencyKey.':post'),
                    new DocumentPostingContext(
                        accountType: $accountType,
                        accountId: $accountId,
                    ),
                );
            },
        );
    }
}
