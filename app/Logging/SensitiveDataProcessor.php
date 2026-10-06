<?php

namespace App\Logging;

use Monolog\LogRecord;

class SensitiveDataProcessor
{
    private const SENSITIVE_KEY_PARTS = [
        'password', 'secret', 'token', 'credential', 'authorization', 'api_key', 'apikey',
        'access_key', 'private_key', 'encryption_key', 'cookie', 'session',
        'national_id', 'tc_kimlik_no', 'phone', 'email', 'address',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->sanitizeString($record->message),
            context: $this->sanitize($record->context),
            extra: $this->sanitize($record->extra),
        );
    }

    /**
     * @param  array<array-key,mixed>  $data
     * @return array<array-key,mixed>
     */
    private function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            $normalized = strtolower((string) $key);

            if ($this->isSensitiveKey($normalized)) {
                $data[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->sanitize($value);

                continue;
            }

            if (is_string($value)) {
                $data[$key] = $this->sanitizeString($value);
            }
        }

        return $data;
    }

    private function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEY_PARTS as $part) {
            if ($key === $part || str_contains($key, $part)) {
                return true;
            }
        }

        return false;
    }

    private function sanitizeString(string $value): string
    {
        $value = preg_replace(
            '/(authorization\s*[:=]\s*)([^\s,;]+)/i',
            '$1[REDACTED]',
            $value,
        ) ?? $value;

        $value = preg_replace(
            '/((?:password|secret|token|api[_-]?key|access[_-]?key|consumer[_-]?secret)\s*[:=]\s*)([^\s,;]+)/i',
            '$1[REDACTED]',
            $value,
        ) ?? $value;

        $value = preg_replace(
            '/("?(?:password|secret|token|api_key|access_token|refresh_token|consumer_secret)"?\s*:\s*")[^"]*(")/i',
            '$1[REDACTED]$2',
            $value,
        ) ?? $value;

        return $value;
    }
}
