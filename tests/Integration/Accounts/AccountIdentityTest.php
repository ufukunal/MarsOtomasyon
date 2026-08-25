<?php

use App\Foundation\Correlation\CorrelationContext;
use App\Foundation\Logging\SensitiveDataRedactor;
use App\Modules\Accounts\Actions\CreateAccount;
use App\Modules\Accounts\Actions\CreateAccountData;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\AuditEntry;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(DatabaseMigrations::class);

it('creates a normalized company-scoped account with one book currency and redacted audit identity', function (): void {
    [$company, $actor] = m21AccountContext('M21-A');

    $account = app(CreateAccount::class)->handle(new CreateAccountData(
        code: ' ac-001 ',
        type: AccountType::Customer,
        legalName: '  Mars Aydınlatma A.Ş. ',
        tradeName: ' Mars ',
        taxIdentityType: TaxIdentityType::Tckn,
        taxNumber: '10000000146',
        taxOffice: ' Kadıköy ',
        bookCurrencyCode: 'try',
        dueDays: 30,
        discountRate: '12.500000',
        riskLimit: '150000.250000',
    ));

    $account->refresh();

    expect($account->company_id)->toBe($company->getKey())
        ->and($account->code)->toBe('AC-001')
        ->and($account->typeEnum())->toBe(AccountType::Customer)
        ->and($account->statusEnum())->toBe(AccountStatus::Active)
        ->and($account->legal_name)->toBe('Mars Aydınlatma A.Ş.')
        ->and($account->trade_name)->toBe('Mars')
        ->and($account->taxIdentityTypeEnum())->toBe(TaxIdentityType::Tckn)
        ->and($account->tax_number)->toBe('10000000146')
        ->and($account->tax_office)->toBe('Kadıköy')
        ->and($account->book_currency_code)->toBe('TRY')
        ->and($account->due_days)->toBe(30)
        ->and($account->discount_rate)->toBe('12.500000')
        ->and($account->risk_limit)->toBe('150000.250000')
        ->and($account->bookCurrency->code)->toBe('TRY');

    $audit = AuditEntry::query()->where('action', AuditAction::AccountCreated->value)->firstOrFail();
    expect($audit->company_id)->toBe($company->getKey())
        ->and($audit->actor_user_id)->toBe($actor->getKey())
        ->and($audit->correlation_id)->toBe('m2-1-account-test')
        ->and($audit->after_state['code'])->toBe('AC-001')
        ->and($audit->after_state['tax_number'])->toBe(SensitiveDataRedactor::REDACTED);
});

it('allows the same account code and tax identity in different companies', function (): void {
    [$companyA] = m21AccountContext('M21-B-A');
    $first = app(CreateAccount::class)->handle(m21AccountData('shared', '10000000146'));

    [$companyB] = m21AccountContext('M21-B-B');
    $second = app(CreateAccount::class)->handle(m21AccountData('SHARED', '10000000146'));

    expect($first->company_id)->toBe($companyA->getKey())
        ->and($second->company_id)->toBe($companyB->getKey())
        ->and(Account::query()->where('code', 'SHARED')->count())->toBe(2);
});

it('rejects duplicate account codes case-insensitively inside one company', function (): void {
    m21AccountContext('M21-C');
    app(CreateAccount::class)->handle(m21AccountData('cari-01', null, TaxIdentityType::None));

    expect(fn () => app(CreateAccount::class)->handle(m21AccountData('CARI-01', null, TaxIdentityType::None)))
        ->toThrow(ValidationException::class);
});

it('rejects a duplicate tax identity inside one company', function (): void {
    m21AccountContext('M21-D');
    app(CreateAccount::class)->handle(m21AccountData('CARI-01', '10000000146'));

    expect(fn () => app(CreateAccount::class)->handle(m21AccountData('CARI-02', '10000000146')))
        ->toThrow(ValidationException::class);
});

it('rejects invalid checksum inactive currency and invalid commercial limits before persistence', function (): void {
    m21AccountContext('M21-E');

    expect(fn () => app(CreateAccount::class)->handle(m21AccountData('BAD-TCKN', '10000000145')))
        ->toThrow(ValidationException::class);

    expect(fn () => app(CreateAccount::class)->handle(new CreateAccountData(
        code: 'BAD-CURRENCY',
        type: AccountType::Supplier,
        legalName: 'Supplier',
        tradeName: null,
        taxIdentityType: TaxIdentityType::None,
        taxNumber: null,
        taxOffice: null,
        bookCurrencyCode: 'ZZZ',
    )))->toThrow(ValidationException::class);

    expect(fn () => app(CreateAccount::class)->handle(new CreateAccountData(
        code: 'BAD-DISCOUNT',
        type: AccountType::Mixed,
        legalName: 'Mixed',
        tradeName: null,
        taxIdentityType: TaxIdentityType::None,
        taxNumber: null,
        taxOffice: null,
        bookCurrencyCode: 'TRY',
        discountRate: '100.000001',
    )))->toThrow(ValidationException::class);

    expect(Account::query()->count())->toBe(0);
});

it('keeps account identity and commercial bounds protected at PostgreSQL level', function (): void {
    [$company] = m21AccountContext('M21-F');

    $base = [
        'company_id' => $company->getKey(),
        'code' => 'DB-GUARD',
        'type' => AccountType::Customer->value,
        'status' => AccountStatus::Active->value,
        'legal_name' => 'DB Guard',
        'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None->value,
        'tax_number' => null,
        'tax_office' => null,
        'book_currency_code' => 'TRY',
        'due_days' => 0,
        'discount_rate' => '0',
        'risk_limit' => '0',
        'created_at' => now(),
        'updated_at' => now(),
    ];

    DB::table('accounts')->insert($base);

    expect(fn () => DB::table('accounts')->insert([...$base, 'code' => 'db-guard']))
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('accounts')->insert([
        ...$base,
        'code' => 'BAD-RISK',
        'risk_limit' => '-0.000001',
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('accounts')->insert([
        ...$base,
        'code' => 'BAD-IDENTITY',
        'tax_identity_type' => TaxIdentityType::Tckn->value,
        'tax_number' => null,
    ]))->toThrow(QueryException::class);
});

/** @return array{Company, User} */
function m21AccountContext(string $companyCode): array
{
    $company = Company::query()->create([
        'code' => $companyCode,
        'name' => 'Company '.$companyCode,
    ]);
    $actor = User::query()->create([
        'name' => 'M2.1 Account Actor '.$companyCode,
        'email' => strtolower($companyCode).'@m21-accounts.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);

    app(ActiveCompanyContext::class)->set($company);
    app(CorrelationContext::class)->set('m2-1-account-test');
    test()->actingAs($actor);

    return [$company, $actor];
}

function m21AccountData(string $code, ?string $taxNumber, TaxIdentityType $taxType = TaxIdentityType::Tckn): CreateAccountData
{
    return new CreateAccountData(
        code: $code,
        type: AccountType::Customer,
        legalName: 'Test Cari '.$code,
        tradeName: null,
        taxIdentityType: $taxType,
        taxNumber: $taxNumber,
        taxOffice: null,
        bookCurrencyCode: 'TRY',
    );
}
