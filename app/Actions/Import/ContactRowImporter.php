<?php

namespace App\Actions\Import;

use App\Actions\Contacts\SaveContact;
use App\Models\Period\Contact;
use App\Support\Import\RowValidationResult;

final class ContactRowImporter
{
    /** @param array<string, mixed> $row */
    public function validate(array $row): RowValidationResult
    {
        $errors = [];

        if (trim((string) ($row['title'] ?? '')) === '') {
            $errors['title'] = 'Unvan zorunludur.';
        }

        if (! empty($row['email']) && ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'E-posta biçimi geçersiz.';
        }

        if (! empty($row['code']) && Contact::query()->where('code', strtoupper((string) $row['code']))->exists()) {
            $errors['code'] = 'Cari kodu hedefte zaten var.';
        }

        return new RowValidationResult($errors === [], $errors);
    }

    /** @param array<string, mixed> $row */
    public function import(array $row): Contact
    {
        if (! empty($row['code'])) {
            $contact = new Contact;
            $contact->code = strtoupper(trim((string) $row['code']));
            $contact->title = trim((string) $row['title']);
            $contact->type = (string) ($row['type'] ?: 'legal');
            $contact->tax_office = $row['tax_office'] ?: null;
            $contact->tax_number = $row['tax_number'] ?: null;
            $contact->national_id = $row['national_id'] ?: null;
            $contact->address = $row['address'] ?: null;
            $contact->city = $row['city'] ?: null;
            $contact->district = $row['district'] ?: null;
            $contact->phone = $row['phone'] ?: null;
            $contact->email = $row['email'] ?: null;
            $contact->term_days = $row['term_days'] ?: null;
            $contact->risk_limit = bcadd((string) ($row['risk_limit'] ?: '0'), '0', 4);
            $contact->discount_rate = bcadd((string) ($row['discount_rate'] ?: '0'), '0', 4);
            $contact->is_active = true;
            $contact->save();

            return $contact;
        }

        return app(SaveContact::class)->handle([
            ...$row,
            'risk_limit' => $row['risk_limit'] ?: '0',
            'discount_rate' => $row['discount_rate'] ?: '0',
            'category_ids' => [],
            'is_active' => true,
        ]);
    }
}
