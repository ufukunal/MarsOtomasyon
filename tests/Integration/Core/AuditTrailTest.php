<?php

use App\Foundation\Correlation\CorrelationContext;
use App\Foundation\Logging\SensitiveDataRedactor;
use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\AuditEntry;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('requires an active business transaction before recording audit', function (): void {
    $company = auditCompany('AUD-A');
    $actor = auditActor($company, [PermissionKey::SettingsView, PermissionKey::SettingsManage], 'manager');
    auditContext($company, $actor, 'audit-boundary-001');

    expect(fn () => app(AuditRecorder::class)->record(
        AuditAction::CompanySettingsUpdated,
        AuditTargetType::Company,
        $company->getKey(),
        after: ['timezone' => 'Europe/Istanbul'],
    ))->toThrow(LogicException::class, 'Audit recording requires an active business transaction.');
});

it('records company settings mutation with actor company and correlation', function (): void {
    $company = auditCompany('AUD-B');
    $actor = auditActor($company, [PermissionKey::SettingsView, PermissionKey::SettingsManage], 'manager');

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'audit-company-001')
        ->withSession(['active_company_id' => $company->getKey()])
        ->put('/settings/company', [
            'base_currency_code' => 'USD',
            'timezone' => 'Europe/London',
        ])
        ->assertRedirect('/settings/company');

    $entry = AuditEntry::query()->firstOrFail();
    expect($entry->company_id)->toBe($company->getKey())
        ->and($entry->actor_user_id)->toBe($actor->getKey())
        ->and($entry->correlation_id)->toBe('audit-company-001')
        ->and($entry->action)->toBe(AuditAction::CompanySettingsUpdated->value)
        ->and($entry->before_state['base_currency_code'])->toBe('TRY')
        ->and($entry->after_state['base_currency_code'])->toBe('USD')
        ->and($entry->after_state['timezone'])->toBe('Europe/London');
});

it('redacts user email and never stores submitted password in audit state', function (): void {
    $company = auditCompany('AUD-C');
    $actor = auditActor($company, [PermissionKey::UserView, PermissionKey::UserManage], 'user-manager');

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'audit-user-001')
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/settings/users', [
            'name' => 'Yeni Kullanıcı',
            'email' => 'new.user@example.com',
            'password' => 'a-strong-password-123',
            'role_ids' => [],
        ])
        ->assertRedirect();

    $entry = AuditEntry::query()->where('action', AuditAction::UserCreated->value)->firstOrFail();
    expect($entry->after_state['email'])->toBe(SensitiveDataRedactor::REDACTED)
        ->and(array_key_exists('password', $entry->after_state))->toBeFalse();
});

it('rolls audit entry back with the business mutation', function (): void {
    $company = auditCompany('AUD-D');
    $actor = auditActor($company, [PermissionKey::SettingsManage], 'manager');
    auditContext($company, $actor, 'audit-rollback-001');

    expect(fn () => DB::transaction(function () use ($company): void {
        $company->timezone = 'Europe/London';
        $company->save();

        app(AuditRecorder::class)->record(
            AuditAction::CompanySettingsUpdated,
            AuditTargetType::Company,
            $company->getKey(),
            before: ['timezone' => 'Europe/Istanbul'],
            after: ['timezone' => 'Europe/London'],
        );

        throw new RuntimeException('rollback');
    }))->toThrow(RuntimeException::class, 'rollback');

    expect($company->refresh()->timezone)->toBe('Europe/Istanbul')
        ->and(AuditEntry::query()->count())->toBe(0);
});

it('rejects update and delete attempts at PostgreSQL level', function (): void {
    $company = auditCompany('AUD-E');
    $actor = auditActor($company, [PermissionKey::SettingsManage], 'manager');
    auditContext($company, $actor, 'audit-immutable-001');

    $entry = DB::transaction(fn (): AuditEntry => app(AuditRecorder::class)->record(
        AuditAction::CompanySettingsUpdated,
        AuditTargetType::Company,
        $company->getKey(),
        after: ['timezone' => 'Europe/Istanbul'],
    ));

    expect(fn () => DB::table('audit_entries')->where('id', $entry->getKey())->update(['action' => 'tampered']))
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('audit_entries')->where('id', $entry->getKey())->delete())
        ->toThrow(QueryException::class);
});

it('keeps the read-only audit viewer company scoped', function (): void {
    $companyA = auditCompany('AUD-F-A');
    $companyB = auditCompany('AUD-F-B');
    $actorA = auditActor($companyA, [PermissionKey::SettingsView], 'viewer');
    $actorB = auditActor($companyB, [PermissionKey::SettingsView], 'viewer');

    auditContext($companyB, $actorB, 'audit-foreign-001');
    $foreign = DB::transaction(fn (): AuditEntry => app(AuditRecorder::class)->record(
        AuditAction::CompanySettingsUpdated,
        AuditTargetType::Company,
        $companyB->getKey(),
        after: ['timezone' => 'Europe/Istanbul'],
    ));

    $this->actingAs($actorA)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/settings/audit/'.$foreign->getKey())
        ->assertNotFound();
});

function auditCompany(string $code): Company
{
    return Company::query()->create([
        'code' => $code,
        'name' => 'Company '.$code,
    ]);
}

function auditUser(string $email): User
{
    return User::query()->create([
        'name' => 'Audit User',
        'email' => $email,
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);
}

/** @param list<PermissionKey> $permissions */
function auditActor(Company $company, array $permissions, string $suffix): User
{
    $user = auditUser(strtolower((string) $company->code).'-'.$suffix.'@audit.test');
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'is_active' => true,
        'joined_at' => now(),
    ]);
    $role = Role::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'audit-'.$suffix,
        'name' => 'Audit '.$suffix,
        'is_active' => true,
    ]);

    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }

    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}

function auditContext(Company $company, User $actor, string $correlationId): void
{
    app(ActiveCompanyContext::class)->set($company);
    app(CorrelationContext::class)->set($correlationId);
    test()->actingAs($actor);
}
