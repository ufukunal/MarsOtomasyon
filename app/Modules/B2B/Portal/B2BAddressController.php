<?php

namespace App\Modules\B2B\Portal;

use App\Modules\Accounts\Enums\AccountAddressType;
use App\Modules\Accounts\Models\AccountAddress;
use App\Modules\B2B\Enums\B2BPermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final readonly class B2BAddressController
{
    public function __construct(private B2BPortalAccess $access) {}

    public function store(Request $request): RedirectResponse
    {
        $this->access->authorize(B2BPermission::ManageAddresses);
        $validated = $request->validate($this->rules());
        $user = $this->access->user();
        $type = AccountAddressType::from((string) $validated['type']);
        $isDefault = (bool) $validated['is_default'] || ! $this->hasDefault($type);

        DB::transaction(function () use ($validated, $user, $type, $isDefault): void {
            if ($isDefault) {
                $this->clearDefaults($type);
            }
            AccountAddress::query()->create($this->payload($validated, $isDefault) + [
                'company_id' => $user->company_id,
                'account_id' => $user->account_id,
            ]);
        });

        return back()->with('status', 'Adres eklendi.');
    }

    public function update(Request $request, string $address): RedirectResponse
    {
        $this->access->authorize(B2BPermission::ManageAddresses);
        $validated = $request->validate($this->rules());
        $addressModel = $this->address($address);
        $oldType = $addressModel->typeEnum();
        $wasDefault = (bool) $addressModel->is_default;
        $newType = AccountAddressType::from((string) $validated['type']);
        $isDefault = (bool) $validated['is_default'];

        DB::transaction(function () use ($addressModel, $validated, $oldType, $wasDefault, $newType, $isDefault): void {
            if ($isDefault) {
                $this->clearDefaults($newType, (int) $addressModel->getKey());
            }
            $addressModel->fill($this->payload($validated, $isDefault))->save();

            if ($wasDefault && ($oldType !== $newType || ! $isDefault)) {
                $this->promoteDefault($oldType, (int) $addressModel->getKey());
            }
        });

        return back()->with('status', 'Adres güncellendi.');
    }

    public function destroy(string $address): RedirectResponse
    {
        $this->access->authorize(B2BPermission::ManageAddresses);
        $addressModel = $this->address($address);
        $type = $addressModel->typeEnum();
        $wasDefault = (bool) $addressModel->is_default;
        $id = (int) $addressModel->getKey();

        DB::transaction(function () use ($addressModel, $type, $wasDefault, $id): void {
            $addressModel->delete();
            if ($wasDefault) {
                $this->promoteDefault($type, $id);
            }
        });

        return back()->with('status', 'Adres silindi.');
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(AccountAddressType::class)],
            'label' => ['required', 'string', 'max:80'],
            'recipient_name' => ['nullable', 'string', 'max:160'],
            'line1' => ['required', 'string', 'max:240'],
            'line2' => ['nullable', 'string', 'max:240'],
            'district' => ['nullable', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country_code' => ['required', 'string', 'size:2'],
            'is_default' => ['required', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(array $validated, bool $isDefault): array
    {
        return [
            'type' => (string) $validated['type'],
            'label' => trim((string) $validated['label']),
            'recipient_name' => $validated['recipient_name'] ?? null,
            'line1' => (string) $validated['line1'],
            'line2' => $validated['line2'] ?? null,
            'district' => $validated['district'] ?? null,
            'city' => (string) $validated['city'],
            'postal_code' => $validated['postal_code'] ?? null,
            'country_code' => mb_strtoupper((string) $validated['country_code']),
            'is_default' => $isDefault,
        ];
    }

    private function hasDefault(AccountAddressType $type): bool
    {
        $user = $this->access->user();

        return AccountAddress::query()
            ->where('company_id', $user->company_id)
            ->where('account_id', $user->account_id)
            ->where('type', $type->value)
            ->where('is_default', true)
            ->exists();
    }

    private function clearDefaults(AccountAddressType $type, ?int $exceptId = null): void
    {
        $user = $this->access->user();
        $query = AccountAddress::query()
            ->where('company_id', $user->company_id)
            ->where('account_id', $user->account_id)
            ->where('type', $type->value)
            ->where('is_default', true);
        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }
        $query->update(['is_default' => false]);
    }

    private function promoteDefault(AccountAddressType $type, int $exceptId): void
    {
        $user = $this->access->user();
        if ($this->hasDefault($type)) {
            return;
        }
        $candidate = AccountAddress::query()
            ->where('company_id', $user->company_id)
            ->where('account_id', $user->account_id)
            ->where('type', $type->value)
            ->whereKeyNot($exceptId)
            ->orderBy('id')
            ->first();
        if ($candidate !== null) {
            $candidate->forceFill(['is_default' => true])->save();
        }
    }

    private function address(string $publicId): AccountAddress
    {
        $user = $this->access->user();

        return AccountAddress::query()
            ->where('company_id', $user->company_id)
            ->where('account_id', $user->account_id)
            ->where('public_id', mb_strtoupper(trim($publicId)))
            ->firstOrFail();
    }
}
