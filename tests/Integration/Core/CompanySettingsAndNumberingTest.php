<?php

use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Enums\DocumentType;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\DocumentSequence;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Numbering\DocumentNumberIssuer;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

uses(RefreshDatabase::class);

it('enforces company scoped settings permissions', function (): void {
    $this->withoutVite();
    $company = m15Company('M15-A');
    $viewer = m15Actor($company, [PermissionKey::SettingsView], 'viewer');
    $denied = m15Actor($company, [], 'denied');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/settings/company')
        ->assertOk()
        ->assertSee('Firma / Sistem');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/settings/company/edit')
        ->assertForbidden();

    $this->actingAs($denied)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/settings/company')
        ->assertForbidden();
});

it('updates only company runtime settings and normalizes currency code', function (): void {
    $company = m15Company('M15-B');
    $actor = m15Actor($company, [PermissionKey::SettingsView, PermissionKey::SettingsManage], 'manager');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->put('/settings/company', [
            'base_currency_code' => 'usd',
            'timezone' => 'Europe/London',
            'code' => 'CHANGED',
            'name' => 'Changed Name',
        ])
        ->assertRedirect('/settings/company');

    $company->refresh();

    expect($company->base_currency_code)->toBe('USD')
        ->and($company->timezone)->toBe('Europe/London')
        ->and($company->code)->toBe('M15-B')
        ->and($company->name)->toBe('Company M15-B');
});

it('rejects an unknown timezone', function (): void {
    $company = m15Company('M15-C');
    $actor = m15Actor($company, [PermissionKey::SettingsView, PermissionKey::SettingsManage], 'manager');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->from('/settings/company/edit')
        ->put('/settings/company', [
            'base_currency_code' => 'TRY',
            'timezone' => 'Mars/Olympus',
        ])
        ->assertRedirect('/settings/company/edit')
        ->assertSessionHasErrors('timezone');
});

it('creates normalized company scoped document sequences', function (): void {
    $company = m15Company('M15-D');
    $actor = m15Actor($company, [PermissionKey::SettingsView, PermissionKey::SettingsManage], 'manager');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/settings/numbering', [
            'document_type' => DocumentType::Quote->value,
            'series_code' => 'MAIN',
            'prefix' => 'TKL-',
            'padding' => 5,
            'next_value' => 100,
            'is_active' => '1',
        ])
        ->assertRedirect();

    $sequence = DocumentSequence::query()->firstOrFail();

    expect($sequence->company_id)->toBe($company->getKey())
        ->and($sequence->document_type)->toBe(DocumentType::Quote)
        ->and($sequence->series_code)->toBe('main')
        ->and($sequence->exampleNumber())->toBe('TKL-00100');
});

it('rejects duplicate document type and series identity within a company', function (): void {
    $company = m15Company('M15-E');
    $actor = m15Actor($company, [PermissionKey::SettingsView, PermissionKey::SettingsManage], 'manager');
    m15Sequence($company, DocumentType::SalesOrder, 'main');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->from('/settings/numbering/create')
        ->post('/settings/numbering', [
            'document_type' => DocumentType::SalesOrder->value,
            'series_code' => 'MAIN',
            'prefix' => 'SIP-',
            'padding' => 6,
            'next_value' => 1,
            'is_active' => '1',
        ])
        ->assertRedirect('/settings/numbering/create')
        ->assertSessionHasErrors('series_code');
});

it('does not expose another company document sequence by route id', function (): void {
    $this->withoutVite();
    $companyA = m15Company('M15-F-A');
    $companyB = m15Company('M15-F-B');
    $actor = m15Actor($companyA, [PermissionKey::SettingsView], 'viewer');
    $foreignSequence = m15Sequence($companyB, DocumentType::Dispatch, 'default');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/settings/numbering/'.$foreignSequence->getKey())
        ->assertNotFound();
});

it('allows the next sequence value to move only forward', function (): void {
    $company = m15Company('M15-G');
    $actor = m15Actor($company, [PermissionKey::SettingsView, PermissionKey::SettingsManage], 'manager');
    $sequence = m15Sequence($company, DocumentType::SalesInvoice, 'default', nextValue: 50);

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->from('/settings/numbering/'.$sequence->getKey().'/edit')
        ->put('/settings/numbering/'.$sequence->getKey(), [
            'prefix' => 'FTR-',
            'padding' => 6,
            'next_value' => 49,
            'is_active' => '1',
        ])
        ->assertRedirect('/settings/numbering/'.$sequence->getKey().'/edit')
        ->assertSessionHasErrors('next_value');

    expect($sequence->fresh()?->next_value)->toBe(50);
});

it('requires the document number issuer to participate in a business transaction', function (): void {
    $company = m15Company('M15-H');
    m15Sequence($company, DocumentType::SalesInvoice, 'default');

    expect(fn () => app(DocumentNumberIssuer::class)->issue(
        (int) $company->getKey(),
        DocumentType::SalesInvoice,
    ))->toThrow(DomainException::class, 'business transaction dışında');
});

it('issues a number exactly once and rolls the increment back with the business transaction', function (): void {
    $company = m15Company('M15-I');
    $sequence = m15Sequence(
        $company,
        DocumentType::SalesInvoice,
        'default',
        prefix: 'FTR-',
        padding: 6,
        nextValue: 41,
    );
    $issuer = app(DocumentNumberIssuer::class);

    $issued = DB::transaction(fn () => $issuer->issue(
        (int) $company->getKey(),
        DocumentType::SalesInvoice,
    ));

    expect($issued->sequenceValue)->toBe(41)
        ->and($issued->number)->toBe('FTR-000041')
        ->and($sequence->fresh()?->next_value)->toBe(42);

    try {
        DB::transaction(function () use ($issuer, $company): void {
            $rolledBack = $issuer->issue((int) $company->getKey(), DocumentType::SalesInvoice);
            expect($rolledBack->sequenceValue)->toBe(42);

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('rollback');
    }

    expect($sequence->fresh()?->next_value)->toBe(42);
});

it('keeps repeated issuance monotonic and unique under the locked row path', function (): void {
    $company = m15Company('M15-J');
    $sequence = m15Sequence($company, DocumentType::Collection, 'default', prefix: 'THS-', padding: 4);
    $issuer = app(DocumentNumberIssuer::class);

    $numbers = DB::transaction(function () use ($issuer, $company): array {
        $issued = [];

        for ($index = 0; $index < 50; $index++) {
            $issued[] = $issuer->issue((int) $company->getKey(), DocumentType::Collection)->number;
        }

        return $issued;
    });

    expect(array_unique($numbers))->toHaveCount(50)
        ->and($numbers[0])->toBe('THS-0001')
        ->and($numbers[49])->toBe('THS-0050')
        ->and($sequence->fresh()?->next_value)->toBe(51);
});

it('does not issue from an inactive number series', function (): void {
    $company = m15Company('M15-K');
    m15Sequence($company, DocumentType::Payment, 'default', active: false);

    expect(fn () => DB::transaction(fn () => app(DocumentNumberIssuer::class)->issue(
        (int) $company->getKey(),
        DocumentType::Payment,
    )))->toThrow(DomainException::class, 'Aktif belge numara serisi bulunamadı.');
});

function m15Company(string $code): Company
{
    return Company::query()->create([
        'code' => $code,
        'name' => 'Company '.$code,
    ]);
}

function m15User(string $email): User
{
    return User::query()->create([
        'name' => 'M1.5 User',
        'email' => $email,
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);
}

function m15Membership(Company $company, User $user): CompanyMembership
{
    return CompanyMembership::query()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'is_active' => true,
        'joined_at' => now(),
    ]);
}

/** @param list<PermissionKey> $permissions */
function m15Actor(Company $company, array $permissions, string $suffix): User
{
    $user = m15User(strtolower((string) $company->code).'-'.$suffix.'@m15.test');
    $membership = m15Membership($company, $user);
    $role = Role::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'm15-'.$suffix,
        'name' => 'M1.5 '.$suffix,
        'is_active' => true,
    ]);

    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }

    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}

function m15Sequence(
    Company $company,
    DocumentType $documentType,
    string $seriesCode,
    string $prefix = '',
    int $padding = 6,
    int $nextValue = 1,
    bool $active = true,
): DocumentSequence {
    return DocumentSequence::query()->create([
        'company_id' => $company->getKey(),
        'document_type' => $documentType,
        'series_code' => $seriesCode,
        'prefix' => $prefix,
        'padding' => $padding,
        'next_value' => $nextValue,
        'is_active' => $active,
    ]);
}
