<?php

namespace App\Modules\Accounts;

use App\Modules\Accounts\Models\Account;
use App\Modules\B2B\Enums\B2BPermission;
use App\Modules\B2B\Enums\B2BRole;
use App\Modules\B2B\Enums\B2BUserStatus;
use App\Modules\B2B\Models\B2BUser;
use App\Modules\Core\Company\ActiveCompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use LogicException;

final readonly class AccountB2BUserController
{
    public function __construct(private ActiveCompanyContext $companyContext) {}

    public function store(Request $request, int $account): RedirectResponse
    {
        $accountModel = $this->account($account);
        $validated = $request->validate($this->rules());

        B2BUser::query()->create([
            'company_id' => $this->companyId(),
            'account_id' => $accountModel->getKey(),
            'name' => trim((string) $validated['name']),
            'email' => mb_strtolower(trim((string) $validated['email'])),
            'password' => (string) $validated['password'],
            'status' => (string) $validated['status'],
            'role' => (string) $validated['role'],
            'permissions' => array_values($validated['permissions'] ?? []),
            'password_changed_at' => now(),
        ]);

        return back()->with('status', 'B2B kullanıcısı oluşturuldu.');
    }

    public function update(Request $request, int $account, string $user): RedirectResponse
    {
        $this->account($account);
        $b2bUser = $this->user($account, $user);
        $validated = $request->validate($this->rules($b2bUser));
        $beforeSecurity = [$b2bUser->statusEnum()->value, $b2bUser->roleEnum()->value, json_encode($b2bUser->permissions)];
        $afterSecurity = [(string) $validated['status'], (string) $validated['role'], json_encode(array_values($validated['permissions'] ?? []))];
        $passwordChanged = isset($validated['password']) && trim((string) $validated['password']) !== '';

        DB::transaction(function () use ($b2bUser, $validated, $beforeSecurity, $afterSecurity, $passwordChanged): void {
            $values = [
                'name' => trim((string) $validated['name']),
                'email' => mb_strtolower(trim((string) $validated['email'])),
                'status' => (string) $validated['status'],
                'role' => (string) $validated['role'],
                'permissions' => array_values($validated['permissions'] ?? []),
            ];
            if ($passwordChanged) {
                $values['password'] = (string) $validated['password'];
                $values['password_changed_at'] = now();
            }
            if ($passwordChanged || $beforeSecurity !== $afterSecurity) {
                $values['auth_version'] = ((int) $b2bUser->auth_version) + 1;
            }
            $b2bUser->fill($values)->save();
            if ($passwordChanged) {
                DB::table('b2b_password_reset_tokens')
                    ->where('company_id', $b2bUser->company_id)
                    ->where('b2b_user_id', $b2bUser->getKey())
                    ->delete();
            }
        });

        return back()->with('status', 'B2B kullanıcısı güncellendi; güvenlik değişikliği varsa aktif oturumları yenilendi.');
    }

    /** @return array<string, mixed> */
    private function rules(?B2BUser $user = null): array
    {
        $emailRule = Rule::unique('b2b_users', 'email')->where('company_id', $this->companyId());
        if ($user instanceof B2BUser) {
            $emailRule->ignore($user->getKey());
        }

        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', $emailRule],
            'status' => ['required', Rule::enum(B2BUserStatus::class)],
            'role' => ['required', Rule::enum(B2BRole::class)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::enum(B2BPermission::class)],
            'password' => [$user instanceof B2BUser ? 'nullable' : 'required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
        ];
    }

    private function account(int $id): Account
    {
        return Account::query()->where('company_id', $this->companyId())->findOrFail($id);
    }

    private function user(int $accountId, string $publicId): B2BUser
    {
        return B2BUser::query()
            ->where('company_id', $this->companyId())
            ->where('account_id', $accountId)
            ->where('public_id', mb_strtoupper(trim($publicId)))
            ->firstOrFail();
    }

    private function companyId(): int
    {
        $id = $this->companyContext->requireCompany()->getKey();
        if (! is_int($id)) {
            throw new LogicException('B2B user management requires a persisted active company.');
        }

        return $id;
    }
}
