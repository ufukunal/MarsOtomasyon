<?php

namespace App\Foundation\Idempotency;

use InvalidArgumentException;
use JsonException;
use Stringable;

final readonly class RequestFingerprint implements Stringable
{
    private function __construct(public string $value)
    {
    }

    /** @throws JsonException */
    public static function fromPayload(array $payload): self
    {
        $json = json_encode(
            self::canonicalize($payload),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        );

        return new self(hash('sha256', $json));
    }

    public static function fromHash(string $hash): self
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) {
            throw new InvalidArgumentException('Fingerprint must be a lowercase SHA-256 hex digest.');
        }

        return new self($hash);
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map(self::canonicalize(...), $value);
            }

            ksort($value, SORT_STRING);

            foreach ($value as $key => $item) {
                $value[$key] = self::canonicalize($item);
            }

            return $value;
        }

        if ($value === null || is_scalar($value)) {
            return $value;
        }

        throw new InvalidArgumentException('Fingerprint payload must contain only JSON-compatible values.');
    }
}
