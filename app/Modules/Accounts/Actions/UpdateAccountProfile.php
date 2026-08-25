<?php

namespace App\Modules\Accounts\Actions;

use App\Modules\Accounts\Enums\AccountAddressType;
use App\Modules\Accounts\Enums\AccountContactKind;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountAddress;
use App\Modules\Accounts\Models\AccountAuthorizedContact;
use App\Modules\Accounts\Models\AccountContact;
use App\Modules\Accounts\Models\AccountShippingPreference;
use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class UpdateAccountProfile
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private AuditRecorder $audit,
    ) {}

    public function handle(int $accountId, UpdateAccountProfileData $data): Account
    {
        $companyId = $this->companyId();
        $this->assertPrimaryRules($data);

        return DB::transaction(function () use ($companyId, $accountId, $data): Account {
            $account = Account::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($accountId);

            $account->load(['contacts', 'authorizedContacts', 'addresses', 'shippingPreferences']);
            $before = $this->snapshot($account);

            AccountContact::query()
                ->where('company_id', $companyId)
                ->where('account_id', $accountId)
                ->where('is_primary', true)
                ->update(['is_primary' => false, 'updated_at' => now()]);
            AccountAuthorizedContact::query()
                ->where('company_id', $companyId)
                ->where('account_id', $accountId)
                ->where('is_primary', true)
                ->update(['is_primary' => false, 'updated_at' => now()]);
            AccountAddress::query()
                ->where('company_id', $companyId)
                ->where('account_id', $accountId)
                ->where('is_default', true)
                ->update(['is_default' => false, 'updated_at' => now()]);
            AccountShippingPreference::query()
                ->where('company_id', $companyId)
                ->where('account_id', $accountId)
                ->where('is_default', true)
                ->update(['is_default' => false, 'updated_at' => now()]);

            $this->syncContacts($companyId, $accountId, $data->contacts);
            $this->syncAuthorizedContacts($companyId, $accountId, $data->authorizedContacts);
            $this->syncAddresses($companyId, $accountId, $data->addresses);
            $this->syncShippingPreferences($companyId, $accountId, $data->shippingPreferences);

            $account->load(['contacts', 'authorizedContacts', 'addresses', 'shippingPreferences']);

            $this->audit->record(
                AuditAction::AccountProfileUpdated,
                AuditTargetType::Account,
                $account->getKey(),
                before: $before,
                after: $this->snapshot($account),
            );

            return $account;
        });
    }

    /** @param list<array{id?: int, kind: string, label?: string|null, value: string, is_primary?: bool|string|int}> $rows */
    private function syncContacts(int $companyId, int $accountId, array $rows): void
    {
        /** @var array<int, AccountContact> $existing */
        $existing = [];
        foreach (AccountContact::query()->where('company_id', $companyId)->where('account_id', $accountId)->get() as $contact) {
            $existing[(int) $contact->getKey()] = $contact;
        }

        /** @var array<int, true> $kept */
        $kept = [];

        foreach ($rows as $index => $row) {
            $kind = AccountContactKind::tryFrom($row['kind'])
                ?? throw ValidationException::withMessages(["contacts.$index.kind" => 'Geçersiz iletişim türü.']);
            $value = match ($kind) {
                AccountContactKind::Phone => $this->normalizePhone($row['value'], "contacts.$index.value"),
                AccountContactKind::Email => $this->normalizeEmail($row['value'], "contacts.$index.value"),
            };
            $id = $row['id'] ?? null;
            $contact = $id === null ? new AccountContact : ($existing[$id] ?? null);
            if (! $contact instanceof AccountContact) {
                throw ValidationException::withMessages(["contacts.$index.id" => 'İletişim kaydı bu cariye ait değil.']);
            }

            $contact->fill([
                'company_id' => $companyId,
                'account_id' => $accountId,
                'kind' => $kind,
                'label' => $this->optionalText($row['label'] ?? null, 80, "contacts.$index.label"),
                'value' => $value,
                'normalized_value' => $value,
                'is_primary' => (bool) ($row['is_primary'] ?? false),
            ]);
            $contact->save();
            $kept[(int) $contact->getKey()] = true;
        }

        foreach ($existing as $id => $contact) {
            if (! isset($kept[$id])) {
                $contact->delete();
            }
        }
    }

    /** @param list<array{id?: int, name: string, title?: string|null, phone?: string|null, email?: string|null, is_primary?: bool|string|int, note?: string|null}> $rows */
    private function syncAuthorizedContacts(int $companyId, int $accountId, array $rows): void
    {
        /** @var array<int, AccountAuthorizedContact> $existing */
        $existing = [];
        foreach (AccountAuthorizedContact::query()->where('company_id', $companyId)->where('account_id', $accountId)->get() as $contact) {
            $existing[(int) $contact->getKey()] = $contact;
        }

        /** @var array<int, true> $kept */
        $kept = [];

        foreach ($rows as $index => $row) {
            $phone = $this->optionalPhone($row['phone'] ?? null, "authorized_contacts.$index.phone");
            $email = $this->optionalEmail($row['email'] ?? null, "authorized_contacts.$index.email");
            if ($phone === null && $email === null) {
                throw ValidationException::withMessages(["authorized_contacts.$index.phone" => 'Yetkili için telefon veya e-posta bilgilerinden en az biri girilmelidir.']);
            }

            $id = $row['id'] ?? null;
            $contact = $id === null ? new AccountAuthorizedContact : ($existing[$id] ?? null);
            if (! $contact instanceof AccountAuthorizedContact) {
                throw ValidationException::withMessages(["authorized_contacts.$index.id" => 'Yetkili kaydı bu cariye ait değil.']);
            }

            $contact->fill([
                'company_id' => $companyId,
                'account_id' => $accountId,
                'name' => $this->requiredText($row['name'], 160, "authorized_contacts.$index.name", 'Yetkili adı zorunludur.'),
                'title' => $this->optionalText($row['title'] ?? null, 120, "authorized_contacts.$index.title"),
                'phone' => $phone,
                'email' => $email,
                'is_primary' => (bool) ($row['is_primary'] ?? false),
                'note' => $this->optionalText($row['note'] ?? null, 500, "authorized_contacts.$index.note"),
            ]);
            $contact->save();
            $kept[(int) $contact->getKey()] = true;
        }

        foreach ($existing as $id => $contact) {
            if (! isset($kept[$id])) {
                $contact->delete();
            }
        }
    }

    /** @param list<array{id?: int, type: string, label: string, recipient_name?: string|null, line1: string, line2?: string|null, district?: string|null, city: string, postal_code?: string|null, country_code: string, is_default?: bool|string|int}> $rows */
    private function syncAddresses(int $companyId, int $accountId, array $rows): void
    {
        /** @var array<int, AccountAddress> $existing */
        $existing = [];
        foreach (AccountAddress::query()->where('company_id', $companyId)->where('account_id', $accountId)->get() as $address) {
            $existing[(int) $address->getKey()] = $address;
        }

        /** @var array<int, true> $kept */
        $kept = [];

        foreach ($rows as $index => $row) {
            $type = AccountAddressType::tryFrom($row['type'])
                ?? throw ValidationException::withMessages(["addresses.$index.type" => 'Geçersiz adres türü.']);
            $countryCode = mb_strtoupper(trim($row['country_code']));
            if (preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
                throw ValidationException::withMessages(["addresses.$index.country_code" => 'Ülke kodu iki harfli ISO kodu olmalıdır.']);
            }

            $id = $row['id'] ?? null;
            $address = $id === null ? new AccountAddress : ($existing[$id] ?? null);
            if (! $address instanceof AccountAddress) {
                throw ValidationException::withMessages(["addresses.$index.id" => 'Adres kaydı bu cariye ait değil.']);
            }

            $address->fill([
                'company_id' => $companyId,
                'account_id' => $accountId,
                'type' => $type,
                'label' => $this->requiredText($row['label'], 80, "addresses.$index.label", 'Adres etiketi zorunludur.'),
                'recipient_name' => $this->optionalText($row['recipient_name'] ?? null, 200, "addresses.$index.recipient_name"),
                'line1' => $this->requiredText($row['line1'], 240, "addresses.$index.line1", 'Adres satırı zorunludur.'),
                'line2' => $this->optionalText($row['line2'] ?? null, 240, "addresses.$index.line2"),
                'district' => $this->optionalText($row['district'] ?? null, 120, "addresses.$index.district"),
                'city' => $this->requiredText($row['city'], 120, "addresses.$index.city", 'Şehir zorunludur.'),
                'postal_code' => $this->optionalText($row['postal_code'] ?? null, 20, "addresses.$index.postal_code"),
                'country_code' => $countryCode,
                'is_default' => (bool) ($row['is_default'] ?? false),
            ]);
            $address->save();
            $kept[(int) $address->getKey()] = true;
        }

        foreach ($existing as $id => $address) {
            if (! isset($kept[$id])) {
                $address->delete();
            }
        }
    }

    /** @param list<array{id?: int, company_name: string, city: string, branch?: string|null, contact_name?: string|null, phone?: string|null, preference?: string|null, address?: string|null, note?: string|null, is_default?: bool|string|int}> $rows */
    private function syncShippingPreferences(int $companyId, int $accountId, array $rows): void
    {
        /** @var array<int, AccountShippingPreference> $existing */
        $existing = [];
        foreach (AccountShippingPreference::query()->where('company_id', $companyId)->where('account_id', $accountId)->get() as $preference) {
            $existing[(int) $preference->getKey()] = $preference;
        }

        /** @var array<int, true> $kept */
        $kept = [];

        foreach ($rows as $index => $row) {
            $id = $row['id'] ?? null;
            $preference = $id === null ? new AccountShippingPreference : ($existing[$id] ?? null);
            if (! $preference instanceof AccountShippingPreference) {
                throw ValidationException::withMessages(["shipping_preferences.$index.id" => 'Ambar / nakliye kaydı bu cariye ait değil.']);
            }

            $preference->fill([
                'company_id' => $companyId,
                'account_id' => $accountId,
                'company_name' => $this->requiredText($row['company_name'], 200, "shipping_preferences.$index.company_name", 'Ambar / nakliye firma adı zorunludur.'),
                'city' => $this->requiredText($row['city'], 120, "shipping_preferences.$index.city", 'Ambar / nakliye şehri zorunludur.'),
                'branch' => $this->optionalText($row['branch'] ?? null, 120, "shipping_preferences.$index.branch"),
                'contact_name' => $this->optionalText($row['contact_name'] ?? null, 160, "shipping_preferences.$index.contact_name"),
                'phone' => $this->optionalPhone($row['phone'] ?? null, "shipping_preferences.$index.phone"),
                'preference' => $this->optionalText($row['preference'] ?? null, 120, "shipping_preferences.$index.preference"),
                'address' => $this->optionalText($row['address'] ?? null, 500, "shipping_preferences.$index.address"),
                'note' => $this->optionalText($row['note'] ?? null, 1000, "shipping_preferences.$index.note"),
                'is_default' => (bool) ($row['is_default'] ?? false),
            ]);
            $preference->save();
            $kept[(int) $preference->getKey()] = true;
        }

        foreach ($existing as $id => $preference) {
            if (! isset($kept[$id])) {
                $preference->delete();
            }
        }
    }

    private function assertPrimaryRules(UpdateAccountProfileData $data): void
    {
        $contactPrimaryCounts = [];
        foreach ($data->contacts as $row) {
            if (! ($row['is_primary'] ?? false)) {
                continue;
            }
            $contactPrimaryCounts[$row['kind']] = ($contactPrimaryCounts[$row['kind']] ?? 0) + 1;
        }
        foreach ($contactPrimaryCounts as $count) {
            if ($count > 1) {
                throw ValidationException::withMessages(['contacts' => 'Her iletişim türünde en fazla bir birincil kayıt olabilir.']);
            }
        }

        if ($data->authorizedContacts !== []) {
            $primaryCount = count(array_filter(
                $data->authorizedContacts,
                static fn (array $row): bool => (bool) ($row['is_primary'] ?? false),
            ));
            if ($primaryCount !== 1) {
                throw ValidationException::withMessages(['authorized_contacts' => 'Yetkili listesinde tam olarak bir birincil yetkili seçilmelidir.']);
            }
        }

        $addressDefaults = [];
        foreach ($data->addresses as $row) {
            if (! ($row['is_default'] ?? false)) {
                continue;
            }
            $addressDefaults[$row['type']] = ($addressDefaults[$row['type']] ?? 0) + 1;
        }
        foreach ($addressDefaults as $count) {
            if ($count > 1) {
                throw ValidationException::withMessages(['addresses' => 'Fatura ve sevk adresi türlerinin her birinde en fazla bir varsayılan kayıt olabilir.']);
            }
        }

        $shippingDefaultCount = count(array_filter(
            $data->shippingPreferences,
            static fn (array $row): bool => (bool) ($row['is_default'] ?? false),
        ));
        if ($shippingDefaultCount > 1) {
            throw ValidationException::withMessages(['shipping_preferences' => 'En fazla bir varsayılan Ambar / Nakliye tercihi seçilebilir.']);
        }
    }

    private function normalizePhone(string $raw, string $field): string
    {
        $value = (string) preg_replace('/[^0-9+]/', '', trim($raw));
        if (preg_match('/^\+?[0-9]{7,20}$/', $value) !== 1 || mb_strlen($value) > 40) {
            throw ValidationException::withMessages([$field => 'Telefon numarası geçersizdir.']);
        }

        return $value;
    }

    private function optionalPhone(?string $raw, string $field): ?string
    {
        if (trim((string) $raw) === '') {
            return null;
        }

        return $this->normalizePhone((string) $raw, $field);
    }

    private function normalizeEmail(string $raw, string $field): string
    {
        $value = mb_strtolower(trim($raw));
        if (mb_strlen($value) > 200 || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages([$field => 'E-posta adresi geçersizdir.']);
        }

        return $value;
    }

    private function optionalEmail(?string $raw, string $field): ?string
    {
        if (trim((string) $raw) === '') {
            return null;
        }

        return $this->normalizeEmail((string) $raw, $field);
    }

    private function requiredText(string $raw, int $max, string $field, string $message): string
    {
        $value = trim($raw);
        if ($value === '' || mb_strlen($value) > $max) {
            throw ValidationException::withMessages([$field => $message]);
        }

        return $value;
    }

    private function optionalText(?string $raw, int $max, string $field): ?string
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $max) {
            throw ValidationException::withMessages([$field => 'Alan izin verilen uzunluğu aşıyor.']);
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private function snapshot(Account $account): array
    {
        $contacts = [];
        foreach ($account->contacts->sortBy('id') as $contact) {
            $contacts[] = [
                'id' => $contact->getKey(),
                'kind' => $contact->kindEnum()->value,
                'label' => $contact->label,
                'value' => $contact->value,
                'is_primary' => (bool) $contact->is_primary,
            ];
        }

        $authorizedContacts = [];
        foreach ($account->authorizedContacts->sortBy('id') as $contact) {
            $authorizedContacts[] = [
                'id' => $contact->getKey(),
                'name' => $contact->name,
                'title' => $contact->title,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'is_primary' => (bool) $contact->is_primary,
                'note' => $contact->note,
            ];
        }

        $addresses = [];
        foreach ($account->addresses->sortBy('id') as $address) {
            $addresses[] = [
                'id' => $address->getKey(),
                'type' => $address->typeEnum()->value,
                'label' => $address->label,
                'recipient_name' => $address->recipient_name,
                'address' => trim(implode(' ', array_filter([
                    $address->line1,
                    $address->line2,
                    $address->district,
                    $address->city,
                    $address->postal_code,
                    $address->country_code,
                ]))),
                'is_default' => (bool) $address->is_default,
            ];
        }

        $shippingPreferences = [];
        foreach ($account->shippingPreferences->sortBy('id') as $preference) {
            $shippingPreferences[] = [
                'id' => $preference->getKey(),
                'company_name' => $preference->company_name,
                'city' => $preference->city,
                'branch' => $preference->branch,
                'contact_name' => $preference->contact_name,
                'phone' => $preference->phone,
                'preference' => $preference->preference,
                'address' => $preference->address,
                'note' => $preference->note,
                'is_default' => (bool) $preference->is_default,
            ];
        }

        return [
            'contacts' => $contacts,
            'authorized_contacts' => $authorizedContacts,
            'addresses' => $addresses,
            'shipping_preferences' => $shippingPreferences,
        ];
    }

    private function companyId(): int
    {
        $companyId = $this->companyContext->requireCompany()->getKey();
        if (! is_int($companyId)) {
            throw new LogicException('Account profile update requires a persisted active company.');
        }

        return $companyId;
    }
}
