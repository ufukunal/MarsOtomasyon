<?php

namespace App\Queries\Finance;

use App\Models\Period\BankMovement;
use Carbon\CarbonImmutable;

final class SuggestBankStatementMatches
{
    /** @return list<array{movement_id:int,score:int,reason:string}> */
    public function handle(int $statementMovementId, int $limit = 5): array
    {
        $statement = BankMovement::query()->findOrFail($statementMovementId);

        if ($statement->origin !== 'statement' || $statement->reconciled_movement_id !== null) {
            return [];
        }

        $date = CarbonImmutable::parse($statement->movement_date);
        $candidates = BankMovement::query()
            ->where('bank_account_id', $statement->bank_account_id)
            ->where('origin', 'book')
            ->where('direction', $statement->direction)
            ->where('amount', $statement->amount)
            ->whereBetween('movement_date', [
                $date->subDays(3)->toDateString(),
                $date->addDays(3)->toDateString(),
            ])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $ranked = [];

        foreach ($candidates as $candidate) {
            $score = 70;
            $reason = 'Tutar ve yön eşleşiyor';

            if ($statement->reference !== null
                && $candidate->reference !== null
                && mb_strtolower($statement->reference) === mb_strtolower($candidate->reference)) {
                $score = 100;
                $reason = 'Referans, tutar ve yön eşleşiyor';
            } elseif ($candidate->movement_date->equalTo($statement->movement_date)) {
                $score = 90;
                $reason = 'Tarih, tutar ve yön eşleşiyor';
            }

            $ranked[] = [
                'movement_id' => (int) $candidate->id,
                'score' => $score,
                'reason' => $reason,
            ];
        }

        usort($ranked, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($ranked, 0, max(1, $limit));
    }
}
