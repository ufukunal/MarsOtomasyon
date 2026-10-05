<?php

namespace App\Support\DocumentTemplates;

final class TemplateTokenRegistry
{
    /** @var array<string,list<string>> */
    private const TOKENS = [
        'company' => [
            'code', 'name', 'legal_name', 'tax_office', 'tax_number',
            'address', 'city', 'phone', 'email',
        ],
        'document' => [
            'id', 'type', 'number', 'date', 'status', 'currency',
            'subtotal', 'discount_amount', 'vat_amount', 'grand_total',
        ],
        'contact' => [
            'id', 'code', 'title', 'tax_office', 'tax_number',
        ],
        'shipping' => [
            'recipient_name', 'address', 'city', 'district', 'postcode',
            'cargo_company', 'cargo_code',
        ],
        'line' => [
            'position', 'product_code', 'product_name', 'quantity', 'unit',
            'price', 'discount_amount', 'vat_rate', 'vat_amount', 'total',
        ],
        'totals' => [
            'subtotal', 'discount_amount', 'vat_amount', 'grand_total',
        ],
        'user' => [
            'name',
        ],
    ];

    public function allows(string $token): bool
    {
        [$domain, $field] = array_pad(explode('.', $token, 2), 2, null);

        return is_string($domain)
            && is_string($field)
            && isset(self::TOKENS[$domain])
            && in_array($field, self::TOKENS[$domain], true);
    }

    /** @return list<string> */
    public function all(): array
    {
        $tokens = [];

        foreach (self::TOKENS as $domain => $fields) {
            foreach ($fields as $field) {
                $tokens[] = "{$domain}.{$field}";
            }
        }

        return $tokens;
    }
}
