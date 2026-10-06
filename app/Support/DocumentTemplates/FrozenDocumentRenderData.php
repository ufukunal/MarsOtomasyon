<?php

namespace App\Support\DocumentTemplates;

use DomainException;

final readonly class FrozenDocumentRenderData
{
    /** @param array<string,array<string,mixed>> $domains */
    public function __construct(public array $domains)
    {
        foreach ($domains as $domain => $values) {
            foreach ($values as $field => $value) {
                if (! is_scalar($value) && $value !== null) {
                    throw new DomainException("Render DTO {$domain}.{$field} scalar olmalıdır.");
                }
            }
        }
    }

    public function value(string $token): string
    {
        [$domain, $field] = array_pad(explode('.', $token, 2), 2, '');

        $value = $this->domains[$domain][$field] ?? null;

        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
