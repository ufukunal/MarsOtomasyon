<?php

namespace App\Actions\Contacts;

use App\Models\Period\Contact;
use App\Models\Period\ContactPerson;
use Illuminate\Support\Facades\DB;

final class SaveContactPerson
{
    public function handle(
        Contact $contact,
        array $data,
        ?ContactPerson $person = null,
        ?int $expectedVersion = null,
    ): ContactPerson {
        abort_if($person && $person->contact_id !== $contact->id, 404);

        return DB::connection('period')->transaction(function () use ($contact, $data, $person, $expectedVersion): ContactPerson {
            if ((bool) ($data['is_default'] ?? false)) {
                ContactPerson::query()
                    ->where('contact_id', $contact->id)
                    ->when($person, fn ($q) => $q->whereKeyNot($person->id))
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $attributes = [
                'contact_id' => $contact->id,
                'name' => trim((string) $data['name']),
                'title' => trim((string) ($data['title'] ?? '')) ?: null,
                'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
                'email' => trim((string) ($data['email'] ?? '')) ?: null,
                'is_default' => (bool) ($data['is_default'] ?? false),
            ];

            return $person
                ? $person->updateWithVersion($attributes, $expectedVersion ?? (int) $person->version)
                : ContactPerson::query()->create($attributes);
        });
    }
}
