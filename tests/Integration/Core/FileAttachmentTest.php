<?php

use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
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
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
    Storage::fake('local');
});

it('stores a company file on private storage with server metadata and sha256', function (): void {
    $company = m1FileCompany('FILE-A');
    $actor = m1FileActor($company, [PermissionKey::FileView, PermissionKey::FileManage], 'manager');
    $content = 'Mars attachment content';

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'file-upload-001')
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/settings/files', [
            'file' => UploadedFile::fake()->createWithContent('manual.txt', $content),
            'label' => 'Teknik not',
        ])
        ->assertRedirect();

    $attachment = Attachment::query()->firstOrFail();
    $asset = FileAsset::query()->firstOrFail();

    expect($attachment->company_id)->toBe($company->getKey())
        ->and($asset->company_id)->toBe($company->getKey())
        ->and($asset->original_name)->toBe('manual.txt')
        ->and($asset->mime_type)->toBe('text/plain')
        ->and($asset->sha256)->toBe(hash('sha256', $content))
        ->and((string) $asset->storage_key)->not->toEndWith('.txt')
        ->and(config('filesystems.disks.local.serve'))->toBeFalse();

    Storage::disk('local')->assertExists((string) $asset->storage_key);
    Storage::disk('public')->assertMissing((string) $asset->storage_key);

    $audit = AuditEntry::query()->where('action', AuditAction::FileUploaded->value)->firstOrFail();
    expect($audit->correlation_id)->toBe('file-upload-001')
        ->and($audit->after_state['sha256'])->toBe(hash('sha256', $content))
        ->and(array_key_exists('original_name', $audit->after_state))->toBeFalse();
});

it('rejects dangerous script extensions before persistence', function (): void {
    $company = m1FileCompany('FILE-B');
    $actor = m1FileActor($company, [PermissionKey::FileManage], 'manager');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->from('/settings/files/create')
        ->post('/settings/files', [
            'file' => UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;'),
        ])
        ->assertRedirect('/settings/files/create')
        ->assertSessionHasErrors('file');

    expect(FileAsset::query()->count())->toBe(0)
        ->and(Attachment::query()->count())->toBe(0)
        ->and(AuditEntry::query()->count())->toBe(0);
});

it('enforces separate file view and manage permissions', function (): void {
    $company = m1FileCompany('FILE-C');
    $viewer = m1FileActor($company, [PermissionKey::FileView], 'viewer');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/settings/files')
        ->assertOk();

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/settings/files', [
            'file' => UploadedFile::fake()->createWithContent('note.txt', 'blocked'),
        ])
        ->assertForbidden();
});

it('does not expose another company attachment or download by route id', function (): void {
    $companyA = m1FileCompany('FILE-D-A');
    $companyB = m1FileCompany('FILE-D-B');
    $actorB = m1FileActor($companyB, [PermissionKey::FileView, PermissionKey::FileManage], 'manager-b');

    $this->actingAs($actorB)
        ->withSession(['active_company_id' => $companyB->getKey()])
        ->post('/settings/files', [
            'file' => UploadedFile::fake()->createWithContent('foreign.txt', 'foreign data'),
        ])
        ->assertRedirect();

    $foreign = Attachment::query()->where('company_id', $companyB->getKey())->firstOrFail();
    $actorA = m1FileActor($companyA, [PermissionKey::FileView], 'viewer-a');

    $this->actingAs($actorA)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/settings/files/'.$foreign->getKey())
        ->assertNotFound();

    $this->actingAs($actorA)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/settings/files/'.$foreign->getKey().'/download')
        ->assertNotFound();
});

it('detaches without deleting the original file and archives the last unlinked asset', function (): void {
    $company = m1FileCompany('FILE-E');
    $actor = m1FileActor($company, [PermissionKey::FileView, PermissionKey::FileManage], 'manager');

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'file-detach-upload')
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/settings/files', [
            'file' => UploadedFile::fake()->createWithContent('archive.txt', 'keep original'),
        ])
        ->assertRedirect();

    $attachment = Attachment::query()->firstOrFail();
    $asset = FileAsset::query()->firstOrFail();
    $storageKey = (string) $asset->storage_key;

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'file-detach-001')
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/settings/files/'.$attachment->getKey().'/detach')
        ->assertRedirect();

    $attachment->refresh();
    $asset->refresh();

    expect($attachment->detached_at)->not->toBeNull()
        ->and($asset->archived_at)->not->toBeNull();
    Storage::disk('local')->assertExists($storageKey);

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/settings/files/'.$attachment->getKey().'/download')
        ->assertNotFound();

    $audit = AuditEntry::query()->where('action', AuditAction::AttachmentDetached->value)->firstOrFail();
    expect($audit->correlation_id)->toBe('file-detach-001')
        ->and($audit->before_state['detached'])->toBeFalse()
        ->and($audit->after_state['detached'])->toBeTrue();
});

function m1FileCompany(string $code): Company
{
    return Company::query()->create([
        'code' => $code,
        'name' => 'Company '.$code,
    ]);
}

/** @param list<PermissionKey> $permissions */
function m1FileActor(Company $company, array $permissions, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'File '.$suffix,
        'email' => strtolower((string) $company->code).'-'.$suffix.'@files.test',
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
        'code' => 'files-'.$suffix,
        'name' => 'Files '.$suffix,
        'is_active' => true,
    ]);

    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }

    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}
