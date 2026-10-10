<?php

use App\Actions\Finance\PostFinanceTransfer;
use App\Actions\Finance\ReverseFinanceTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('writes a balanced, idempotent cash-to-cash transfer and reverses both sides', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $db = DB::connection('period');
            $accounts = [];
            foreach (['From', 'To'] as $label) {
                $accounts[] = $db->table('cash_accounts')->insertGetId([
                    'code' => 'C-'.Str::random(12),
                    'name' => 'V4 '.$label,
                    'currency' => 'TRY',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            $action = app(PostFinanceTransfer::class);
            $key = 'v4-'.Str::random(20);
            $pair = $action->handle('cash', $accounts[0], 'cash', $accounts[1], '45', '2026-10-10', $key);
            expect($pair['source']->direction)->toBe('out')
                ->and($pair['target']->direction)->toBe('in')
                ->and($pair['source']->amount)->toBe('45.0000')
                ->and($pair['target']->amount)->toBe('45.0000');

            $replayed = $action->handle('cash', $accounts[0], 'cash', $accounts[1], '45', '2026-10-10', $key);
            expect($replayed['source']->id)->toBe($pair['source']->id);

            $group = (string) $pair['source']->group_key;
            expect($db->table('cash_movements')->where('group_key', $group)->count())->toBe(2);

            $reversed = app(ReverseFinanceTransfer::class)->handle(
                $group, '2026-10-10', 'V4 correction', 'v4-'.Str::random(20),
            );
            expect($reversed)->toHaveCount(2)
                ->and($db->table('cash_movements')->whereIn('reversal_of_id', [
                    $pair['source']->id, $pair['target']->id,
                ])->count())->toBe(2);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('refuses transferring funds between the same account without mutating the ledger', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            expect(fn () => app(PostFinanceTransfer::class)->handle(
                'cash', 12, 'cash', 12, '1', '2026-10-10', 'v4-'.Str::random(20),
            ))->toThrow(DomainException::class);
            expect(DB::connection('period')->table('cash_movements')->count())->toBe(0);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
