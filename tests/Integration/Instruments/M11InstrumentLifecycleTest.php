<?php

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\PostingPeriodStatus;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\PostingPeriod;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Instruments\Actions\InstrumentOperations;
use App\Modules\Instruments\Files\InstrumentFileManager;
use App\Modules\Treasury\Models\TreasuryAccount;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(DatabaseMigrations::class);

beforeEach(function (): void { $this->withoutVite(); });

it('posts received delivery on the real delivery date and settles through treasury without a second cari effect', function (): void {
    [$company, $customer, , , $bank] = m11Fixture('M11-A');
    $ops = app(InstrumentOperations::class);
    $instrument = $ops->register((int) $company->getKey(), (int) $customer->getKey(), 'received', 'cheque', 'CHK-100', '100.25', 'TRY', '2026-08-12', '2026-08-30', '2026-08-01');

    $delivery = DB::table('account_transactions')->where('source_type', 'instrument')->where('source_id', (string) $instrument->getKey())->first();
    expect($instrument->status)->toBe('portfolio')->and((string) $delivery->signed_amount)->toBe('-100.250000')->and((string) $delivery->posting_date)->toBe('2026-08-12');

    $ops->sendToBank((int) $company->getKey(), (int) $instrument->getKey(), (int) $bank->getKey(), '2026-08-20');
    $settled = $ops->settle((int) $company->getKey(), (int) $instrument->getKey(), (int) $bank->getKey(), '2026-08-28');
    $ops->settle((int) $company->getKey(), (int) $instrument->getKey(), (int) $bank->getKey(), '2026-08-28');

    expect($settled->status)->toBe('collected')
        ->and(DB::table('account_transactions')->where('source_type', 'instrument')->where('source_id', (string) $instrument->getKey())->count())->toBe(1)
        ->and(DB::table('treasury_movements')->where('source_type', 'instrument')->where('source_id', (string) $instrument->getKey())->count())->toBe(1)
        ->and((string) DB::table('treasury_movements')->where('source_type', 'instrument')->where('source_id', (string) $instrument->getKey())->value('signed_amount'))->toBe('100.250000');
});

it('posts issued delivery once and settles the bank with the opposite treasury sign', function (): void {
    [$company, , $supplier, , $bank] = m11Fixture('M11-B');
    $ops = app(InstrumentOperations::class);
    $instrument = $ops->register((int) $company->getKey(), (int) $supplier->getKey(), 'issued', 'promissory_note', 'SNT-75', '75', 'TRY', '2026-08-10', '2026-08-31');
    $settled = $ops->settle((int) $company->getKey(), (int) $instrument->getKey(), (int) $bank->getKey(), '2026-08-25');

    expect($settled->status)->toBe('settled')
        ->and((string) DB::table('account_transactions')->where('source_type', 'instrument')->where('source_id', (string) $instrument->getKey())->value('signed_amount'))->toBe('75.000000')
        ->and(DB::table('account_transactions')->where('source_type', 'instrument')->where('source_id', (string) $instrument->getKey())->count())->toBe(1)
        ->and((string) DB::table('treasury_movements')->where('source_type', 'instrument')->where('source_id', (string) $instrument->getKey())->value('signed_amount'))->toBe('-75.000000');
});

it('reverses both customer delivery and supplier endorsement exactly once when an endorsed received instrument is dishonored', function (): void {
    [$company, $customer, $supplier] = m11Fixture('M11-C');
    $ops = app(InstrumentOperations::class);
    $instrument = $ops->register((int) $company->getKey(), (int) $customer->getKey(), 'received', 'cheque', 'CHK-CIRO', '200', 'TRY', '2026-08-05', '2026-08-25');
    $ops->endorse((int) $company->getKey(), (int) $instrument->getKey(), (int) $supplier->getKey(), '2026-08-15');
    $dishonored = $ops->dishonor((int) $company->getKey(), (int) $instrument->getKey(), '2026-08-25');
    $ops->dishonor((int) $company->getKey(), (int) $instrument->getKey(), '2026-08-25');

    $customerAmounts = DB::table('account_transactions')->where('source_type', 'instrument')->where('source_id', (string) $instrument->getKey())->where('account_id', $customer->getKey())->orderBy('id')->pluck('signed_amount')->map(fn ($v): string => (string) $v)->all();
    $supplierAmounts = DB::table('account_transactions')->where('source_type', 'instrument')->where('source_id', (string) $instrument->getKey())->where('account_id', $supplier->getKey())->orderBy('id')->pluck('signed_amount')->map(fn ($v): string => (string) $v)->all();
    expect($dishonored->status)->toBe('dishonored')->and($customerAmounts)->toBe(['-200.000000', '200.000000'])->and($supplierAmounts)->toBe(['200.000000', '-200.000000']);

    expect(fn () => DB::table('instrument_events')->where('instrument_id', $instrument->getKey())->update(['event_type' => 'cancelled']))->toThrow(QueryException::class);
    expect(fn () => DB::table('instruments')->where('id', $instrument->getKey())->update(['amount' => '201.000000']))->toThrow(QueryException::class);
});

it('tracks repeatable bank custody history without changing cari', function (): void {
    [$company, $customer, , , $bank] = m11Fixture('M11-D');
    $ops = app(InstrumentOperations::class);
    $instrument = $ops->register((int) $company->getKey(), (int) $customer->getKey(), 'received', 'cheque', 'CHK-HISTORY', '10', 'TRY', '2026-08-01', '2026-08-30');
    $ops->sendToBank((int) $company->getKey(), (int) $instrument->getKey(), (int) $bank->getKey(), '2026-08-10');
    $ops->recallFromBank((int) $company->getKey(), (int) $instrument->getKey(), '2026-08-11');
    $ops->sendToBank((int) $company->getKey(), (int) $instrument->getKey(), (int) $bank->getKey(), '2026-08-12');

    expect(DB::table('instrument_events')->where('instrument_id', $instrument->getKey())->where('event_type', 'sent_to_bank')->count())->toBe(2)
        ->and(DB::table('account_transactions')->where('source_type', 'instrument')->where('source_id', (string) $instrument->getKey())->count())->toBe(1);
});

it('exposes company-scoped RBAC routes and replaces active front/back private attachments', function (): void {
    [$company, $customer, , $user] = m11Fixture('M11-E');
    $this->actingAs($user)->withSession(['active_company_id' => $company->getKey()])->get(route('instruments.index'))->assertOk()->assertSee('Çek / Senet');
    $this->actingAs($user)->withSession(['active_company_id' => $company->getKey()])->post(route('instruments.store'), [
        'account_id' => $customer->getKey(), 'direction' => 'received', 'kind' => 'cheque', 'document_no' => 'WEB-1',
        'amount' => '50', 'currency_code' => 'TRY', 'delivery_date' => '2026-08-10', 'due_date' => '2026-08-31',
    ])->assertRedirect();
    $instrumentId = (int) DB::table('instruments')->value('id');

    Storage::fake('local');
    app(ActiveCompanyContext::class)->set($company);
    $files = app(InstrumentFileManager::class);
    $this->actingAs($user);
    $files->upload($instrumentId, UploadedFile::fake()->create('front-1.pdf', 10, 'application/pdf'), 'front');
    $files->upload($instrumentId, UploadedFile::fake()->create('front-2.pdf', 10, 'application/pdf'), 'front');
    expect(DB::table('attachments')->where('attachable_type', 'instrument')->where('attachable_id', $instrumentId)->where('label', 'front')->whereNull('detached_at')->count())->toBe(1)
        ->and(DB::table('attachments')->where('attachable_type', 'instrument')->where('attachable_id', $instrumentId)->where('label', 'front')->count())->toBe(2);
});

/** @return array{Company,Account,Account,User,TreasuryAccount} */
function m11Fixture(string $code): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $customer = m11Account($company, 'CUS', AccountType::Customer, 'Müşteri '.$code);
    $supplier = m11Account($company, 'SUP', AccountType::Supplier, 'Tedarikçi '.$code);
    PostingPeriod::query()->create(['company_id' => $company->getKey(), 'code' => '2026-08', 'name' => 'Ağustos 2026', 'starts_on' => '2026-08-01', 'ends_on' => '2026-08-31', 'status' => PostingPeriodStatus::Open, 'closed_at' => null]);
    $user = User::query()->create(['name' => 'Instrument Manager', 'email' => strtolower($code).'@instrument.test', 'password' => 'correct-password', 'status' => UserStatus::Active]);
    $membership = CompanyMembership::query()->create(['company_id' => $company->getKey(), 'user_id' => $user->getKey(), 'is_active' => true, 'joined_at' => now()]);
    $role = Role::query()->create(['company_id' => $company->getKey(), 'code' => 'instrument-manager', 'name' => 'Instrument Manager', 'is_active' => true]);
    foreach ([PermissionKey::InstrumentView, PermissionKey::InstrumentManage, PermissionKey::FileView, PermissionKey::FileManage] as $permission) app(GrantPermissionToRole::class)->handle($role, $permission);
    app(AssignRoleToMembership::class)->handle($membership, $role);
    $bank = TreasuryAccount::query()->create(['company_id' => $company->getKey(), 'type' => 'bank', 'code' => 'BANK', 'name' => 'Ana Banka', 'currency_code' => 'TRY', 'is_active' => true, 'bank_name' => 'Test Bank']);
    return [$company, $customer, $supplier, $user, $bank];
}

function m11Account(Company $company, string $code, AccountType $type, string $name): Account
{
    return Account::query()->create(['company_id' => $company->getKey(), 'code' => $code, 'type' => $type, 'status' => AccountStatus::Active, 'legal_name' => $name, 'trade_name' => null, 'tax_identity_type' => TaxIdentityType::None, 'tax_number' => null, 'tax_office' => null, 'book_currency_code' => 'TRY', 'due_days' => 0, 'discount_rate' => '0.000000', 'risk_limit' => '0.000000']);
}
