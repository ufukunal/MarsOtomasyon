<?php

namespace App\Support\Integrity\Checks;

use App\Enums\DocumentType;
use App\Models\Period\ContactTransaction;
use App\Models\Period\Document;
use App\Models\Period\DocumentRelation;
use App\Queries\Finance\BuildContactAging;
use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ContactBalanceCheck implements IntegrityCheck
{
    public function __construct(private readonly BuildContactAging $aging) {}

    public function name(): string
    {
        return 'contacts';
    }

    public function run(): IntegrityResult
    {
        $started = hrtime(true);

        if (! Schema::connection('period')->hasTable('contact_transactions')) {
            return new IntegrityResult(
                checked: 0,
                mismatches: [],
                durationMs: $this->elapsed($started),
                meta: ['status' => 'not_applicable', 'reason' => 'contact ledger henüz kurulmadı'],
            );
        }

        $contacts = ContactTransaction::query()
            ->select('contact_id')
            ->distinct()
            ->pluck('contact_id');
        $mismatches = [];

        foreach ($contacts as $contactId) {
            $aging = $this->aging->handle((int) $contactId, now()->toDateString());
            $expectedOpen = bccomp($aging->balance, '0', 4) > 0 ? $aging->balance : '0.0000';

            if (bccomp($aging->openDebit, $expectedOpen, 4) !== 0) {
                $mismatches[] = [
                    'contact_id' => $contactId,
                    'reason' => 'aging_balance_mismatch',
                    'balance' => $aging->balance,
                    'open_debit' => $aging->openDebit,
                ];
            }
        }

        $documents = Document::query()
            ->where('status', 'posted')
            ->whereIn('document_type', [
                DocumentType::SalesInvoice->value,
                DocumentType::Collection->value,
                DocumentType::ContactDebitCredit->value,
                DocumentType::SupplierInvoice->value,
                DocumentType::Payment->value,
            ])
            ->get();

        foreach ($documents as $document) {
            $transactions = ContactTransaction::query()
                ->where('document_id', $document->id)
                ->get();

            if ($transactions->count() !== 1) {
                $mismatches[] = [
                    'document_id' => $document->id,
                    'reason' => 'contact_transaction_count',
                    'count' => $transactions->count(),
                ];

                continue;
            }

            $transaction = $transactions->first();

            $isReversal = DocumentRelation::query()
                ->where('relation_type', 'reversal_of')
                ->where('source_document_id', $document->id)
                ->exists();

            $expectedAmount = in_array($document->document_type, [
                DocumentType::SupplierInvoice,
                DocumentType::Payment,
            ], true)
                ? bcadd(
                    bcmul((string) $document->grand_total, (string) $document->exchange_rate, 8),
                    '0',
                    4,
                )
                : (string) $document->grand_total;
            $expectedCurrency = in_array($document->document_type, [
                DocumentType::SupplierInvoice,
                DocumentType::Payment,
            ], true)
                ? 'TRY'
                : (string) $document->currency;

            if (bccomp((string) $transaction->amount, $expectedAmount, 4) !== 0
                || $transaction->currency !== $expectedCurrency) {
                $mismatches[] = [
                    'document_id' => $document->id,
                    'reason' => 'contact_transaction_amount_or_currency',
                ];
            }

            $expectedDirection = match ($document->document_type) {
                DocumentType::SalesInvoice => 'debit',
                DocumentType::Collection => 'credit',
                DocumentType::SupplierInvoice => 'credit',
                DocumentType::Payment => 'debit',
                default => null,
            };

            if ($expectedDirection !== null) {
                if ($isReversal) {
                    $expectedDirection = $expectedDirection === 'debit' ? 'credit' : 'debit';
                }

                if ($transaction->direction !== $expectedDirection) {
                    $mismatches[] = [
                        'document_id' => $document->id,
                        'reason' => 'contact_transaction_direction',
                    ];
                }
            }

            if (in_array($document->document_type, [DocumentType::Collection, DocumentType::Payment], true)) {
                $cashCount = DB::connection('period')->table('cash_movements')
                    ->where('document_id', $document->id)
                    ->count();
                $bankCount = DB::connection('period')->table('bank_movements')
                    ->where('document_id', $document->id)
                    ->count();

                if ($cashCount + $bankCount !== 1) {
                    $mismatches[] = [
                        'document_id' => $document->id,
                        'reason' => 'collection_finance_movement_count',
                    ];
                }
            }
        }

        $reversals = ContactTransaction::query()->whereNotNull('reversal_of_id')->get();

        foreach ($reversals as $reversal) {
            $original = ContactTransaction::query()->find($reversal->reversal_of_id);

            if (! $original
                || $original->contact_id !== $reversal->contact_id
                || $original->currency !== $reversal->currency
                || $original->direction === $reversal->direction
                || bccomp((string) $original->amount, (string) $reversal->amount, 4) !== 0) {
                $mismatches[] = [
                    'transaction_id' => $reversal->id,
                    'reason' => 'invalid_contact_reversal',
                ];
            }
        }

        return new IntegrityResult(
            checked: $contacts->count() + $documents->count() + $reversals->count(),
            mismatches: $mismatches,
            durationMs: $this->elapsed($started),
        );
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
