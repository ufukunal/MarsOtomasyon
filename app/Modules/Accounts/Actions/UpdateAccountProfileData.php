<?php

namespace App\Modules\Accounts\Actions;

/**
 * @phpstan-type  ContactRow             array{id?: int, kind: string, label?: string|null, value: string, is_primary?: bool|string|int}
 * @phpstan-type  AuthorizedContactRow   array{id?: int, name: string, title?: string|null, phone?: string|null, email?: string|null, is_primary?: bool|string|int, note?: string|null}
 * @phpstan-type  AddressRow             array{id?: int, type: string, label: string, recipient_name?: string|null, line1: string, line2?: string|null, district?: string|null, city: string, postal_code?: string|null, country_code: string, is_default?: bool|string|int}
 * @phpstan-type  ShippingPreferenceRow  array{id?: int, company_name: string, city: string, branch?: string|null, contact_name?: string|null, phone?: string|null, preference?: string|null, address?: string|null, note?: string|null, is_default?: bool|string|int}
 */
final readonly class UpdateAccountProfileData
{
    /** @var list<ContactRow> */
    public array $contacts;

    /** @var list<AuthorizedContactRow> */
    public array $authorizedContacts;

    /** @var list<AddressRow> */
    public array $addresses;

    /** @var list<ShippingPreferenceRow> */
    public array $shippingPreferences;

    /**
     * @param  list<ContactRow>             $contacts
     * @param  list<AuthorizedContactRow>   $authorizedContacts
     * @param  list<AddressRow>             $addresses
     * @param  list<ShippingPreferenceRow>  $shippingPreferences
     */
    public function __construct(
        array $contacts,
        array $authorizedContacts,
        array $addresses,
        array $shippingPreferences,
    ) {
        $this->contacts = $contacts;
        $this->authorizedContacts = $authorizedContacts;
        $this->addresses = $addresses;
        $this->shippingPreferences = $shippingPreferences;
    }
}
