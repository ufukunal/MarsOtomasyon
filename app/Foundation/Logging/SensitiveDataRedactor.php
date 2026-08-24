<?php

namespace App\Foundation\Logging;

final class SensitiveDataRedactor
{
    public const REDACTED = '[REDACTED]';

    /** @param array<mixed> $values
     * @return array<mixed>
     */
    public function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $values[$key] = self::REDACTED;

                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->redact($value);

                continue;
            }

            if (is_string($value)) {
                $values[$key] = $this->redactBearerToken($value);
            }
        }

        return $values;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $key));

        foreach ([
            'password', 'passwd', 'secret', 'token', 'authorization', 'apikey', 'privatekey',
            'credential', 'cookie', 'sessionid', 'tckn', 'vkn', 'iban', 'email', 'phone',
            'telephone', 'mobile', 'address', 'birthdate',
        ] as $sensitive) {
            if (str_contains($normalized, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    private function redactBearerToken(string $value): string
    {
        return (string) preg_replace(
            '/\bBearer\s+[A-Za-z0-9._~+\/=:-]+/i',
            'Bearer '.self::REDACTED,
            $value,
        );
    }
}
