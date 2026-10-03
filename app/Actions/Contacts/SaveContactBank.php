<?php

namespace App\Actions\Contacts;

use App\Models\Period\Contact;
use App\Models\Period\ContactBank;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveContactBank
{
    public function handle(
        Contact $contact,
        array $data,
        ?ContactBank $bank = null,
        ?int $expectedVersion = null,
    ): ContactBank {
        abort_if($bank && $bank->contact_id !== $contact->id, 404);

        $iban = strtoupper(str_replace(' ', '', (string) $data['iban']));

        if (! preg_match('/^TR\d{24}$/D', $iban)) {
            throw ValidationException::withMessages([
                'iban' => 'IBAN TR ile başlamalı ve ardından 24 rakam içermelidir.',
            ]);
        }

        return DB::connection('period')->transaction(function () use ($contact, $data, $bank, $expectedVersion, $iban): ContactBank {
            if ((bool) ($data['is_default'] ?? false)) {
                ContactBank::query()
                    ->where('contact_id', $contact->id)
                    ->when($bank, fn ($q) => $q->whereKeyNot($bank->id))
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $attributes = [
                'contact_id' => $contact->id,
                'bank_name' => trim((string) ($data['bank_name'] ?? '')) ?: null,
                'iban' => $iban,
                'is_default' => (bool) ($data['is_default'] ?? false),
            ];

            return $bank
                ? $bank->updateWithVersion($attributes, $expectedVersion ?? (int) $bank->version)
                : ContactBank::query()->create($attributes);
        });
    }
}
