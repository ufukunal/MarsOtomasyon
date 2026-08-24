<?php

namespace App\Modules\Core\Management;

use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Authorization\PrivilegeGrantGuard;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class UserManagementController
{
    public function __construct(
        private readonly ActiveCompanyContext $companyContext,
        private readonly PrivilegeGrantGuard $privilegeGuard,
        private readonly AuditRecorder $audit,
    ) {}

    public function index(): View
    {
        $memberships = CompanyMembership::query()
            ->where('company_id', $this->companyId())
            ->with(['user', 'roles'])
            ->orderBy('id')
            ->get();

        return view('settings.users.index', compact('memberships'));
    }

    public function show(int $membership): View
    {
        $membership = $this->membership($membership);

        return view('settings.users.show', [
            'membership' => $membership,
            'identityEditable' => $this->identityEditable($membership),
        ]);
    }

    public function create(): View
    {
        return view('settings.users.form', [
            'membership' => null,
            'roles' => $this->grantableRoles(),
            'selectedRoleIds' => [],
            'identityEditable' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'min:12', 'max:4096'],
            'role_ids' => ['array'],
            'role_ids.*' => ['integer'],
        ]);

        $email = mb_strtolower(trim((string) $validated['email']));
        $this->assertEmailAvailable($email);
        $roles = $this->rolesFromRequest($validated['role_ids'] ?? []);
        $this->privilegeGuard->assertCanGrantRoles($roles);

        $membership = DB::transaction(function () use ($validated, $email, $roles): CompanyMembership {
            $user = User::query()->create([
                'name' => trim((string) $validated['name']),
                'email' => $email,
                'password' => (string) $validated['password'],
                'status' => UserStatus::Active,
            ]);

            $membership = CompanyMembership::query()->create([
                'company_id' => $this->companyId(),
                'user_id' => $user->getKey(),
                'is_active' => true,
                'joined_at' => now(),
            ]);

            $this->syncRoles($membership, $roles);
            $this->audit->record(
                AuditAction::UserCreated,
                AuditTargetType::CompanyMembership,
                $membership->getKey(),
                after: $this->snapshot($membership, $user, $roles->modelKeys()),
            );

            return $membership;
        });

        return redirect()->route('settings.users.show', $membership->getKey())
            ->with('status', 'Kullanıcı oluşturuldu.');
    }

    public function edit(int $membership): View
    {
        $membership = $this->membership($membership);

        return view('settings.users.form', [
            'membership' => $membership,
            'roles' => $this->grantableRoles(),
            'selectedRoleIds' => $membership->roles->modelKeys(),
            'identityEditable' => $this->identityEditable($membership),
        ]);
    }

    public function update(Request $request, int $membership): RedirectResponse
    {
        $membership = $this->membership($membership);
        $identityEditable = $this->identityEditable($membership);

        $rules = [
            'is_active' => ['required', 'boolean'],
            'role_ids' => ['array'],
            'role_ids.*' => ['integer'],
        ];

        if ($identityEditable) {
            $rules['name'] = ['required', 'string', 'max:160'];
            $rules['email'] = ['required', 'string', 'email:rfc', 'max:255'];
            $rules['password'] = ['nullable', 'string', 'min:12', 'max:4096'];
        }

        $validated = $request->validate($rules);
        $roles = $this->rolesFromRequest($validated['role_ids'] ?? []);
        $this->privilegeGuard->assertCanGrantRoles($roles);
        $before = $this->snapshot($membership, $membership->user, $membership->roles->modelKeys());

        DB::transaction(function () use ($membership, $identityEditable, $validated, $roles, $before): void {
            $user = $membership->user;
            abort_if($user === null, 409, 'Üyelik geçerli bir kullanıcıya bağlı değil.');

            if ($identityEditable) {
                $email = mb_strtolower(trim((string) $validated['email']));
                $this->assertEmailAvailable($email, (int) $user->getKey());

                $user->name = trim((string) $validated['name']);
                $user->email = $email;

                if (filled($validated['password'] ?? null)) {
                    $user->password = (string) $validated['password'];
                }

                $user->save();
            }

            $membership->is_active = (bool) $validated['is_active'];
            $membership->save();
            $this->syncRoles($membership, $roles);

            $this->audit->record(
                AuditAction::UserUpdated,
                AuditTargetType::CompanyMembership,
                $membership->getKey(),
                before: $before,
                after: $this->snapshot($membership, $user, $roles->modelKeys()),
            );
        });

        return redirect()->route('settings.users.show', $membership->getKey())
            ->with('status', 'Kullanıcı üyeliği güncellendi.');
    }

    private function membership(int $membershipId): CompanyMembership
    {
        return CompanyMembership::query()
            ->where('company_id', $this->companyId())
            ->with(['user', 'roles.permissions'])
            ->findOrFail($membershipId);
    }

    /** @return Collection<int, Role> */
    private function grantableRoles(): Collection
    {
        $roles = Role::query()
            ->where('company_id', $this->companyId())
            ->where('is_active', true)
            ->with('permissions')
            ->orderBy('name')
            ->get();

        return $roles->filter(function (Role $role): bool {
            foreach ($role->permissions as $permission) {
                $key = PermissionKey::tryFrom((string) $permission->key);
                if ($key === null || ! Gate::allows($key->value)) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /**
     * @param array<array-key, mixed> $roleIds
     * @return Collection<int, Role>
     */
    private function rolesFromRequest(array $roleIds): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $roleIds)));

        if ($ids === []) {
            return new Collection;
        }

        $roles = Role::query()
            ->where('company_id', $this->companyId())
            ->whereIn('id', $ids)
            ->with('permissions')
            ->get();

        if ($roles->count() !== count($ids)) {
            throw ValidationException::withMessages(['role_ids' => 'Seçilen roller bu şirkete ait değil.']);
        }

        return $roles;
    }

    /** @param Collection<int, Role> $roles */
    private function syncRoles(CompanyMembership $membership, Collection $roles): void
    {
        $pivot = [];
        foreach ($roles as $role) {
            $pivot[(int) $role->getKey()] = [
                'company_id' => $this->companyId(),
                'assigned_at' => now(),
            ];
        }

        $membership->roles()->sync($pivot);
    }

    private function identityEditable(CompanyMembership $membership): bool
    {
        return ! $membership->user?->memberships()
            ->whereKeyNot($membership->getKey())
            ->exists();
    }

    /**
     * @param array<array-key, int|string> $roleIds
     * @return array{user_id:int,membership_id:int,name:string|null,email:string|null,is_active:bool,role_ids:list<int>}
     */
    private function snapshot(CompanyMembership $membership, ?User $user, array $roleIds): array
    {
        $normalizedRoleIds = array_map('intval', $roleIds);
        sort($normalizedRoleIds);

        return [
            'user_id' => (int) $membership->user_id,
            'membership_id' => (int) $membership->getKey(),
            'name' => $user?->name === null ? null : (string) $user->name,
            'email' => $user?->email === null ? null : (string) $user->email,
            'is_active' => (bool) $membership->is_active,
            'role_ids' => $normalizedRoleIds,
        ];
    }

    private function assertEmailAvailable(string $email, ?int $ignoreUserId = null): void
    {
        $query = User::query()->whereRaw('lower(email) = ?', [$email]);
        if ($ignoreUserId !== null) {
            $query->whereKeyNot($ignoreUserId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages(['email' => 'Bu e-posta adresi zaten kullanılıyor.']);
        }
    }

    private function companyId(): int
    {
        return (int) $this->companyContext->requireCompany()->getKey();
    }
}
