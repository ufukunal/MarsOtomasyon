<?php

namespace App\Foundation\Outbox;

use InvalidArgumentException;
use JsonException;

final class OutboxPayload
{
    /** @param array<array-key, mixed> $payload */
    public static function validate(array $payload, OutboxEventDefinition $definition): void
    {
        $keys = array_keys($payload);

        foreach ($keys as $key) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('Outbox payload must use named top-level fields.');
            }
        }

        $unknown = array_values(array_diff($keys, $definition->allowedPayloadKeys));
        if ($unknown !== []) {
            throw new InvalidArgumentException('Outbox payload contains fields not allowed by the event contract.');
        }

        $missing = array_values(array_diff($definition->requiredPayloadKeys, $keys));
        if ($missing !== []) {
            throw new InvalidArgumentException('Outbox payload is missing required event-contract fields.');
        }

        self::assertNoSensitiveKeys($payload);
        self::canonicalJson($payload);
    }

    /** @param array<array-key, mixed> $payload */
    public static function canonicalJson(array $payload): string
    {
        try {
            return json_encode(
                self::normalize($payload),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Outbox payload must contain JSON-compatible values.', previous: $exception);
        }
    }

    /** @param array<array-key, mixed> $payload */
    private static function assertNoSensitiveKeys(array $payload): void
    {
        foreach ($payload as $key => $value) {
            $normalizedKey = strtolower((string) preg_replace('/[^a-z0-9]/i', '', (string) $key));

            foreach (['password', 'passwd', 'secret', 'token', 'authorization', 'apikey', 'privatekey', 'credential'] as $sensitive) {
                if (str_contains($normalizedKey, $sensitive)) {
                    throw new InvalidArgumentException('Outbox payload contains a sensitive field that must not be persisted.');
                }
            }

            if (is_array($value)) {
                self::assertNoSensitiveKeys($value);
            }
        }
    }

    private static function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map(self::normalize(...), $value);
            }

            ksort($value, SORT_STRING);

            foreach ($value as $key => $item) {
                if (! is_string($key)) {
                    throw new InvalidArgumentException('Outbox object keys must be strings.');
                }

                $value[$key] = self::normalize($item);
            }

            return $value;
        }

        if ($value === null || is_scalar($value)) {
            return $value;
        }

        throw new InvalidArgumentException('Outbox payload must contain JSON-compatible values.');
    }
}
