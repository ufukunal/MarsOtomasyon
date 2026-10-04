<?php

namespace App\Actions\Finance;

use App\Models\Period\BankMovement;
use App\Models\Period\Contact;
use App\Models\Period\ContactTransaction;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ReconcileBankStatement
{
    public function handle(
        int $statementMovementId,
        string $mode,
        string $idempotencyKey,
        ?int $bookMovementId = null,
        ?int $contactId = null,
        string $movementType = 'statement_created',
    ): BankMovement {
        MutationAuthorizer::authorize('bank_reconciliation.update');

        return IdempotencyKey::run(
            $idempotencyKey,
            'bank-statement.reconcile:'.$statementMovementId,
            function () use (
                $statementMovementId,
                $mode,
                $bookMovementId,
                $contactId,
                $movementType,
            ): BankMovement {
                if (! in_array($mode, ['existing', 'create'], true)) {
                    throw new DomainException('Mutabakat modu existing veya create olmalıdır.');
                }

                return DB::connection('period')->transaction(function () use (
                    $statementMovementId,
                    $mode,
                    $bookMovementId,
                    $contactId,
                    $movementType,
                ): BankMovement {
                    $statement = BankMovement::query()
                        ->lockForUpdate()
                        ->findOrFail($statementMovementId);

                    if ($statement->origin !== 'statement' || $statement->reconciled_movement_id !== null) {
                        throw new DomainException('Ekstre satırı mutabakat için uygun değil.');
                    }

                    if ($mode === 'existing') {
                        if ($bookMovementId === null) {
                            throw new DomainException('Mevcut hareketle eşleştirmede book hareketi zorunludur.');
                        }

                        $book = BankMovement::query()->lockForUpdate()->findOrFail($bookMovementId);
                        $this->assertCompatible($statement, $book);

                        if (BankMovement::query()
                            ->where('origin', 'statement')
                            ->where('reconciled_movement_id', $book->id)
                            ->where('id', '!=', $statement->id)
                            ->exists()) {
                            throw new DomainException('Seçilen defter hareketi başka bir ekstre satırıyla zaten eşleştirilmiş.');
                        }
                    } else {
                        $movementType = trim($movementType);

                        if (! in_array($movementType, [
                            'statement_created',
                            'collection',
                            'payment',
                            'expense',
                            'advance',
                            'advance_return',
                            'manual',
                        ], true)) {
                            throw new DomainException('Ekstreden üretilecek finans hareketi türü geçersiz.');
                        }

                        $actor = auth()->user();
                        $account = $statement->account()->firstOrFail();

                        if ($account->currency !== 'TRY') {
                            throw new DomainException('Döviz ekstresinden yeni generic defter hareketi üretilemez; mevcut alış/ithalat hareketiyle eşleştirilmelidir.');
                        }

                        $contact = $contactId === null
                            ? null
                            : Contact::query()->lockForUpdate()->findOrFail($contactId);

                        $contactTransaction = null;

                        if ($contact !== null) {

                            $contactTransaction = ContactTransaction::query()->create([
                                'contact_id' => $contact->id,
                                'transaction_type' => $movementType,
                                'direction' => $statement->direction === 'in' ? 'credit' : 'debit',
                                'transaction_date' => $statement->movement_date,
                                'amount' => $statement->amount,
                                'currency' => 'TRY',
                                'description' => $statement->statement_description ?? $statement->description,
                                'created_by' => $actor?->id,
                                'created_by_name' => $actor?->name,
                            ]);
                        }

                        $book = BankMovement::query()->create([
                            'bank_account_id' => $statement->bank_account_id,
                            'contact_id' => $contact?->id,
                            'movement_date' => $statement->movement_date,
                            'direction' => $statement->direction,
                            'movement_type' => $movementType,
                            'amount' => $statement->amount,
                            'origin' => 'book',
                            'reference' => $statement->reference,
                            'group_key' => hash('sha256', 'statement:'.$statement->statement_fingerprint),
                            'description' => $statement->statement_description ?? $statement->description,
                            'metadata' => [
                                'statement_movement_id' => $statement->id,
                                'statement_fingerprint' => $statement->statement_fingerprint,
                                'contact_transaction_id' => $contactTransaction?->id,
                            ],
                            'created_by' => $actor?->id,
                            'created_by_name' => $actor?->name,
                        ]);
                    }

                    $actor = auth()->user();
                    $statement->setAttribute('reconciled_movement_id', $book->id);
                    $statement->setAttribute('reconciled_at', now());
                    $statement->setAttribute('reconciled_by', $actor?->id);
                    $statement->setAttribute('reconciled_by_name', $actor?->name);
                    $statement->save();

                    AuditContext::period(
                        'Banka ekstre satırı mutabık hale getirildi.',
                        [
                            'statement_movement_id' => $statement->id,
                            'book_movement_id' => $book->id,
                            'mode' => $mode,
                        ],
                        $statement,
                        'bank_statement_reconciled',
                    );

                    return $statement->refresh();
                }, attempts: 3);
            },
        );
    }

    private function assertCompatible(BankMovement $statement, BankMovement $book): void
    {
        if ($book->origin !== 'book'
            || $book->bank_account_id !== $statement->bank_account_id
            || $book->direction !== $statement->direction
            || bccomp((string) $book->amount, (string) $statement->amount, 4) !== 0) {
            throw new DomainException('Seçilen book hareketi ekstre satırıyla tutar/yön/hesap olarak eşleşmiyor.');
        }
    }
}
