<?php

namespace App\Support\Integrity\Checks;

use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ContactBalanceCheck implements IntegrityCheck
{
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

        $rows = DB::connection('period')->table('contact_transactions')
            ->selectRaw(
                "contact_id, currency, SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END)::text AS balance"
            )
            ->groupBy('contact_id', 'currency')
            ->get();

        return new IntegrityResult(
            checked: $rows->count(),
            mismatches: [],
            durationMs: $this->elapsed($started),
            meta: [
                'balances' => $rows->map(fn ($row) => [
                    'contact_id' => $row->contact_id,
                    'currency' => $row->currency,
                    'balance' => $row->balance,
                ])->all(),
                'scope' => 'Cari bakiyesi ayrıca saklanmadığı için ledger tek gerçek kaynaktır; bu kontrol normalize edilmiş rapor girdisini üretir.',
            ],
        );
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
