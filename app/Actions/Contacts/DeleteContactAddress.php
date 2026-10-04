<?php

namespace App\Actions\Contacts;

use App\Exceptions\StaleRecordException;
use App\Models\Period\Contact;
use App\Models\Period\ContactAddress;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Period\PeriodContext;

final class DeleteContactAddress
{
    public function handle(Contact $contact, ContactAddress $address, int $expectedVersion): void
    {
        MutationAuthorizer::authorize('contacts.update');
        PeriodContext::ensureWritable();
        abort_unless((int) $address->contact_id === (int) $contact->id, 404);

        $deleted = ContactAddress::query()->whereKey($address->id)->where('version', $expectedVersion)->delete();

        if ($deleted !== 1) {
            throw new StaleRecordException('Cari adresi eşzamanlı olarak değiştirildi.');
        }
    }
}
