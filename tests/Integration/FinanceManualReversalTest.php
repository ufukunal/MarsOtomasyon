<?php

use App\Actions\Finance\PostManualFinanceMovement;
use App\Actions\Finance\ReverseFinanceTransfer;
use App\Actions\Finance\ReverseManualFinanceMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('posts a manual cash entry and reverses it without editing the source ledger record', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $accountId = DB::connection('period')->table('cash_accounts')->insertGetId([
                'code' => 'V4'.Str::random(10), 'name' => 'Cash V4',
                'currency' => 'TRY', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $entry = app(PostManualFinanceMovement::class)->handle(
                'cash', $accountId, 'in', '52.5000', '2026-10-10', 'v4-'.Str::random(16),
            );
            expect($entry->direction)->toBe('in')
                ->and($entry->amount)->toBe('52.5000');

            $reversal = app(ReverseManualFinanceMovement::class)->handle(
                'cash', $entry->id, '2026-10-10', 'Wrong entry', 'v4-'.Str::random(16),
            );
            expect($reversal->direction)->toBe('out')
                ->and($reversal->reversal_of_id)->toBe($entry->id)
                ->and($entry->refresh()->direction)->toBe('in');

            expect(fn () => app(ReverseManualFinanceMovement::class)->handle(
                'cash', $entry->id, '2026-10-10', 'Duplicate', 'v4-'.Str::random(16),
            ))->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('blocks reserved movement kinds and an empty manual reversal justification', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            foreach (['transfer', 'reversal', 'statement'] as $forbidden) {
                expect(fn () => app(PostManualFinanceMovement::class)->handle(
                    'cash', 1, 'in', '50', '2026-10-10', 'v4-'.Str::random(16),
                    movementType: $forbidden,
                ))->toThrow(DomainException::class);
            }

            expect(fn () => app(ReverseManualFinanceMovement::class)->handle(
                'cash', 1, '2026-10-10', ' ', 'v4-'.Str::random(16),
            ))->toThrow(DomainException::class);
            expect(fn () => app(ReverseFinanceTransfer::class)->handle(
                ' ', '2026-10-10', 'No group', 'v4-'.Str::random(16),
            ))->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
