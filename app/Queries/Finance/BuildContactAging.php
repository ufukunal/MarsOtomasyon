<?php

namespace App\Queries\Finance;

use App\DataObjects\Finance\AgingLine;
use App\DataObjects\Finance\ContactAgingResult;
use App\Models\Period\ContactTransaction;
use Carbon\CarbonImmutable;

final class BuildContactAging
{
    public function handle(int $contactId, string $asOf): ContactAgingResult
    {
        $asOfDate = CarbonImmutable::parse($asOf)->startOfDay();

        $rows = ContactTransaction::query()
            ->where('contact_id', $contactId)
            ->whereDate('transaction_date', '<=', $asOfDate)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $balance = '0.0000';

        foreach ($rows as $row) {
            $balance = $row->direction === 'debit'
                ? bcadd($balance, (string) $row->amount, 4)
                : bcsub($balance, (string) $row->amount, 4);
        }

        $byId = $rows->keyBy('id');
        $excluded = [];

        foreach ($rows as $row) {
            if ($row->reversal_of_id === null) {
                continue;
            }

            $original = $byId->get($row->reversal_of_id);

            if (! $original
                || $original->direction === $row->direction
                || $original->currency !== $row->currency
                || bccomp((string) $original->amount, (string) $row->amount, 4) !== 0) {
                continue;
            }

            $excluded[(int) $row->id] = true;
            $excluded[(int) $original->id] = true;
        }

        $active = $rows->reject(fn ($row) => isset($excluded[(int) $row->id]));
        $credit = '0.0000';

        foreach ($active->where('direction', 'credit') as $row) {
            $credit = bcadd($credit, (string) $row->amount, 4);
        }

        $debits = $active
            ->where('direction', 'debit')
            ->sortBy(fn ($row) => sprintf(
                '%s|%s|%020d',
                $row->due_date?->toDateString() ?? $row->transaction_date->toDateString(),
                $row->transaction_date->toDateString(),
                $row->id,
            ))
            ->values();

        $lines = [];
        $openDebit = '0.0000';
        $buckets = [
            'future' => '0.0000',
            '1-30' => '0.0000',
            '31-60' => '0.0000',
            '61-90' => '0.0000',
            '91-120' => '0.0000',
            '120+' => '0.0000',
        ];

        foreach ($debits as $row) {
            $original = bcadd((string) $row->amount, '0', 4);
            $applied = bccomp($credit, '0', 4) > 0
                ? (bccomp($credit, $original, 4) >= 0 ? $original : $credit)
                : '0.0000';
            $remaining = bcsub($original, $applied, 4);
            $credit = bcsub($credit, $applied, 4);
            $due = CarbonImmutable::parse($row->due_date ?? $row->transaction_date)->startOfDay();
            $bucket = $this->bucket($due, $asOfDate);
            $color = bccomp($remaining, '0', 4) === 0
                ? 'green'
                : (bccomp($applied, '0', 4) > 0 ? 'yellow' : 'red');

            $lines[] = new AgingLine(
                transactionId: (int) $row->id,
                transactionDate: $row->transaction_date->toDateString(),
                dueDate: $due->toDateString(),
                originalAmount: $original,
                appliedCredit: $applied,
                remaining: $remaining,
                bucket: $bucket,
                color: $color,
            );

            $openDebit = bcadd($openDebit, $remaining, 4);
            $buckets[$bucket] = bcadd($buckets[$bucket], $remaining, 4);
        }

        $expectedOpen = bccomp($balance, '0', 4) > 0 ? $balance : '0.0000';
        $excessCredit = bccomp($balance, '0', 4) < 0 ? bcmul($balance, '-1', 4) : '0.0000';

        return new ContactAgingResult(
            lines: $lines,
            balance: $balance,
            openDebit: $expectedOpen,
            excessCredit: $excessCredit,
            buckets: $buckets,
        );
    }

    private function bucket(CarbonImmutable $due, CarbonImmutable $asOf): string
    {
        if ($due->greaterThan($asOf)) {
            return 'future';
        }

        $days = (int) $due->diffInDays($asOf);

        return match (true) {
            $days <= 30 => '1-30',
            $days <= 60 => '31-60',
            $days <= 90 => '61-90',
            $days <= 120 => '91-120',
            default => '120+',
        };
    }
}
