<?php

namespace App\Support\Integrity\Checks;

use App\Enums\DocumentType;
use App\Models\Period\BankMovement;
use App\Models\Period\CashMovement;
use App\Models\Period\Document;
use App\Models\Period\Security;
use App\Models\Period\SecurityPayroll;
use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
use Illuminate\Support\Facades\Schema;

final class FinanceIntegrityCheck implements IntegrityCheck
{
    public function name(): string
    {
        return 'finance';
    }

    public function run(): IntegrityResult
    {
        $started = hrtime(true);

        if (! Schema::connection('period')->hasTable('securities')) {
            return new IntegrityResult(
                checked: 0,
                mismatches: [],
                durationMs: $this->elapsed($started),
                meta: ['status' => 'not_applicable'],
            );
        }

        $mismatches = [];
        $checked = 0;
        $transferGroups = [];

        foreach (CashMovement::query()->where('movement_type', 'transfer')->get() as $movement) {
            $checked++;
            $transferGroups[(string) $movement->group_key][] = [
                'direction' => $movement->direction,
                'amount' => (string) $movement->amount,
                'currency' => (string) $movement->account()->value('currency'),
            ];
        }

        foreach (BankMovement::query()->where('movement_type', 'transfer')->where('origin', 'book')->get() as $movement) {
            $checked++;
            $transferGroups[(string) $movement->group_key][] = [
                'direction' => $movement->direction,
                'amount' => (string) $movement->amount,
                'currency' => (string) $movement->account()->value('currency'),
            ];
        }

        foreach ($transferGroups as $groupKey => $rows) {
            if ($groupKey === ''
                || count($rows) !== 2
                || collect($rows)->where('direction', 'in')->count() !== 1
                || collect($rows)->where('direction', 'out')->count() !== 1
                || bccomp($rows[0]['amount'], $rows[1]['amount'], 4) !== 0
                || $rows[0]['currency'] !== $rows[1]['currency']) {
                $mismatches[] = [
                    'group_key' => $groupKey,
                    'reason' => 'finance_transfer_unbalanced',
                ];
            }
        }

        $statementRows = BankMovement::query()->where('origin', 'statement')->get();

        foreach ($statementRows as $statement) {
            $checked++;

            if ($statement->statement_fingerprint === null) {
                $mismatches[] = [
                    'movement_id' => $statement->id,
                    'reason' => 'statement_fingerprint_missing',
                ];
            }

            if ($statement->reconciled_movement_id === null) {
                continue;
            }

            $book = BankMovement::query()->find($statement->reconciled_movement_id);

            if (! $book
                || $book->origin !== 'book'
                || $book->bank_account_id !== $statement->bank_account_id
                || $book->direction !== $statement->direction
                || bccomp((string) $book->amount, (string) $statement->amount, 4) !== 0) {
                $mismatches[] = [
                    'movement_id' => $statement->id,
                    'reason' => 'statement_reconciliation_invalid',
                ];
            }
        }

        $documents = Document::query()
            ->where('status', 'posted')
            ->whereIn('document_type', [
                DocumentType::Expense->value,
                DocumentType::Advance->value,
                DocumentType::AdvanceReturn->value,
            ])
            ->get();

        foreach ($documents as $document) {
            $checked++;
            $cashCount = CashMovement::query()->where('document_id', $document->id)->count();
            $bankCount = BankMovement::query()
                ->where('document_id', $document->id)
                ->where('origin', 'book')
                ->count();

            if ($cashCount + $bankCount !== 1) {
                $mismatches[] = [
                    'document_id' => $document->id,
                    'reason' => 'finance_document_movement_count',
                ];
            }
        }

        $securities = Security::query()->get();

        foreach ($securities as $security) {
            $checked++;
            $invalid = match ($security->status) {
                'endorsed' => $security->endorsed_to_contact_id === null,
                'banked', 'collected', 'paid' => $security->bank_account_id === null,
                default => false,
            };

            if ($invalid) {
                $mismatches[] = [
                    'security_id' => $security->id,
                    'status' => $security->status,
                    'reason' => 'security_state_context_missing',
                ];
            }
        }

        $payrolls = SecurityPayroll::query()->get();

        foreach ($payrolls as $payroll) {
            $checked++;
            $ids = array_values(array_unique(array_map('intval', $payroll->security_ids)));
            $rows = Security::query()->whereIn('id', $ids)->get();
            $total = $rows->reduce(
                fn (string $sum, Security $security): string => bcadd($sum, (string) $security->amount, 4),
                '0.0000',
            );

            if ($rows->count() !== count($ids)
                || bccomp($total, (string) $payroll->total_amount, 4) !== 0) {
                $mismatches[] = [
                    'payroll_id' => $payroll->id,
                    'reason' => 'security_payroll_total_or_membership',
                ];
            }
        }

        return new IntegrityResult(
            checked: $checked,
            mismatches: $mismatches,
            durationMs: $this->elapsed($started),
        );
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
