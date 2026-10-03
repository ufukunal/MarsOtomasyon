<?php

namespace App\Logging;

use Monolog\LogRecord;

class SensitiveDataProcessor
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'session',
        'session_id',
        'api_key',
        'apikey',
        'authorization',
        'national_id',
        'tc_kimlik_no',
        'phone',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            context: $this->sanitize($record->context),
            extra: $this->sanitize($record->extra),
        );
    }

    /**
     * @param array<string|int, mixed> $data
     * @return array<string|int, mixed>
     */
    private function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
                $data[$key] = $this->mask($normalizedKey, $value);

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->sanitize($value);
            }
        }

        return $data;
    }

    private function mask(string $key, mixed $value): string
    {
        if ($key === 'national_id' || $key === 'tc_kimlik_no') {
            $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

            if (strlen($digits) >= 5) {
                return substr($digits, 0, 3).'*****'.substr($digits, -2);
            }
        }

        if ($key === 'phone') {
            $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

            if (strlen($digits) >= 4) {
                return '***'.substr($digits, -4);
            }
        }

        return '[REDACTED]';
    }
}
