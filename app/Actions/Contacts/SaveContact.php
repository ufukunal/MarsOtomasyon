<?php

namespace App\Actions\Contacts;

use App\Models\Period\Contact;
use App\Support\Period\PeriodContext;
use Illuminate\Support\Facades\DB;

final class SaveContact
{
    public function handle(
        array $data,
        ?Contact $contact = null,
        ?int $expectedVersion = null,
    ): Contact {
        PeriodContext::ensureWritable();

        return DB::connection('period')->transaction(function () use ($data, $contact, $expectedVersion): Contact {
            $categoryIds = array_values(array_unique(array_map(
                'intval',
                $data['category_ids'] ?? [],
            )));

            unset($data['category_ids'], $data['code']);

            $attributes = [
                'title' => trim((string) $data['title']),
                'type' => (string) ($data['type'] ?? 'legal'),
                'tax_office' => trim((string) ($data['tax_office'] ?? '')) ?: null,
                'tax_number' => trim((string) ($data['tax_number'] ?? '')) ?: null,
                'national_id' => trim((string) ($data['national_id'] ?? '')) ?: null,
                'address' => trim((string) ($data['address'] ?? '')) ?: null,
                'city' => trim((string) ($data['city'] ?? '')) ?: null,
                'district' => trim((string) ($data['district'] ?? '')) ?: null,
                'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
                'email' => trim((string) ($data['email'] ?? '')) ?: null,
                'term_days' => $data['term_days'] ?? null,
                'risk_limit' => bcadd((string) ($data['risk_limit'] ?? '0'), '0', 4),
                'discount_rate' => bcadd((string) ($data['discount_rate'] ?? '0'), '0', 4),
                'price_list_id' => $data['price_list_id'] ?: null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ];

            if ($contact) {
                $contact = $contact->updateWithVersion(
                    $attributes,
                    $expectedVersion ?? (int) $contact->version,
                );
            } else {
                $contact = new Contact($attributes);
                $contact->save();
                $contact->refresh();
            }

            $contact->categories()->sync($categoryIds);

            return $contact->refresh();
        });
    }
}
