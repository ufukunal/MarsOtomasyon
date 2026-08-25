<?php

namespace App\Modules\Accounts\Actions;

final readonly class UpdateAccountProfileData
{
    /**
     * @param list<array{id?: int, kind: string, label?: string|null, value: string, is_primary?: bool|string|int}> $contacts
     * @param list<array{id?: int, name: string, title?: string|null, phone?: string|null, email?: string|null, is_primary?: bool|string|int, note?: string|null}> $authorizedContacts
     * @param list<array{id?: int, type: string, label: string, recipient_name?: string|null, line1: string, line2?: string|null, district?: string|null, city: string, postal_code?: string|null, country_code: string, is_default?: bool|string|int}> $addresses
     * @param list<array{id?: int, company_name: string, city: string, branch?: string|null, contact_name?: string|null, phone?: string|null, preference?: string|null, address?: string|null, note?: string|null, is_default?: bool|string|int}> $shippingPreferences
     */
    public function __construct(
        public array $contacts,
        public array $authorizedContacts,
        public array $addresses,
        public array $shippingPreferences,
    ) {
    }
}
