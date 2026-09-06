<?php

use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Enums\AttachmentTargetType;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Attachment;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\FileAsset;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Preview\CadPreviewService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;

uses(DatabaseMigrations::class);

it('opens a real DXF fixture in the read-only browser CAD workspace without browser errors', function (): void {
    $company = Company::query()->create([
        'code' => 'BROWSER-M32',
        'name' => 'Browser M32 Company',
    ]);
    $user = User::query()->create([
        'name' => 'Browser CAD User',
        'email' => 'browser-m32@example.test',
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
        'code' => 'M32-CAD',
        'name' => 'M32 CAD Viewer',
        'is_active' => true,
    ]);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::FileView);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::FileManage);
    app(AssignRoleToMembership::class)->handle($membership, $role);
    Branch::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'MAIN',
        'name' => 'Merkez',
        'is_active' => true,
    ]);

    $fixture = file_get_contents(base_path('tests/Fixtures/m32/sample.dxf'));
    if (! is_string($fixture)) {
        throw new RuntimeException('M32 DXF fixture could not be read.');
    }
    Storage::disk('local')->put('cad/browser-m32.dxf', $fixture);

    $asset = FileAsset::query()->create([
        'company_id' => $company->getKey(),
        'uploaded_by_user_id' => $user->getKey(),
        'storage_disk' => 'local',
        'storage_key' => 'cad/browser-m32.dxf',
        'original_name' => 'browser-m32.dxf',
        'mime_type' => 'application/dxf',
        'client_extension' => 'dxf',
        'size_bytes' => strlen($fixture),
        'sha256' => hash('sha256', $fixture),
    ]);
    $attachment = Attachment::query()->create([
        'company_id' => $company->getKey(),
        'file_asset_id' => $asset->getKey(),
        'attachable_type' => AttachmentTargetType::Company,
        'attachable_id' => $company->getKey(),
        'label' => 'M32 DXF',
        'attached_by_user_id' => $user->getKey(),
        'attached_at' => now(),
    ]);

    app(CadPreviewService::class)->requestPreview(
        (int) $company->getKey(),
        (int) $attachment->getKey(),
        'local',
    );

    visit('/login')
        ->fill('email', 'browser-m32@example.test')
        ->fill('password', 'correct-password')
        ->click('Giriş Yap')
        ->assertPathIs('/workspace')
        ->click('Ayarlar')
        ->assertPathIs('/settings')
        ->click('Firma Dosyaları')
        ->assertPathIs('/settings/files')
        ->click('browser-m32.dxf')
        ->assertPathIs('/settings/files/'.$attachment->getKey())
        ->click('CAD/3D Önizle')
        ->assertPathIs('/settings/files/'.$attachment->getKey().'/cad')
        ->assertSee('CAD / 3D Önizleme')
        ->assertSee('browser-m32.dxf')
        ->assertSee('Mars Local Viewer')
        ->assertSee('Read-only teknik önizleme')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});
