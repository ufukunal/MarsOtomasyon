<?php

use App\Foundation\Idempotency\IdempotencyConflict;
use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Ledger\AccountTransactionPoster;
use App\Modules\Accounts\Ledger\AccountTransactionReverser;
use App\Modules\Accounts\Ledger\PostAccountTransactionData;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountTransaction;
use App\Modules\Core\Enums\PostingPeriodStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\PostingPeriod;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use LogicException;

uses(DatabaseMigrations::class);

it('posts one immutable signed account effect and safely replays the same source effect', function (): void {
    $company = m26Company('M26-A');
    $account = m26Account($company, 'M26-001');
    m26Period($company, '2026-08-01', '2026-08-31');
    $identity = m26Identity($company, 'invoice-1001', 'account.debit');

    $first = DB::transaction(fn (): AccountTransaction => app(AccountTransactionPoster::class)->post(
        new PostAccountTransactionData(
            accountId: (int) $account->getKey(),
            postingDate: '2026-08-25',
            signedAmount: '00125.5',
            sourceEffect: $identity,
            memo: ' Satış faturası ',
        ),
    ));

    $replay = DB::transaction(fn (): AccountTransaction => app(AccountTransactionPoster::class)->post(
        new PostAccountTransactionData(
            accountId: (int) $account->getKey(),
            postingDate: '2026-08-25',
            signedAmount: '125.500000',
            sourceEffect: $identity,
            memo: 'Satış faturası',
        ),
    ));

    expect($first->getKey())->toBe($replay->getKey())
        ->and($first->signed_amount)->toBe('125.500000')
        ->and($first->currency_code)->toBe('TRY')
        ->and($first->memo)->toBe('Satış faturası')
        ->and(AccountTransaction::query()->count())->toBe(1);

    expect(fn () => DB::transaction(fn (): AccountTransaction => app(AccountTransactionPoster::class)->post(
        new PostAccountTransactionData(
            accountId: (int) $account->getKey(),
            postingDate: '2026-08-25',
            signedAmount: '126',
            sourceEffect: $identity,
            memo: 'Satış faturası',
        ),
    )))->toThrow(IdempotencyConflict::class);

    expect(AccountTransaction::query()->count())->toBe(1);
});

it('requires the source business transaction and an open posting period', function (): void {
    $company = m26Company('M26-B');
    $account = m26Account($company, 'M26-002');
    m26Period($company, '2026-08-01', '2026-08-31', PostingPeriodStatus::Closed);
    $data = new PostAccountTransactionData(
        accountId: (int) $account->getKey(),
        postingDate: '2026-08-25',
        signedAmount: '10',
        sourceEffect: m26Identity($company, 'closed-period', 'account.debit'),
    );

    expect(fn () => app(AccountTransactionPoster::class)->post($data))
        ->toThrow(LogicException::class);

    expect(fn () => DB::transaction(fn (): AccountTransaction => app(AccountTransactionPoster::class)->post($data)))
        ->toThrow(DomainException::class);

    expect(AccountTransaction::query()->count())->toBe(0);
});

it('creates reversal as a new exact opposite row and never mutates the original', function (): void {
    $company = m26Company('M26-C');
    $account = m26Account($company, 'M26-003');
    m26Period($company, '2026-08-01', '2026-08-31');

    $original = DB::transaction(fn (): AccountTransaction => app(AccountTransactionPoster::class)->post(
        new PostAccountTransactionData(
            accountId: (int) $account->getKey(),
            postingDate: '2026-08-20',
            signedAmount: '-75.25',
            sourceEffect: m26Identity($company, 'payment-77', 'account.credit'),
        ),
    ));

    $reversalIdentity = m26Identity($company, 'payment-77-reversal', 'account.reversal');
    $reversal = DB::transaction(fn (): AccountTransaction => app(AccountTransactionReverser::class)->reverse(
        originalTransactionId: (int) $original->getKey(),
        postingDate: '2026-08-25',
        sourceEffect: $reversalIdentity,
        memo: 'Ödeme iptali',
    ));

    $replay = DB::transaction(fn (): AccountTransaction => app(AccountTransactionReverser::class)->reverse(
        originalTransactionId: (int) $original->getKey(),
        postingDate: '2026-08-25',
        sourceEffect: $reversalIdentity,
        memo: 'Ödeme iptali',
    ));

    expect($original->refresh()->signed_amount)->toBe('-75.250000')
        ->and($reversal->signed_amount)->toBe('75.250000')
        ->and($reversal->reversal_of_transaction_id)->toBe($original->getKey())
        ->and($replay->getKey())->toBe($reversal->getKey())
        ->and(AccountTransaction::query()->count())->toBe(2);

    expect(fn () => DB::transaction(fn (): AccountTransaction => app(AccountTransactionReverser::class)->reverse(
        originalTransactionId: (int) $original->getKey(),
        postingDate: '2026-08-25',
        sourceEffect: m26Identity($company, 'second-reversal', 'account.reversal'),
    )))->toThrow(DomainException::class);
});

it('enforces company currency period source and immutability rules at the PostgreSQL boundary', function (): void {
    $companyA = m26Company('M26-D-A');
    $companyB = m26Company('M26-D-B');
    $accountA = m26Account($companyA, 'M26-A');
    $accountB = m26Account($companyB, 'M26-B');
    $periodA = m26Period($companyA, '2026-08-01', '2026-08-31');
    $periodB = m26Period($companyB, '2026-08-01', '2026-08-31');

    $valid = DB::transaction(fn (): AccountTransaction => app(AccountTransactionPoster::class)->post(
        new PostAccountTransactionData(
            accountId: (int) $accountA->getKey(),
            postingDate: '2026-08-25',
            signedAmount: '50',
            sourceEffect: m26Identity($companyA, 'valid-row', 'account.debit'),
        ),
    ));

    expect(fn () => DB::table('account_transactions')->where('id', $valid->getKey())->update(['memo' => 'mutated']))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('account_transactions')->where('id', $valid->getKey())->delete())
        ->toThrow(QueryException::class);

    expect(fn () => m26DirectTransactionInsert(
        companyId: (int) $companyA->getKey(),
        accountId: (int) $accountA->getKey(),
        postingPeriodId: (int) $periodA->getKey(),
        currencyCode: 'USD',
        sourceId: 'bad-currency',
        fingerprint: str_repeat('a', 64),
    ))->toThrow(QueryException::class);

    expect(fn () => m26DirectTransactionInsert(
        companyId: (int) $companyA->getKey(),
        accountId: (int) $accountA->getKey(),
        postingPeriodId: (int) $periodB->getKey(),
        currencyCode: 'TRY',
        sourceId: 'bad-period',
        fingerprint: str_repeat('b', 64),
    ))->toThrow(QueryException::class);

    expect(fn () => m26DirectTransactionInsert(
        companyId: (int) $companyA->getKey(),
        accountId: (int) $accountB->getKey(),
        postingPeriodId: (int) $periodA->getKey(),
        currencyCode: 'TRY',
        sourceId: 'bad-company',
        fingerprint: str_repeat('c', 64),
    ))->toThrow(QueryException::class);

    expect(fn () => DB::transaction(fn (): AccountTransaction => app(AccountTransactionPoster::class)->post(
        new PostAccountTransactionData(
            accountId: (int) $accountB->getKey(),
            postingDate: '2026-08-25',
            signedAmount: '5',
            sourceEffect: m26Identity($companyA, 'cross-company-service', 'account.debit'),
        ),
    )))->toThrow(ModelNotFoundException::class);
});

function m26Company(string $code): Company
{
    return Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
}

function m26Account(Company $company, string $code): Account
{
    return Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => $code,
        'type' => AccountType::Customer,
        'status' => AccountStatus::Active,
        'legal_name' => 'Cari '.$code,
        'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null,
        'tax_office' => null,
        'book_currency_code' => 'TRY',
        'due_days' => 0,
        'discount_rate' => '0',
        'risk_limit' => '0',
    ]);
}

function m26Period(
    Company $company,
    string $startsOn,
    string $endsOn,
    PostingPeriodStatus $status = PostingPeriodStatus::Open,
): PostingPeriod {
    return PostingPeriod::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'P-'.$startsOn,
        'name' => 'Period '.$startsOn,
        'starts_on' => $startsOn,
        'ends_on' => $endsOn,
        'status' => $status,
        'closed_at' => $status === PostingPeriodStatus::Closed ? now() : null,
    ]);
}

function m26Identity(Company $company, string $sourceId, string $effectType): SourceEffectIdentity
{
    return new SourceEffectIdentity(
        companyId: (int) $company->getKey(),
        sourceType: 'test.account-source',
        sourceId: $sourceId,
        effectType: $effectType,
    );
}

function m26DirectTransactionInsert(
    int $companyId,
    int $accountId,
    int $postingPeriodId,
    string $currencyCode,
    string $sourceId,
    string $fingerprint,
): void {
    DB::table('account_transactions')->insert([
        'company_id' => $companyId,
        'account_id' => $accountId,
        'posting_period_id' => $postingPeriodId,
        'posting_date' => '2026-08-25',
        'currency_code' => $currencyCode,
        'signed_amount' => '1.000000',
        'source_type' => 'test.direct',
        'source_id' => $sourceId,
        'effect_type' => 'account.debit',
        'effect_fingerprint' => $fingerprint,
        'memo' => null,
        'reversal_of_transaction_id' => null,
        'created_at' => now(),
    ]);
}
