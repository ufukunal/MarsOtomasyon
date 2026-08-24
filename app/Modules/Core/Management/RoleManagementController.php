<?php

namespace App\Modules\Core\Management;

use App\Modules\Core\Authorization\PrivilegeGrantGuard;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class RoleManagementController
{
    public function __construct(
        private readonly ActiveCompanyContext $companyContext,
        private readonly PrivilegeGrantGuard $privilegeGuard,
    ) {}

    public function index(): View
    {
        $roles = Role::query()
            ->where('company_id', $this->companyId())
            ->withCount(['permissions', 'memberships'])
            ->orderBy('name')
            ->get();

        return view('settings.roles.index', compact('roles'));
    }

    public function show(int $role): View
    {
        return view('settings.roles.show', [
            'role' => $this->role($role),
        ]);
    }

    public function create(): View
    {
        return view('settings.roles.form', [
            'role' => null,
            'grantablePermissions' => $this->privilegeGuard->grantablePermissions(),
            'selectedPermissionKeys' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9._-]+$/i'],
            'name' => ['required', 'string', 'max:160'],
            'is_active' => ['required', 'boolean'],
            'permission_keys' => ['array'],
            'permission_keys.*' => ['string', 'max:120'],
        ]);

        $permissionKeys = $this->normalizePermissionKeys($validated['permission_keys'] ?? []);
        $this->privilegeGuard->assertCanGrantPermissionKeys($permissionKeys);
        $this->assertCodeAvailable((string) $validated['code']);

        $role = DB::transaction(function () use ($validated, $permissionKeys): Role {
            $role = Role::query()->create([
                'company_id' => $this->companyId(),
                'code' => mb_strtolower(trim((string) $validated['code'])),
                'name' => trim((string) $validated['name']),
                'is_active' => (bool) $validated['is_active'],
            ]);

            $this->syncPermissions($role, $permissionKeys);

            return $role;
        });

        return redirect()->route('settings.roles.show', $role->getKey())
            ->with('status', 'Rol oluşturuldu.');
    }

    public function edit(int $role): View
    {
        $role = $this->role($role);

        return view('settings.roles.form', [
            'role' => $role,
            'grantablePermissions' => $this->privilegeGuard->grantablePermissions(),
            'selectedPermissionKeys' => $role->permissions->pluck('key')->map(static fn (mixed $key): string => (string) $key)->all(),
        ]);
    }

    public function update(Request $request, int $role): RedirectResponse
    {
        $role = $this->role($role);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9._-]+$/i'],
            'name' => ['required', 'string', 'max:160'],
            'is_active' => ['required', 'boolean'],
            'permission_keys' => ['array'],
            'permission_keys.*' => ['string', 'max:120'],
        ]);

        $permissionKeys = $this->normalizePermissionKeys($validated['permission_keys'] ?? []);
        $this->privilegeGuard->assertCanGrantPermissionKeys($permissionKeys);
        $this->assertCodeAvailable((string) $validated['code'], (int) $role->getKey());

        DB::transaction(function () use ($role, $validated, $permissionKeys): void {
            $role->code = mb_strtolower(trim((string) $validated['code']));
            $role->name = trim((string) $validated['name']);
            $role->is_active = (bool) $validated['is_active'];
            $role->save();
            $this->syncPermissions($role, $permissionKeys);
        });

        return redirect()->route('settings.roles.show', $role->getKey())
            ->with('status', 'Rol güncellendi.');
    }

    private function role(int $roleId): Role
    {
        return Role::query()
            ->where('company_id', $this->companyId())
            ->with(['permissions', 'memberships.user'])
            ->findOrFail($roleId);
    }

    /**
     * @param  array<array-key, mixed>  $rawKeys
     * @return list<string>
     */
    private function normalizePermissionKeys(array $rawKeys): array
    {
        $keys = array_values(array_unique(array_map('strval', $rawKeys)));

        foreach ($keys as $key) {
            if (PermissionKey::tryFrom($key) === null) {
                throw ValidationException::withMessages(['permission_keys' => 'Geçersiz yetki seçimi.']);
            }
        }

        return $keys;
    }

    /** @param list<string> $permissionKeys */
    private function syncPermissions(Role $role, array $permissionKeys): void
    {
        $permissionIds = Permission::query()
            ->whereIn('key', $permissionKeys)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if (count($permissionIds) !== count($permissionKeys)) {
            throw ValidationException::withMessages(['permission_keys' => 'Yetki kataloğu ile seçim uyuşmuyor.']);
        }

        $role->permissions()->sync($permissionIds);
    }

    private function assertCodeAvailable(string $code, ?int $ignoreRoleId = null): void
    {
        $query = Role::query()
            ->where('company_id', $this->companyId())
            ->whereRaw('lower(code) = ?', [mb_strtolower(trim($code))]);

        if ($ignoreRoleId !== null) {
            $query->whereKeyNot($ignoreRoleId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages(['code' => 'Bu rol kodu şirkette zaten kullanılıyor.']);
        }
    }

    private function companyId(): int
    {
        return (int) $this->companyContext->requireCompany()->getKey();
    }
}
