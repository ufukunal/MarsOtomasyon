<?php

namespace App\Support\Reporting;

use DomainException;

final readonly class ReportFilterDefinition
{
    /** @param list<string|int> $allowedValues */
    public function __construct(
        public string $key,
        public string $label,
        public string $type = 'string',
        public bool $required = false,
        public array $allowedValues = [],
    ) {}

    public function normalize(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            if ($this->required) {
                throw new DomainException("Rapor filtresi zorunlu: {$this->key}.");
            }

            return null;
        }

        return match ($this->type) {
            'string' => $this->stringValue($value),
            'date' => $this->dateValue($value),
            'integer' => $this->integerValue($value, false),
            'positive_integer' => $this->integerValue($value, true),
            'decimal' => $this->decimalValue($value),
            'boolean' => $this->booleanValue($value),
            'select' => $this->selectValue($value),
            default => throw new DomainException("Desteklenmeyen rapor filtre tipi: {$this->type}."),
        };
    }

    private function stringValue(mixed $value): string
    {
        if (! is_scalar($value)) {
            throw new DomainException("Rapor filtresi metin olmalıdır: {$this->key}.");
        }

        $normalized = trim((string) $value);

        if ($normalized === '' || mb_strlen($normalized) > 255) {
            throw new DomainException("Rapor filtresi geçersiz: {$this->key}.");
        }

        return $normalized;
    }

    private function dateValue(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            throw new DomainException("Rapor tarih filtresi YYYY-MM-DD olmalıdır: {$this->key}.");
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (! $date || $date->format('Y-m-d') !== $value) {
            throw new DomainException("Rapor tarih filtresi geçersiz: {$this->key}.");
        }

        return $value;
    }

    private function integerValue(mixed $value, bool $positive): int
    {
        if (! is_int($value) && ! is_string($value)) {
            throw new DomainException("Rapor filtresi tam sayı olmalıdır: {$this->key}.");
        }

        $raw = is_int($value) ? (string) $value : trim($value);

        if (! preg_match('/^-?\d+$/D', $raw)) {
            throw new DomainException("Rapor filtresi tam sayı olmalıdır: {$this->key}.");
        }

        $normalized = (int) $raw;

        if ($positive && $normalized <= 0) {
            throw new DomainException("Rapor filtresi pozitif tam sayı olmalıdır: {$this->key}.");
        }

        return $normalized;
    }

    private function decimalValue(mixed $value): string
    {
        if (! is_int($value) && ! is_float($value) && ! is_string($value)) {
            throw new DomainException("Rapor filtresi decimal olmalıdır: {$this->key}.");
        }

        $raw = trim((string) $value);

        if (! preg_match('/^-?\d+(?:\.\d+)?$/D', $raw)) {
            throw new DomainException("Rapor filtresi decimal olmalıdır: {$this->key}.");
        }

        return $raw;
    }

    private function booleanValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (! is_int($value) && ! is_string($value)) {
            throw new DomainException("Rapor filtresi boolean olmalıdır: {$this->key}.");
        }

        return match (strtolower(trim((string) $value))) {
            '1', 'true' => true,
            '0', 'false' => false,
            default => throw new DomainException("Rapor filtresi boolean olmalıdır: {$this->key}."),
        };
    }

    private function selectValue(mixed $value): string|int
    {
        if (! is_scalar($value)) {
            throw new DomainException("Rapor select filtresi geçersiz: {$this->key}.");
        }

        foreach ($this->allowedValues as $allowed) {
            if ((string) $allowed === (string) $value) {
                return $allowed;
            }
        }

        throw new DomainException("Rapor select filtresi izinli değerlerden biri değil: {$this->key}.");
    }
}
