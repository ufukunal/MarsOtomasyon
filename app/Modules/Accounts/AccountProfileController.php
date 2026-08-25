<?php

namespace App\Modules\Accounts;

use App\Modules\Accounts\Actions\UpdateAccountProfile;
use App\Modules\Accounts\Actions\UpdateAccountProfileData;
use App\Modules\Accounts\Enums\AccountAddressType;
use App\Modules\Accounts\Enums\AccountContactKind;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Company\ActiveCompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use LogicException;

final readonly class AccountProfileController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private UpdateAccountProfile $updateProfile,
    ) {}

    public function edit(int $account): View
    {
        $account = $this->account($account);
        $account->load(['contacts', 'authorizedContacts', 'addresses', 'shippingPreferences']);

        return view('accounts.profile-form', [
            'account' => $account,
            'contactKinds' => AccountContactKind::cases(),
            'addressTypes' => AccountAddressType::cases(),
        ]);
    }

    public function update(Request $request, int $account): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        /** @var list<array{id?: int, kind: string, label?: string|null, value: string, is_primary?: bool|string|int}> $contacts */
        $contacts = array_values($validated['contacts'] ?? []);
        /** @var list<array{id?: int, name: string, title?: string|null, phone?: string|null, email?: string|null, is_primary?: bool|string|int, note?: string|null}> $authorizedContacts */
        $authorizedContacts = array_values($validated['authorized_contacts'] ?? []);
        /** @var list<array{id?: int, type: string, label: string, recipient_name?: string|null, line1: string, line2?: string|null, district?: string|null, city: string, postal_code?: string|null, country_code: string, is_default?: bool|string|int}> $addresses */
        $addresses = array_values($validated['addresses'] ?? []);
        /** @var list<array{id?: int, company_name: string, city: string, branch?: string|null, contact_name?: string|null, phone?: string|null, preference?: string|null, address?: string|null, note?: string|null, is_default?: bool|string|int}> $shippingPreferences */
        $shippingPreferences = array_values($validated['shipping_preferences'] ?? []);

        $updated = $this->updateProfile->handle($account, new UpdateAccountProfileData([
            'contacts' => $contacts,
            'authorized_contacts' => $authorizedContacts,
            'addresses' => $addresses,
            'shipping_preferences' => $shippingPreferences,
        ]));

        return redirect()->route('customers.show', $updated->getKey())
            ->with('status', 'Cari iletişim ve sevk bilgileri güncellendi.');
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'contacts' => ['sometimes', 'array', 'max:20'],
            'contacts.*.id' => ['nullable', 'integer', 'min:1'],
            'contacts.*.kind' => ['required', Rule::enum(AccountContactKind::class)],
            'contacts.*.label' => ['nullable', 'string', 'max:80'],
            'contacts.*.value' => ['required', 'string', 'max:200'],
            'contacts.*.is_primary' => ['sometimes', 'boolean'],

            'authorized_contacts' => ['sometimes', 'array', 'max:20'],
            'authorized_contacts.*.id' => ['nullable', 'integer', 'min:1'],
            'authorized_contacts.*.name' => ['required', 'string', 'max:160'],
            'authorized_contacts.*.title' => ['nullable', 'string', 'max:120'],
            'authorized_contacts.*.phone' => ['nullable', 'string', 'max:40'],
            'authorized_contacts.*.email' => ['nullable', 'string', 'max:200'],
            'authorized_contacts.*.is_primary' => ['sometimes', 'boolean'],
            'authorized_contacts.*.note' => ['nullable', 'string', 'max:500'],

            'addresses' => ['sometimes', 'array', 'max:30'],
            'addresses.*.id' => ['nullable', 'integer', 'min:1'],
            'addresses.*.type' => ['required', Rule::enum(AccountAddressType::class)],
            'addresses.*.label' => ['required', 'string', 'max:80'],
            'addresses.*.recipient_name' => ['nullable', 'string', 'max:200'],
            'addresses.*.line1' => ['required', 'string', 'max:240'],
            'addresses.*.line2' => ['nullable', 'string', 'max:240'],
            'addresses.*.district' => ['nullable', 'string', 'max:120'],
            'addresses.*.city' => ['required', 'string', 'max:120'],
            'addresses.*.postal_code' => ['nullable', 'string', 'max:20'],
            'addresses.*.country_code' => ['required', 'string', 'size:2'],
            'addresses.*.is_default' => ['sometimes', 'boolean'],

            'shipping_preferences' => ['sometimes', 'array', 'max:20'],
            'shipping_preferences.*.id' => ['nullable', 'integer', 'min:1'],
            'shipping_preferences.*.company_name' => ['required', 'string', 'max:200'],
            'shipping_preferences.*.city' => ['required', 'string', 'max:120'],
            'shipping_preferences.*.branch' => ['nullable', 'string', 'max:120'],
            'shipping_preferences.*.contact_name' => ['nullable', 'string', 'max:160'],
            'shipping_preferences.*.phone' => ['nullable', 'string', 'max:40'],
            'shipping_preferences.*.preference' => ['nullable', 'string', 'max:120'],
            'shipping_preferences.*.address' => ['nullable', 'string', 'max:500'],
            'shipping_preferences.*.note' => ['nullable', 'string', 'max:1000'],
            'shipping_preferences.*.is_default' => ['sometimes', 'boolean'],
        ];
    }

    private function account(int $id): Account
    {
        return Account::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);
    }

    private function companyId(): int
    {
        $companyId = $this->companyContext->requireCompany()->getKey();
        if (! is_int($companyId)) {
            throw new LogicException('Account profile management requires a persisted active company.');
        }

        return $companyId;
    }
}
