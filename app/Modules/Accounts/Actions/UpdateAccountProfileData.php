<?php

namespace App\Modules\Accounts\Actions;

final readonly class UpdateAccountProfileData
{
    /** @var list<array{id?: int, kind: string, label?: string|null, value: string, is_primary?: bool|string|int}> */
    public array $contacts;

    /** @var list<array{id?: int, name: string, title?: string|null, phone?: string|null, email?: string|null, is_primary?: bool|string|int, note?: string|null}> */
    public array $authorizedContacts;

    /** @var list<array{id?: int, type: string, label: string, recipient_name?: string|null, line1: string, line2?: string|null, district?: string|null, city: string, postal_code?: string|null, country_code: string, is_default?: bool|string|int}> */
    public array $addresses;

    /** @var list<array{id?: int, company_name: string, city: string, branch?: string|null, contact_name?: string|null, phone?: string|null, preference?: string|null, address?: string|null, note?: string|null, is_default?: bool|string|int}> */
    public array $shippingPreferences;

    /** @param  array{contacts: list<array{id?: int, kind: string, label?: string|null, value: string, is_primary?: bool|string|int}>, authorized_contacts: list<array{id?: int, name: string, title?: string|null, phone?: string|null, email?: string|null, is_primary?: bool|string|int, note?: string|null}>, addresses: list<array{id?: int, type: string, label: string, recipient_name?: string|null, line1: string, line2?: string|null, district?: string|null, city: string, postal_code?: string|null, country_code: string, is_default?: bool|string|int}>, shipping_preferences: list<array{id?: int, company_name: string, city: string, branch?: string|null, contact_name?: string|null, phone?: string|null, preference?: string|null, address?: string|null, note?: string|null, is_default?: bool|string|int}>}  $payload */
    public function __construct(array $payload)
    {
        $this->contacts = $payload['contacts'];
        $this->authorizedContacts = $payload['authorized_contacts'];
        $this->addresses = $payload['addresses'];
        $this->shippingPreferences = $payload['shipping_preferences'];
    }
}
