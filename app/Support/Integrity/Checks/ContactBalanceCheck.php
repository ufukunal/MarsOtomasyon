<?php

namespace App\Support\Integrity\Checks;

use App\Enums\DocumentType;
use App\Models\Period\ContactTransaction;
use App\Models\Period\Document;
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

            if (bccomp((string) $transaction->amount, (string) $document->grand_total, 4) !== 0) {
                $mismatches[] = [
                    'document_id' => $document->id,
                    'reason' => 'contact_transaction_amount',
                ];
            }

            if ($document->document_type === DocumentType::SalesInvoice && $transaction->direction !== 'debit') {
                $mismatches[] = ['document_id' => $document->id, 'reason' => 'invoice_direction'];
            }

            if ($document->document_type === DocumentType::Collection && $transaction->direction !== 'credit') {
                $mismatches[] = ['document_id' => $document->id, 'reason' => 'collection_direction'];
            }

            if ($document->document_type === DocumentType::Collection) {
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
