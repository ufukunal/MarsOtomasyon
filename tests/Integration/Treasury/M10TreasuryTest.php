<?php

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\PostingPeriodStatus;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\PostingPeriod;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Treasury\Import\BankStatementParser;
use App\Modules\Treasury\Models\TreasuryAccount;
use App\Modules\Treasury\Models\TreasuryPayment;
use App\Modules\Treasury\Models\TreasuryPaymentMethod;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use ZipArchive;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('finalizes customer collections and supplier payments atomically, replay safely, and protects finalized authority rows', function (): void {
    [$company, $customer, $supplier, $user, $cash, , , $cashMethod, $bankMethod] = m10Fixture('M10-A');

    $this->actingAs($user)->withSession(['active_company_id' => $company->getKey()])
        ->post(route('treasury.payments.store'), [
            'direction' => 'collection',
            'account_id' => $customer->getKey(),
            'treasury_account_id' => $cash->getKey(),
            'payment_method_id' => $cashMethod->getKey(),
            'payment_date' => '2026-08-28',
            'amount' => '150.25',
            'reference' => 'COL-1',
        ])->assertRedirect();

    $collection = TreasuryPayment::query()->latest('id')->firstOrFail();
    $this->actingAs($user)->withSession(['active_company_id' => $company->getKey()])
        ->post(route('treasury.payments.finalize', $collection->getKey()))
        ->assertRedirect();

    expect((string) DB::table('treasury_movements')
        ->where('source_type', 'treasury_payment')
        ->where('source_id', (string) $collection->getKey())
        ->value('signed_amount'))->toBe('150.250000')
        ->and((string) DB::table('account_transactions')
            ->where('source_type', 'treasury_payment')
            ->where('source_id', (string) $collection->getKey())
            ->value('signed_amount'))->toBe('-150.250000')
        ->and((string) DB::table('treasury_balances')
            ->where('treasury_account_id', $cash->getKey())
            ->value('balance'))->toBe('150.250000');

    $this->actingAs($user)->withSession(['active_company_id' => $company->getKey()])
        ->post(route('treasury.payments.finalize', $collection->getKey()))
        ->assertRedirect();

    expect(DB::table('treasury_movements')
        ->where('source_type', 'treasury_payment')
        ->where('source_id', (string) $collection->getKey())
        ->count())->toBe(1)
        ->and(DB::table('account_transactions')
            ->where('source_type', 'treasury_payment')
            ->where('source_id', (string) $collection->getKey())
            ->count())->toBe(1);

    expect(fn () => DB::table('treasury_payments')->where('id', $collection->getKey())->delete())
        ->toThrow(QueryException::class);

    $this->actingAs($user)->withSession(['active_company_id' => $company->getKey()])
        ->post(route('treasury.payments.store'), [
            'direction' => 'payment',
            'account_id' => $supplier->getKey(),
            'treasury_account_id' => $cash->getKey(),
            'payment_method_id' => $bankMethod->getKey(),
            'payment_date' => '2026-08-28',
            'amount' => '40',
        ])->assertStatus(422);

    $supplierMethod = TreasuryPaymentMethod::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'CASH-PAY',
        'name' => 'Kasa Ödeme',
        'kind' => 'cash',
        'treasury_account_id' => $cash->getKey(),
        'is_active' => true,
    ]);

    $this->actingAs($user)->withSession(['active_company_id' => $company->getKey()])
        ->post(route('treasury.payments.store'), [
            'direction' => 'payment',
            'account_id' => $supplier->getKey(),
            'treasury_account_id' => $cash->getKey(),
            'payment_method_id' => $supplierMethod->getKey(),
            'payment_date' => '2026-08-28',
            'amount' => '40',
        ])->assertRedirect();

    $payment = TreasuryPayment::query()->latest('id')->firstOrFail();
    $this->actingAs($user)->withSession(['active_company_id' => $company->getKey()])
        ->post(route('treasury.payments.finalize', $payment->getKey()))
        ->assertRedirect();

    expect((string) DB::table('treasury_movements')
        ->where('source_type', 'treasury_payment')
        ->where('source_id', (string) $payment->getKey())
        ->value('signed_amount'))->toBe('-40.000000')
        ->and((string) DB::table('account_transactions')
            ->where('source_type', 'treasury_payment')
            ->where('source_id', (string) $payment->getKey())
            ->value('signed_amount'))->toBe('40.000000')
        ->and((string) DB::table('treasury_balances')
            ->where('treasury_account_id', $cash->getKey())
            ->value('balance'))->toBe('110.250000');
});

it('rejects forged finalization and direct mutation of treasury projections', function (): void {
    [$company, $customer, , , $cash, , , $cashMethod] = m10Fixture('M10-B');

    $payment = TreasuryPayment::query()->create([
        'company_id' => $company->getKey(),
        'account_id' => $customer->getKey(),
        'treasury_account_id' => $cash->getKey(),
        'payment_method_id' => $cashMethod->getKey(),
        'direction' => 'collection',
        'payment_kind' => 'cash',
        'status' => 'draft',
        'pos_status' => null,
        'payment_date' => '2026-08-28',
        'currency_code' => 'TRY',
        'amount' => '100.000000',
    ]);

    expect(fn () => DB::table('treasury_payments')->where('id', $payment->getKey())->update([
        'status' => 'finalized',
        'finalized_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('treasury_balances')->insert([
        'company_id' => $company->getKey(),
        'treasury_account_id' => $cash->getKey(),
        'balance' => '1.000000',
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('settles POS gross commission net, keeps settlement immutable, and reopens customer receivable on chargeback', function (): void {
    [$company, $customer, , $user, , $bank, $pos, , , $posMethod] = m10Fixture('M10-C');

    $this->actingAs($user)->withSession(['active_company_id' => $company->getKey()])
        ->post(route('treasury.payments.store'), [
            'direction' => 'collection',
            'account_id' => $customer->getKey(),
            'treasury_account_id' => $pos->getKey(),
            'payment_method_id' => $posMethod->getKey(),
            'payment_date' => '2026-08-28',
            'amount' => '100',
        ])->assertRedirect();

    $payment = TreasuryPayment::query()->firstOrFail();
    $this->actingAs($user)->withSession(['active_company_id' => $company->getKey()])
        ->post(route('treasury.payments.finalize', $payment->getKey()))
        ->assertRedirect();

    $this->actingAs($user)->withSession(['active_company_id' => $company->getKey()])
        ->post(route('treasury.payments.settle-pos', $payment->getKey()), [
            'bank_account_id' => $bank->getKey(),
            'settlement_date' => '2026-08-28',
            'commission_amount' => '3',
        ])->assertRedirect();

    $settlement = DB::table('treasury_pos_settlements')->first();
    expect($settlement)->not->toBeNull()
        ->and($payment->refresh()->pos_status)->toBe('settled')
        ->and((string) $settlement->gross_amount)->toBe('100.000000')
        ->and((string) $settlement->commission_amount)->toBe('3.000000')
        ->and((string) $settlement->net_amount)->toBe('97.000000')
        ->and((string) DB::table('treasury_balances')->where('treasury_account_id', $pos->getKey())->value('balance'))->toBe('0.000000')
        ->and((string) DB::table('treasury_balances')->where('treasury_account_id', $bank->getKey())->value('balance'))->toBe('97.000000');

    expect(fn () => DB::table('treasury_pos_settlements')->where('id', $settlement->id)->update(['net_amount' => '96.000000']))
        ->toThrow(QueryException::class);

    $this->actingAs($user)->withSession(['active_company_id' => $company->getKey()])
        ->post(route('treasury.payments.chargeback', $payment->getKey()), [
            'chargeback_date' => '2026-08-28',
        ])->assertRedirect();

    expect($payment->refresh()->pos_status)->toBe('chargeback')
        ->and((string) DB::table('treasury_balances')->where('treasury_account_id', $bank->getKey())->value('balance'))->toBe('-3.000000')
        ->and((string) DB::table('account_transactions')->where('source_type', 'treasury_pos_chargeback')->value('signed_amount'))->toBe('100.000000');
});

it('finalizes manual movements, same-currency transfers, expenses and denomination cash counts with immutable authority documents', function (): void {
    [$company, , , $user, $cash, $bank] = m10Fixture('M10-D');

    $this->actingAs($user)->withSession(['active_company_id' => $company->getKey()])
        ->post(route('treasury.manual-movements.store'), [
            'treasury_account_id' => $cash->getKey(),
            'operation' => 'cash_in',
            'movement_date' => '2026-08-28',
            'amount' => '1000',
        ])->assertRedirect();
    $manualId = (int) DB::table('treasury_manual_movements')->value('id');
    $this->actingAs($user)->withSession(['active_company_id' => $company->getKey()])
        ->post(route('treasury.manual-movements.finalize', $manualId))->assertRedirect();

    $this->actingAs($user)->withSession(['active_company_id' => $company->getKey()])
        ->post(route('treasury.transfers.store'), [
            'from_account_id' => $cash->getKey(),
            'to_account_id' => $bank->getKey(),
            'transfer_date' => '2026-08-28',
            'amount' => '200',
        ])->assertRedirect();
    $transferId = (int) DB::table('treasury_transfers')->value('id');
    $this->actingAs($user)->withSession(['active_company_id' => $company->getKey()])
        ->post(route('treasury.transfers.finalize', $transferId))->assertRedirect();

    $this->actingAs($user)->withSession(['active_company_id' => $company->getKey()])
        ->post(route('treasury.expenses.store'), [
            'treasury_account_id' => $bank->getKey(),
            'expense_date' => '2026-08-28',
            'amount' => '50',
            'category' => 'Banka Masrafı',
        ])->assertRedirect();
    $expenseId = (int) DB::table('treasury_expenses')->value('id');
    $this->actingAs($user)->withSession(['active_company_id' => $company->getKey()])
        ->post(route('treasury.expenses.finalize', $expenseId))->assertRedirect();

    $this->actingAs($user)->withSession(['active_company_id' => $company->getKey()])
        ->post(route('treasury.cash-counts.store'), [
            'treasury_account_id' => $cash->getKey(),
            'count_date' => '2026-08-28',
            'lines' => [
                ['denomination' => '250', 'quantity' => 3],
            ],
        ])->assertRedirect();
    $countId = (int) DB::table('treasury_cash_counts')->value('id');
    $this->actingAs($user)->withSession(['active_company_id' => $company->getKey()])
        ->post(route('treasury.cash-counts.finalize', $countId))->assertRedirect();

    expect(fn () => DB::table('treasury_manual_movements')->where('id', $manualId)->delete())->toThrow(QueryException::class);
    expect(fn () => DB::table('treasury_transfers')->where('id', $transferId)->delete())->toThrow(QueryException::class);
    expect(fn () => DB::table('treasury_expenses')->where('id', $expenseId)->delete())->toThrow(QueryException::class);
    expect(fn () => DB::table('treasury_cash_counts')->where('id', $countId)->delete())->toThrow(QueryException::class);

    expect((string) DB::table('treasury_balances')->where('treasury_account_id', $cash->getKey())->value('balance'))->toBe('750.000000')
        ->and((string) DB::table('treasury_balances')->where('treasury_account_id', $bank->getKey())->value('balance'))->toBe('150.000000')
        ->and((string) DB::table('treasury_cash_counts')->where('id', $countId)->value('variance'))->toBe('-50.000000');
});

it('imports bank statements idempotently and reconciles only exact same-account movements', function (): void {
    [$company, , , $user, , $bank] = m10Fixture('M10-E');

    $this->actingAs($user)->withSession(['active_company_id' => $company->getKey()])
        ->post(route('treasury.manual-movements.store'), [
            'treasury_account_id' => $bank->getKey(),
            'operation' => 'bank_in',
            'movement_date' => '2026-08-28',
            'amount' => '100',
        ])->assertRedirect();
    $manualId = (int) DB::table('treasury_manual_movements')->value('id');
    $this->actingAs($user)->withSession(['active_company_id' => $company->getKey()])
        ->post(route('treasury.manual-movements.finalize', $manualId))->assertRedirect();
    $movementId = (int) DB::table('treasury_movements')->where('source_type', 'treasury_manual_movement')->value('id');

    $csv = "booking_date,signed_amount,currency_code,reference,description,external_key\n"
        ."2026-08-28,100,TRY,REF-100,Banka hareketi,EXT-100\n";
    $upload = fn (): UploadedFile => UploadedFile::fake()->createWithContent('statement.csv', $csv);

    foreach ([1, 2] as $_) {
        $this->actingAs($user)->withSession(['active_company_id' => $company->getKey()])
            ->post(route('treasury.statements.import'), [
                'treasury_account_id' => $bank->getKey(),
                'format' => 'csv',
                'statement' => $upload(),
            ])->assertRedirect();
    }

    expect(DB::table('bank_statement_imports')->count())->toBe(1)
        ->and(DB::table('bank_statement_lines')->count())->toBe(1);

    $lineId = (int) DB::table('bank_statement_lines')->value('id');
    $this->actingAs($user)->withSession(['active_company_id' => $company->getKey()])
        ->post(route('treasury.statements.match', $lineId), ['movement_id' => $movementId])
        ->assertRedirect();

    expect(DB::table('bank_statement_lines')->where('id', $lineId)->value('match_status'))->toBe('matched')
        ->and((int) DB::table('bank_statement_lines')->where('id', $lineId)->value('matched_treasury_movement_id'))->toBe($movementId);

    expect(fn () => DB::table('bank_statement_imports')->delete())->toThrow(QueryException::class);
});

it('parses MT940 and XLSX with exact decimal strings and without binary floating-point conversion', function (): void {
    $parser = app(BankStatementParser::class);

    $mt940Path = tempnam(sys_get_temp_dir(), 'mars-mt940-');
    if ($mt940Path === false) {
        throw new RuntimeException('MT940 temp file could not be created.');
    }
    file_put_contents($mt940Path, ":20:START\n:61:260828C12345678901234,123456NTRFREF-A\n:86:High precision credit\n");
    $mt940 = $parser->parse('mt940', $mt940Path, 'TRY');
    unlink($mt940Path);

    expect($mt940)->toHaveCount(1)
        ->and($mt940[0]['signed_amount'])->toBe('12345678901234.123456')
        ->and($mt940[0]['booking_date'])->toBe('2026-08-28');

    $xlsxPath = tempnam(sys_get_temp_dir(), 'mars-xlsx-');
    if ($xlsxPath === false) {
        throw new RuntimeException('XLSX temp file could not be created.');
    }
    $zip = new ZipArchive;
    expect($zip->open($xlsxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
    $zip->addFromString('xl/worksheets/sheet1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    <row r="1">
      <c r="A1" t="inlineStr"><is><t>booking_date</t></is></c>
      <c r="B1" t="inlineStr"><is><t>signed_amount</t></is></c>
      <c r="C1" t="inlineStr"><is><t>currency_code</t></is></c>
      <c r="D1" t="inlineStr"><is><t>external_key</t></is></c>
    </row>
    <row r="2">
      <c r="A2" t="inlineStr"><is><t>2026-08-28</t></is></c>
      <c r="B2"><v>-9876543210987.654321</v></c>
      <c r="C2" t="inlineStr"><is><t>TRY</t></is></c>
      <c r="D2" t="inlineStr"><is><t>XLSX-1</t></is></c>
    </row>
  </sheetData>
</worksheet>
XML);
    $zip->close();

    $xlsx = $parser->parse('xlsx', $xlsxPath, 'TRY');
    unlink($xlsxPath);

    expect($xlsx)->toHaveCount(1)
        ->and($xlsx[0]['signed_amount'])->toBe('-9876543210987.654321')
        ->and($xlsx[0]['external_key'])->toBe('XLSX-1');
});

/**
 * @return array{Company,Account,Account,User,TreasuryAccount,TreasuryAccount,TreasuryAccount,TreasuryPaymentMethod,TreasuryPaymentMethod,TreasuryPaymentMethod}
 */
function m10Fixture(string $code): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $customer = m10CommercialAccount($company, 'CUS', AccountType::Customer, 'Müşteri '.$code);
    $supplier = m10CommercialAccount($company, 'SUP', AccountType::Supplier, 'Tedarikçi '.$code);

    PostingPeriod::query()->create([
        'company_id' => $company->getKey(),
        'code' => '2026-08',
        'name' => 'Ağustos 2026',
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-31',
        'status' => PostingPeriodStatus::Open,
        'closed_at' => null,
    ]);

    $user = User::query()->create([
        'name' => 'Treasury Manager',
        'email' => strtolower($code).'@treasury.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'is_active' => true,
        'joined_at' => now(),
    ]);
    $role = Role::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'treasury-manager',
        'name' => 'Treasury Manager',
        'is_active' => true,
    ]);
    foreach ([PermissionKey::TreasuryView, PermissionKey::TreasuryManage, PermissionKey::TreasuryReconcile] as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    $cash = TreasuryAccount::query()->create([
        'company_id' => $company->getKey(),
        'type' => 'cash',
        'code' => 'CASH',
        'name' => 'Merkez Kasa',
        'currency_code' => 'TRY',
        'is_active' => true,
    ]);
    $bank = TreasuryAccount::query()->create([
        'company_id' => $company->getKey(),
        'type' => 'bank',
        'code' => 'BANK',
        'name' => 'Ana Banka',
        'currency_code' => 'TRY',
        'is_active' => true,
        'bank_name' => 'Test Bank',
    ]);
    $pos = TreasuryAccount::query()->create([
        'company_id' => $company->getKey(),
        'type' => 'pos',
        'code' => 'POS',
        'name' => 'Ana POS',
        'currency_code' => 'TRY',
        'is_active' => true,
        'pos_provider' => 'Test POS',
    ]);

    $cashMethod = TreasuryPaymentMethod::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'CASH',
        'name' => 'Nakit',
        'kind' => 'cash',
        'treasury_account_id' => $cash->getKey(),
        'is_active' => true,
    ]);
    $bankMethod = TreasuryPaymentMethod::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'BANK',
        'name' => 'Banka',
        'kind' => 'bank',
        'treasury_account_id' => $bank->getKey(),
        'is_active' => true,
    ]);
    $posMethod = TreasuryPaymentMethod::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'POS',
        'name' => 'POS',
        'kind' => 'pos',
        'treasury_account_id' => $pos->getKey(),
        'is_active' => true,
    ]);

    return [$company, $customer, $supplier, $user, $cash, $bank, $pos, $cashMethod, $bankMethod, $posMethod];
}

function m10CommercialAccount(Company $company, string $code, AccountType $type, string $name): Account
{
    return Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => $code,
        'type' => $type,
        'status' => AccountStatus::Active,
        'legal_name' => $name,
        'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null,
        'tax_office' => null,
        'book_currency_code' => 'TRY',
        'due_days' => 0,
        'discount_rate' => '0.000000',
        'risk_limit' => '0.000000',
    ]);
}
