<?php

use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\DocumentType;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\AuditEntry;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\DocumentSequence;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('audits document sequence create and update with correlation and snapshots', function (): void {
    $company = documentSequenceAuditCompany('M15-AUDIT');
    $actor = documentSequenceAuditActor($company);

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'm15-sequence-create')
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/settings/numbering', [
            'document_type' => DocumentType::SalesInvoice->value,
            'series_code' => 'MAIN',
            'prefix' => 'FTR-',
            'padding' => 6,
            'next_value' => 10,
            'is_active' => '1',
        ])
        ->assertRedirect();

    $sequence = DocumentSequence::query()->firstOrFail();
    $created = AuditEntry::query()
        ->where('action', AuditAction::DocumentSequenceCreated->value)
        ->firstOrFail();

    expect($created->company_id)->toBe($company->getKey())
        ->and($created->actor_user_id)->toBe($actor->getKey())
        ->and($created->correlation_id)->toBe('m15-sequence-create')
        ->and($created->before_state)->toBeNull()
        ->and($created->after_state['document_type'])->toBe(DocumentType::SalesInvoice->value)
        ->and($created->after_state['series_code'])->toBe('main')
        ->and($created->after_state['prefix'])->toBe('FTR-')
        ->and($created->after_state['next_value'])->toBe(10);

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'm15-sequence-update')
        ->withSession(['active_company_id' => $company->getKey()])
        ->put('/settings/numbering/'.$sequence->getKey(), [
            'prefix' => 'EFTR-',
            'padding' => 8,
            'next_value' => 25,
            'is_active' => '1',
        ])
        ->assertRedirect();

    $updated = AuditEntry::query()
        ->where('action', AuditAction::DocumentSequenceUpdated->value)
        ->firstOrFail();

    expect($updated->correlation_id)->toBe('m15-sequence-update')
        ->and($updated->before_state['prefix'])->toBe('FTR-')
        ->and($updated->before_state['padding'])->toBe(6)
        ->and($updated->before_state['next_value'])->toBe(10)
        ->and($updated->after_state['prefix'])->toBe('EFTR-')
        ->and($updated->after_state['padding'])->toBe(8)
        ->and($updated->after_state['next_value'])->toBe(25);
});

function documentSequenceAuditCompany(string $code): Company
{
    return Company::query()->create([
        'code' => $code,
        'name' => 'Company '.$code,
    ]);
}

function documentSequenceAuditActor(Company $company): User
{
    $user = User::query()->create([
        'name' => 'M1.5 Numbering Audit User',
        'email' => strtolower((string) $company->code).'@m15-numbering-audit.test',
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
        'code' => 'm15-numbering-audit',
        'name' => 'M1.5 Numbering Audit',
        'is_active' => true,
    ]);

    foreach ([PermissionKey::SettingsView, PermissionKey::SettingsManage] as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }

    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}
