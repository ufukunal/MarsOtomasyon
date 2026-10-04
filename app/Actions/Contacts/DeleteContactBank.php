<?php

namespace App\Actions\Contacts;

use App\Exceptions\StaleRecordException;
use App\Models\Period\Contact;
use App\Models\Period\ContactBank;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Period\PeriodContext;

final class DeleteContactBank
{
    public function handle(Contact $contact, ContactBank $bank, int $expectedVersion): void
    {
        MutationAuthorizer::authorize('contacts.update');
        PeriodContext::ensureWritable();
        abort_unless((int) $bank->contact_id === (int) $contact->id, 404);

        $deleted = ContactBank::query()->whereKey($bank->id)->where('version', $expectedVersion)->delete();

        if ($deleted !== 1) {
            throw new StaleRecordException('Cari banka kaydı eşzamanlı olarak değiştirildi.');
        }
    }
}
