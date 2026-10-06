<?php

namespace App\Actions\Purchases;

use App\Actions\Documents\PostDocument;
use App\DataObjects\Documents\DocumentPostingContext;
use App\Enums\DocumentType;
use App\Models\Period\BankAccount;
use App\Models\Period\CashAccount;
use App\Models\Period\Contact;
use App\Models\Period\Document;
use App\Models\Period\DocumentRelation;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use DomainException;

final class PostPayment
{
    public function __construct(private readonly PostDocument $postDocument) {}

    public function handle(
        int $contactId,
        string $amount,
        string $currency,
        string $exchangeRate,
        string $documentDate,
        string $accountType,
        int $accountId,
        string $idempotencyKey,
        ?int $sourceInvoiceId = null,
        ?string $note = null,
    ): Document {
        MutationAuthorizer::authorize('payments.create');

        return IdempotencyKey::run(
            $idempotencyKey,
            'payment.create',
            function () use (
                $contactId,
                $amount,
                $currency,
                $exchangeRate,
                $documentDate,
                $accountType,
                $accountId,
                $idempotencyKey,
                $sourceInvoiceId,
                $note,
            ): Document {
                $normalized = bcadd($amount, '0', 4);
                $currency = strtoupper($currency);
                $exchangeRate = $currency === 'TRY'
                    ? '1.000000'
                    : bcadd($exchangeRate, '0', 6);

                if (bccomp($normalized, '0', 4) <= 0 || bccomp($exchangeRate, '0', 6) <= 0) {
                    throw new DomainException('Ödeme tutarı veya kuru geçersiz.');
                }

                $contact = Contact::query()->findOrFail($contactId);

                if (! in_array($accountType, ['cash', 'bank'], true)) {
                    throw new DomainException('Ödeme hesabı cash veya bank olmalıdır.');
                }

                $account = $accountType === 'cash'
                    ? CashAccount::query()->where('is_active', true)->findOrFail($accountId)
                    : BankAccount::query()->where('is_active', true)->findOrFail($accountId);

                if ($account->currency !== $currency) {
                    throw new DomainException('Ödeme hesabı para birimi ödeme para birimiyle eşleşmiyor.');
                }

                $sourceInvoice = null;

                if ($sourceInvoiceId !== null) {
                    $sourceInvoice = Document::query()->findOrFail($sourceInvoiceId);

                    if ($sourceInvoice->document_type !== DocumentType::SupplierInvoice
                        || $sourceInvoice->status !== 'posted'
                        || (int) $sourceInvoice->contact_id !== $contactId
                        || $sourceInvoice->currency !== $currency) {
                        throw new DomainException('Ödeme kaynak alış faturası geçersiz.');
                    }

                    $exchangeRate = (string) $sourceInvoice->exchange_rate;
                }

                $actor = auth()->user();
                $document = Document::query()->create([
                    'document_type' => DocumentType::Payment->value,
                    'revision_no' => 0,
                    'document_date' => $documentDate,
                    'contact_id' => $contact->id,
                    'currency' => $currency,
                    'exchange_rate' => $exchangeRate,
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
                        'relation_type' => 'payment_source',
                        'created_by' => $actor?->id,
                        'created_by_name' => $actor?->name,
                    ]);
                }

                $contactAmountTry = bcadd(bcmul($normalized, $exchangeRate, 8), '0', 4);

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
