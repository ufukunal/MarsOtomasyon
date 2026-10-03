<?php

namespace App\Actions\Contacts;

use App\Models\Period\Contact;
use App\Models\Period\ContactAddress;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Period\PeriodContext;
use Illuminate\Support\Facades\DB;

final class SaveContactAddress
{
    /** @param array<string, mixed> $data */
    public function handle(
        Contact $contact,
        array $data,
        ?ContactAddress $address = null,
        ?int $expectedVersion = null,
    ): ContactAddress {
        MutationAuthorizer::authorize('contacts.update');
        PeriodContext::ensureWritable();
        abort_if($address && $address->contact_id !== $contact->id, 404);

        return DB::connection('period')->transaction(function () use ($contact, $data, $address, $expectedVersion): ContactAddress {
            if ((bool) ($data['is_default'] ?? false)) {
                ContactAddress::query()
                    ->where('contact_id', $contact->id)
                    ->where('type', $data['type'])
                    ->when($address, fn ($q) => $q->whereKeyNot($address->id))
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $attributes = [
                'contact_id' => $contact->id,
                'type' => (string) $data['type'],
                'title' => trim((string) ($data['title'] ?? '')) ?: null,
                'address' => trim((string) $data['address']),
                'city' => trim((string) ($data['city'] ?? '')) ?: null,
                'district' => trim((string) ($data['district'] ?? '')) ?: null,
                'is_default' => (bool) ($data['is_default'] ?? false),
            ];

            return $address
                ? $address->updateWithVersion($attributes, $expectedVersion ?? (int) $address->version)
                : ContactAddress::query()->create($attributes);
        });
    }
}
