<?php

use App\Actions\Finance\SaveBankAccount;
use App\Actions\Finance\SaveCashAccount;
use App\Models\Period\BankAccount;
use App\Models\Period\CashAccount;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('normalizes bank account codes, currencies and optional IBAN', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $bank = app(SaveBankAccount::class)->handle([
                'code' => ' bk'.Str::random(7).' ',
                'bank_name' => 'V4 Local Bank',
                'account_name' => 'Test Account',
                'currency' => 'try',
                'iban' => '',
            ]);

            expect($bank)->toBeInstanceOf(BankAccount::class)
                ->and($bank->currency)->toBe('TRY')
                ->and($bank->iban)->toBeNull()
                ->and($bank->code)->toBe(strtoupper(trim($bank->code)));
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('refuses malformed bank IBAN and invalid currencies without writing an account', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $action = app(SaveBankAccount::class);
            foreach ([
                ['code' => 'V4', 'bank_name' => 'Bank', 'account_name' => 'Acc', 'currency' => 'EU'],
                ['code' => 'V4', 'bank_name' => 'Bank', 'account_name' => 'Acc', 'iban' => 'INVALID'],
                ['code' => 'V4', 'bank_name' => ' ', 'account_name' => 'Acc'],
                ['code' => 'V4', 'bank_name' => 'Bank', 'account_name' => ' '],
            ] as $bad) {
                expect(fn () => $action->handle($bad))->toThrow(DomainException::class);
            }
            expect(BankAccount::query()->where('code', 'V4')->count())->toBe(0);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('creates and updates local cash accounts with optimistic versioning', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $action = app(SaveCashAccount::class);
            $cash = $action->handle(['code' => ' V4'.Str::random(7), 'name' => 'V4 Cash', 'currency' => 'try']);
            $oldVersion = (int) $cash->version;
            $updated = $action->handle([
                'code' => $cash->code, 'name' => 'Updated Cash', 'currency' => 'TRY',
            ], $cash, $oldVersion);

            expect($updated)->toBeInstanceOf(CashAccount::class)
                ->and($updated->name)->toBe('Updated Cash')
                ->and((int) $updated->version)->toBe($oldVersion + 1);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects illegal cash account names, codes and currency lengths', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            foreach ([
                ['code' => '', 'name' => 'Cash'],
                ['code' => 'V4', 'name' => ''],
                ['code' => 'V4', 'name' => 'Cash', 'currency' => 'US'],
            ] as $bad) {
                expect(fn () => app(SaveCashAccount::class)->handle($bad))
                    ->toThrow(DomainException::class);
            }
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
