<?php

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountB2BPolicy;
use App\Modules\B2B\Enums\B2BUserStatus;
use App\Modules\B2B\Models\B2BUser;
use App\Modules\Core\Models\Company;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('renders a dedicated external B2B login without using the internal web guard', function (): void {
    $this->get('/b2b/login')
        ->assertOk()
        ->assertSee('Mars B2B')
        ->assertSee('Firma Kodu')
        ->assertSee('Bayi Girişi');

    $this->get('/b2b')
        ->assertRedirect('/b2b/login');

    $this->assertGuest('b2b');
    $this->assertGuest('web');
});

it('authenticates a pre-bound active B2B user by company code and email and records login time', function (): void {
    [$company, $account, $policy, $user] = m19B2BFixture('M19-A');

    $response = $this->post('/b2b/login', [
        'company_code' => mb_strtolower((string) $company->code),
        'email' => mb_strtoupper((string) $user->email),
        'password' => 'correct-password',
    ]);

    $response->assertRedirect('/b2b');
    $this->assertAuthenticatedAs($user, 'b2b');
    $this->assertGuest('web');

    expect($user->fresh()?->last_login_at)->not->toBeNull()
        ->and($policy->is_enabled)->toBeTrue();

    $this->get('/b2b')
        ->assertOk()
        ->assertSee('Bayi Paneli')
        ->assertSee((string) $account->legal_name)
        ->assertSee((string) $user->name);
});

it('uses an immutable server-generated ULID public identity while keeping numeric primary keys private', function (): void {
    [, , , $user] = m19B2BFixture('M19-B');
    $publicId = $user->getAttribute('public_id');

    expect($user->getKey())->toBeInt()
        ->and($publicId)->toBeString()
        ->and($publicId)->toHaveLength(26)
        ->and($publicId)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');

    expect(fn () => $user->forceFill(['public_id' => (string) Str::ulid()])->save())
        ->toThrow(LogicException::class);
});

it('enforces public identity immutability in PostgreSQL too', function (): void {
    [, , , $user] = m19B2BFixture('M19-C');

    expect(fn () => DB::table('b2b_users')
        ->where('id', $user->getKey())
        ->update(['public_id' => (string) Str::ulid()]))
        ->toThrow(QueryException::class);
});

it('rejects disabled account policy with the same public credential error', function (): void {
    [$company, , $policy, $user] = m19B2BFixture('M19-D');
    $policy->update(['is_enabled' => false]);

    $this->post('/b2b/login', [
        'company_code' => (string) $company->code,
        'email' => (string) $user->email,
        'password' => 'correct-password',
    ])->assertSessionHasErrors([
        'email' => 'Giriş bilgileri geçersiz.',
    ]);

    $this->assertGuest('b2b');
});

it('revokes an existing B2B session when the account policy is deactivated', function (): void {
    [, , $policy, $user] = m19B2BFixture('M19-E');

    $this->actingAs($user, 'b2b')
        ->get('/b2b')
        ->assertOk();

    $policy->update(['is_enabled' => false]);

    $this->get('/b2b')
        ->assertRedirect('/b2b/login')
        ->assertSessionHasErrors([
            'email' => 'Bayi erişiminiz devre dışı.',
        ]);

    $this->assertGuest('b2b');
});

it('rate limits repeated B2B credential failures per normalized company email and client address', function (): void {
    [$company, , , $user] = m19B2BFixture('M19-F');

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->post('/b2b/login', [
            'company_code' => mb_strtolower((string) $company->code),
            'email' => mb_strtoupper((string) $user->email),
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    $this->post('/b2b/login', [
        'company_code' => (string) $company->code,
        'email' => (string) $user->email,
        'password' => 'correct-password',
    ])->assertSessionHasErrors([
        'email' => 'Çok fazla giriş denemesi. Kısa süre sonra tekrar deneyin.',
    ]);

    $this->assertGuest('b2b');
});

it('logs out the B2B guard and invalidates prior session data', function (): void {
    [, , , $user] = m19B2BFixture('M19-G');

    $response = $this
        ->actingAs($user, 'b2b')
        ->withSession(['b2b_sensitive_marker' => 'must-disappear'])
        ->post('/b2b/logout');

    $response
        ->assertRedirect('/b2b/login')
        ->assertSessionMissing('b2b_sensitive_marker');

    $this->assertGuest('b2b');
});

/** @return array{Company, Account, AccountB2BPolicy, B2BUser} */
function m19B2BFixture(string $code): array
{
    $company = Company::query()->create([
        'code' => $code,
        'name' => 'Company '.$code,
    ]);

    $account = Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => $code.'-CUSTOMER',
        'type' => AccountType::Customer,
        'status' => AccountStatus::Active,
        'legal_name' => 'Bayi '.$code,
        'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null,
        'tax_office' => null,
        'book_currency_code' => 'TRY',
        'due_days' => 0,
        'discount_rate' => '10.000000',
        'risk_limit' => '25000.000000',
    ]);

    $policy = AccountB2BPolicy::query()->create([
        'company_id' => $company->getKey(),
        'account_id' => $account->getKey(),
        'is_enabled' => true,
        'allow_orders' => true,
        'show_stock' => true,
        'show_invoices' => true,
        'show_statement' => true,
        'allow_address_management' => true,
    ]);

    $user = B2BUser::query()->create([
        'company_id' => $company->getKey(),
        'account_id' => $account->getKey(),
        'name' => 'B2B User '.$code,
        'email' => mb_strtoupper($code).'@B2B.TEST',
        'password' => 'correct-password',
        'status' => B2BUserStatus::Active,
    ])->refresh();

    return [$company, $account, $policy, $user];
}
