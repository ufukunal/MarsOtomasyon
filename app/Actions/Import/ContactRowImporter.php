<?php

namespace App\Actions\Import;

use App\Actions\Contacts\SaveContact;
use App\Models\Period\Contact;
use App\Support\Import\RowValidationResult;

final class ContactRowImporter
{
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

    public function import(array $row): Contact
    {
        if (! empty($row['code'])) {
            $contact = new Contact;
            $contact->forceFill([
                'code' => strtoupper(trim((string) $row['code'])),
                'title' => trim((string) $row['title']),
                'type' => (string) ($row['type'] ?: 'legal'),
                'tax_office' => $row['tax_office'] ?: null,
                'tax_number' => $row['tax_number'] ?: null,
                'national_id' => $row['national_id'] ?: null,
                'address' => $row['address'] ?: null,
                'city' => $row['city'] ?: null,
                'district' => $row['district'] ?: null,
                'phone' => $row['phone'] ?: null,
                'email' => $row['email'] ?: null,
                'term_days' => $row['term_days'] ?: null,
                'risk_limit' => bcadd((string) ($row['risk_limit'] ?: '0'), '0', 4),
                'discount_rate' => bcadd((string) ($row['discount_rate'] ?: '0'), '0', 4),
                'is_active' => true,
            ]);
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
