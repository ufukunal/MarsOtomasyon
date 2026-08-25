<?php

use App\Foundation\Logging\SensitiveDataRedactor;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountBankAccount;
use App\Modules\Accounts\Models\AccountNote;
use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Enums\AttachmentTargetType;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Attachment;
use App\Modules\Core\Models\AuditEntry;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\FileAsset;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
    Storage::fake('local');
});

it('updates bank accounts and notes atomically with normalized bank data and redacted audit state', function (): void {
    $company = m24Company('M24-A');
    $actor = m24Actor($company, [PermissionKey::AccountView, PermissionKey::AccountManage], 'manager');
    $account = m24Account($company, 'M24-001');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/customers/'.$account->getKey().'/records/edit')
        ->assertOk()
        ->assertSee('Banka Hesapları')
        ->assertSee('Dahili Notlar')
        ->assertSee('Cari Dosyaları');

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'm24-records-001')
        ->withSession(['active_company_id' => $company->getKey()])
        ->put('/customers/'.$account->getKey().'/records', [
            'bank_accounts' => [[
                'bank_name' => ' Mars Bank ',
                'branch_name' => 'Merkez',
                'account_holder' => 'Mars A.Ş.',
                'iban' => 'TR33 0006 1005 1978 6457 8413 26',
                'account_number' => ' 12345-ABC ',
                'swift_code' => 'MARSBANK',
                'currency_code' => 'try',
                'is_default' => '1',
                'note' => 'Tahsilat hesabı',
            ]],
            'notes' => [[
                'body' => ' Bu not audit kaydına düz metin girmemelidir. ',
                'is_pinned' => '1',
            ]],
        ])
        ->assertRedirect('/customers/'.$account->getKey());

    $bank = AccountBankAccount::query()->where('account_id', $account->getKey())->firstOrFail();
    $note = AccountNote::query()->where('account_id', $account->getKey())->firstOrFail();

    expect($bank->bank_name)->toBe('Mars Bank')
        ->and($bank->iban)->toBe('TR330006100519786457841326')
        ->and($bank->account_number)->toBe('12345-ABC')
        ->and($bank->currency_code)->toBe('TRY')
        ->and($bank->is_default)->toBeTrue()
        ->and($note->body)->toBe('Bu not audit kaydına düz metin girmemelidir.')
        ->and($note->is_pinned)->toBeTrue()
        ->and($note->created_by_user_id)->toBe($actor->getKey())
        ->and($note->updated_by_user_id)->toBe($actor->getKey());

    $audit = AuditEntry::query()->where('action', AuditAction::AccountRecordsUpdated->value)->firstOrFail();
    $serialized = json_encode($audit->after_state, JSON_THROW_ON_ERROR);

    expect($audit->correlation_id)->toBe('m24-records-001')
        ->and($serialized)->not->toContain('TR330006100519786457841326')
        ->and($serialized)->not->toContain('12345-ABC')
        ->and($serialized)->not->toContain('Bu not audit kaydına düz metin girmemelidir.')
        ->and($serialized)->toContain(SensitiveDataRedactor::REDACTED)
        ->and($serialized)->toContain(hash('sha256', 'Bu not audit kaydına düz metin girmemelidir.'));
});

it('rejects invalid bank invariants and cross company child ids without partial writes', function (): void {
    $companyA = m24Company('M24-B-A');
    $companyB = m24Company('M24-B-B');
    $actorA = m24Actor($companyA, [PermissionKey::AccountView, PermissionKey::AccountManage], 'manager-a');
    $accountA = m24Account($companyA, 'M24-A');
    $accountB = m24Account($companyB, 'M24-B');

    $foreignBank = AccountBankAccount::query()->create([
        'company_id' => $companyB->getKey(),
        'account_id' => $accountB->getKey(),
        'bank_name' => 'Foreign Bank',
        'branch_name' => null,
        'account_holder' => null,
        'iban' => 'TR330006100519786457841326',
        'account_number' => null,
        'swift_code' => null,
        'currency_code' => 'TRY',
        'is_default' => true,
        'note' => null,
    ]);

    $this->actingAs($actorA)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->from('/customers/'.$accountA->getKey().'/records/edit')
        ->put('/customers/'.$accountA->getKey().'/records', [
            'bank_accounts' => [[
                'id' => $foreignBank->getKey(),
                'bank_name' => 'Injected',
                'iban' => 'TR330006100519786457841326',
                'currency_code' => 'TRY',
                'is_default' => '1',
            ]],
            'notes' => [['body' => 'Bu not rollback olmalı', 'is_pinned' => '0']],
        ])
        ->assertRedirect('/customers/'.$accountA->getKey().'/records/edit')
        ->assertSessionHasErrors('bank_accounts.0.id');

    expect($foreignBank->refresh()->bank_name)->toBe('Foreign Bank')
        ->and(AccountNote::query()->where('account_id', $accountA->getKey())->count())->toBe(0);

    $this->actingAs($actorA)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->from('/customers/'.$accountA->getKey().'/records/edit')
        ->put('/customers/'.$accountA->getKey().'/records', [
            'bank_accounts' => [
                ['bank_name' => 'A', 'iban' => 'TR330006100519786457841326', 'currency_code' => 'TRY', 'is_default' => '1'],
                ['bank_name' => 'B', 'account_number' => 'X-2', 'currency_code' => 'TRY', 'is_default' => '1'],
            ],
            'notes' => [],
        ])
        ->assertSessionHasErrors('bank_accounts');

    expect(AccountBankAccount::query()->where('account_id', $accountA->getKey())->count())->toBe(0);

    expect(fn () => DB::table('account_notes')->insert([
        'company_id' => $companyA->getKey(),
        'account_id' => $accountB->getKey(),
        'body' => 'Invalid tenant link',
        'is_pinned' => false,
        'created_by_user_id' => $actorA->getKey(),
        'updated_by_user_id' => $actorA->getKey(),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('uses account permissions for records and keeps viewer mutations forbidden', function (): void {
    $company = m24Company('M24-C');
    $viewer = m24Actor($company, [PermissionKey::AccountView], 'viewer');
    $account = m24Account($company, 'M24-003');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/customers/'.$account->getKey())
        ->assertOk();

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/customers/'.$account->getKey().'/records/edit')
        ->assertForbidden();

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->put('/customers/'.$account->getKey().'/records', ['bank_accounts' => [], 'notes' => []])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/customers/'.$account->getKey().'/files', [
            'file' => UploadedFile::fake()->createWithContent('blocked.txt', 'blocked'),
        ])
        ->assertForbidden();
});

it('stores account files privately and prevents cross account and cross company attachment access', function (): void {
    $companyA = m24Company('M24-D-A');
    $companyB = m24Company('M24-D-B');
    $managerA = m24Actor($companyA, [PermissionKey::AccountView, PermissionKey::AccountManage], 'manager-a');
    $viewerA = m24Actor($companyA, [PermissionKey::AccountView], 'viewer-a');
    $accountA = m24Account($companyA, 'M24-D1');
    $otherA = m24Account($companyA, 'M24-D2');
    $foreignB = m24Account($companyB, 'M24-D3');
    $content = 'Private account document';

    $this->actingAs($managerA)
        ->withHeader('X-Correlation-ID', 'm24-file-001')
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->post('/customers/'.$accountA->getKey().'/files', [
            'file' => UploadedFile::fake()->createWithContent('contract.txt', $content),
            'label' => 'Cari Sözleşmesi',
        ])
        ->assertRedirect('/customers/'.$accountA->getKey().'/records/edit');

    $attachment = Attachment::query()->firstOrFail();
    $asset = FileAsset::query()->firstOrFail();

    expect($attachment->attachable_type->value)->toBe(AttachmentTargetType::Account->value)
        ->and($attachment->attachable_id)->toBe($accountA->getKey())
        ->and($attachment->company_id)->toBe($companyA->getKey())
        ->and($asset->sha256)->toBe(hash('sha256', $content));
    Storage::disk('local')->assertExists((string) $asset->storage_key);
    Storage::disk('public')->assertMissing((string) $asset->storage_key);

    $this->actingAs($viewerA)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/customers/'.$accountA->getKey().'/files/'.$attachment->getKey().'/download')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->actingAs($viewerA)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/customers/'.$otherA->getKey().'/files/'.$attachment->getKey().'/download')
        ->assertNotFound();

    expect(fn () => DB::table('attachments')->insert([
        'company_id' => $companyA->getKey(),
        'file_asset_id' => $asset->getKey(),
        'attachable_type' => AttachmentTargetType::Account->value,
        'attachable_id' => $foreignB->getKey(),
        'label' => null,
        'attached_by_user_id' => $managerA->getKey(),
        'attached_at' => now(),
        'detached_at' => null,
        'detached_by_user_id' => null,
    ]))->toThrow(QueryException::class);
});

it('reuses the shared file security policy for dangerous account uploads and preserves originals on detach', function (): void {
    $company = m24Company('M24-E');
    $manager = m24Actor($company, [PermissionKey::AccountView, PermissionKey::AccountManage], 'manager');
    $account = m24Account($company, 'M24-005');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->from('/customers/'.$account->getKey().'/records/edit')
        ->post('/customers/'.$account->getKey().'/files', [
            'file' => UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;'),
        ])
        ->assertRedirect('/customers/'.$account->getKey().'/records/edit')
        ->assertSessionHasErrors('file');

    expect(FileAsset::query()->count())->toBe(0);

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/customers/'.$account->getKey().'/files', [
            'file' => UploadedFile::fake()->createWithContent('archive.txt', 'keep account original'),
        ])
        ->assertRedirect();

    $attachment = Attachment::query()->firstOrFail();
    $asset = FileAsset::query()->firstOrFail();
    $storageKey = (string) $asset->storage_key;

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/customers/'.$account->getKey().'/files/'.$attachment->getKey().'/detach')
        ->assertRedirect('/customers/'.$account->getKey().'/records/edit');

    $attachment->refresh();
    $asset->refresh();
    expect($attachment->detached_at)->not->toBeNull()
        ->and($asset->archived_at)->not->toBeNull();
    Storage::disk('local')->assertExists($storageKey);
});

function m24Company(string $code): Company
{
    return Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
}

/** @param list<PermissionKey> $permissions */
function m24Actor(Company $company, array $permissions, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'M2.4 '.$suffix,
        'email' => strtolower((string) $company->code).'-'.$suffix.'@m24.test',
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
        'code' => 'm24-'.$suffix,
        'name' => 'M2.4 '.$suffix,
        'is_active' => true,
    ]);
    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}

function m24Account(Company $company, string $code): Account
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
