<?php

namespace App\Actions\Contacts;

use App\Exceptions\StaleRecordException;
use App\Models\Period\Contact;
use App\Models\Period\ContactPerson;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Period\PeriodContext;

final class DeleteContactPerson
{
    public function handle(Contact $contact, ContactPerson $person, int $expectedVersion): void
    {
        MutationAuthorizer::authorize('contacts.update');
        PeriodContext::ensureWritable();
        abort_unless((int) $person->contact_id === (int) $contact->id, 404);

        $deleted = ContactPerson::query()->whereKey($person->id)->where('version', $expectedVersion)->delete();

        if ($deleted !== 1) {
            throw new StaleRecordException('Cari yetkilisi eşzamanlı olarak değiştirildi.');
        }
    }
}
