<?php

namespace App\Support\DocumentTemplates;

use DomainException;

final class TemplateDefinitionValidator
{
    public const SECTION_TYPES = [
        'header',
        'company',
        'customer',
        'supplier',
        'document_info',
        'address',
        'line_table',
        'totals',
        'payment',
        'notes',
        'signature',
        'footer',
        'barcode',
        'qr',
        'custom_text',
        'spacer',
        'page_break',
    ];

    /** @param array<string,mixed> $definition @return array{sections:list<array{type:string,visible:bool,settings:array<string,mixed>}>} */
    public function normalize(array $definition): array
    {
        $unknownRoot = array_diff(array_keys($definition), ['sections']);

        if ($unknownRoot !== []) {
            throw new DomainException('Template definition yalnız sections kök alanını içerebilir.');
        }

        $sections = $definition['sections'] ?? null;

        if (! is_array($sections) || ! array_is_list($sections)) {
            throw new DomainException('Template sections sıralı liste olmalıdır.');
        }

        if (count($sections) > 100) {
            throw new DomainException('Template en fazla 100 section içerebilir.');
        }

        $normalized = [];

        foreach ($sections as $index => $section) {
            if (! is_array($section)) {
                throw new DomainException("Template section #{$index} nesne olmalıdır.");
            }

            $unknown = array_diff(array_keys($section), ['type', 'visible', 'settings']);

            if ($unknown !== []) {
                throw new DomainException("Template section #{$index} desteklenmeyen alan içeriyor.");
            }

            $type = trim((string) ($section['type'] ?? ''));

            if (! in_array($type, self::SECTION_TYPES, true)) {
                throw new DomainException("Template section tipi desteklenmiyor: {$type}.");
            }

            $visible = $section['visible'] ?? true;

            if (! is_bool($visible)) {
                throw new DomainException("Template section #{$index} visible boolean olmalıdır.");
            }

            $settings = $section['settings'] ?? [];

            if (! is_array($settings) || array_is_list($settings)) {
                throw new DomainException("Template section #{$index} settings nesne olmalıdır.");
            }

            $this->assertJsonSafe($settings, "sections.{$index}.settings");

            $normalized[] = [
                'type' => $type,
                'visible' => $visible,
                'settings' => $settings,
            ];
        }

        return ['sections' => $normalized];
    }

    private function assertJsonSafe(array $value, string $path): void
    {
        foreach ($value as $key => $item) {
            if (! is_string($key) || trim($key) === '') {
                throw new DomainException("{$path} anahtarları boş olamaz.");
            }

            if (is_array($item)) {
                $this->assertJsonArraySafe($item, "{$path}.{$key}");
                continue;
            }

            if (! is_scalar($item) && $item !== null) {
                throw new DomainException("{$path}.{$key} JSON-safe scalar/array olmalıdır.");
            }
        }
    }

    private function assertJsonArraySafe(array $value, string $path): void
    {
        foreach ($value as $index => $item) {
            if (is_array($item)) {
                $this->assertJsonArraySafe($item, "{$path}.{$index}");
            } elseif (! is_scalar($item) && $item !== null) {
                throw new DomainException("{$path}.{$index} JSON-safe olmalıdır.");
            }
        }
    }
}
