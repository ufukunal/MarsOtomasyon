<?php

use App\Actions\Finance\CreateSecurity;
use App\Actions\Finance\PostAdvance;
use App\Actions\Finance\PostContactDebitCredit;
use App\Actions\Finance\PostExpense;
use App\Actions\Finance\PostSecurityPayroll;
use App\Actions\Finance\ReconcileBankStatement;
use App\Actions\Finance\ReverseSecurityPayroll;
use App\Models\Period\SecurityPayroll;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('rejects invalid cheque and note direction, amounts and maturity ordering', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $action = app(CreateSecurity::class);

            foreach ([
                ['backwards', 'check', 'C-100', '5', '2026-10-10', '2026-11-01'],
                ['incoming', 'unknown', 'C-100', '5', '2026-10-10', '2026-11-01'],
                ['incoming', 'check', '', '5', '2026-10-10', '2026-11-01'],
                ['incoming', 'check', 'C-100', '0', '2026-10-10', '2026-11-01'],
                ['incoming', 'check', 'C-100', '5', '2026-10-10', '2026-10-01'],
            ] as [$direction, $kind, $number, $amount, $date, $due]) {
                expect(fn () => $action->handle(
                    $direction, $kind, $number, 1, $amount, $date, $due, 'v4-'.Str::random(16),
                ))->toThrow(DomainException::class);
            }

            expect(DB::connection('period')->table('securities')->count())->toBe(0);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects empty and unsupported cheque payrolls and reasonless reversals', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $action = app(PostSecurityPayroll::class);
            expect(fn () => $action->handle('unsupported', [1], '2026-10-10', 'v4-'.Str::random(16)))
                ->toThrow(DomainException::class);
            expect(fn () => $action->handle('collection', [], '2026-10-10', 'v4-'.Str::random(16)))
                ->toThrow(DomainException::class);

            expect(fn () => app(ReverseSecurityPayroll::class)->handle(
                new SecurityPayroll, '2026-10-10', ' ', 'v4-'.Str::random(16),
            ))->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects disallowed expense currencies, negative VAT and malformed advances', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            expect(fn () => app(PostExpense::class)->handle(
                'rent', '100', '-2', '2026-10-10', 'cash', 1, 'TRY', '1', 'v4-'.Str::random(16),
            ))->toThrow(DomainException::class);
            expect(fn () => app(PostExpense::class)->handle(
                'rent', '100', '20', '2026-10-10', 'cash', 1, 'USD', '35', 'v4-'.Str::random(16),
            ))->toThrow(DomainException::class);
            expect(fn () => app(PostAdvance::class)->handle(
                'invalid', 1, '10', '2026-10-10', 'cash', 1, 'TRY', '1', 'v4-'.Str::random(16),
            ))->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('requires valid contact debit/credit directions and bank statement reconciliation modes', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            expect(fn () => app(PostContactDebitCredit::class)->handle(
                1, 'both', '5', '2026-10-10', 'Correction', 'v4-'.Str::random(16),
            ))->toThrow(DomainException::class);
            expect(fn () => app(PostContactDebitCredit::class)->handle(
                1, 'debit', '5', '2026-10-10', ' ', 'v4-'.Str::random(16),
            ))->toThrow(DomainException::class);
            expect(fn () => app(ReconcileBankStatement::class)->handle(
                1, 'auto-link-all', 'v4-'.Str::random(16),
            ))->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
