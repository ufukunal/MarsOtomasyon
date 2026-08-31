<?php

namespace App\Modules\B2B\Portal;

use App\Modules\Accounts\Enums\AccountAddressType;
use App\Modules\Accounts\Models\AccountAddress;
use App\Modules\B2B\Enums\B2BPermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final readonly class B2BAddressController
{
    public function __construct(private B2BPortalAccess $access) {}

    public function store(Request $request): RedirectResponse
    {
        $this->access->authorize(B2BPermission::ManageAddresses);
        $validated = $request->validate($this->rules());
        $user = $this->access->user();
        AccountAddress::query()->create($this->payload($validated) + [
            'company_id' => $user->company_id,
            'account_id' => $user->account_id,
        ]);

        return back()->with('status', 'Adres eklendi.');
    }

    public function update(Request $request, string $address): RedirectResponse
    {
        $this->access->authorize(B2BPermission::ManageAddresses);
        $validated = $request->validate($this->rules());
        $this->address($address)->fill($this->payload($validated))->save();

        return back()->with('status', 'Adres güncellendi.');
    }

    public function destroy(string $address): RedirectResponse
    {
        $this->access->authorize(B2BPermission::ManageAddresses);
        $this->address($address)->delete();

        return back()->with('status', 'Adres silindi.');
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(AccountAddressType::class)],
            'label' => ['nullable', 'string', 'max:80'],
            'recipient_name' => ['nullable', 'string', 'max:160'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'country_code' => ['required', 'string', 'size:2'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(array $validated): array
    {
        return [
            'type' => (string) $validated['type'],
            'label' => $validated['label'] ?? null,
            'recipient_name' => $validated['recipient_name'] ?? null,
            'line1' => (string) $validated['line1'],
            'line2' => $validated['line2'] ?? null,
            'district' => $validated['district'] ?? null,
            'city' => (string) $validated['city'],
            'postal_code' => $validated['postal_code'] ?? null,
            'country_code' => mb_strtoupper((string) $validated['country_code']),
            'is_default' => false,
        ];
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
