<?php

namespace App\Foundation\Operations;

use Illuminate\Encryption\Encrypter;
use RuntimeException;

final class BackupRecoveryCipher
{
    private const CIPHER = 'AES-256-CBC';

    public function encryptString(string $value): string
    {
        return $this->encrypter()->encryptString($value);
    }

    public function decryptString(string $payload): string
    {
        return $this->encrypter()->decryptString($payload);
    }

    public function configured(): bool
    {
        try {
            return $this->recoveryKey(false) !== null;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function sharesApplicationKey(): bool
    {
        $recovery = $this->recoveryKey(false);
        if ($recovery === null) {
            return false;
        }

        $application = $this->normalizeKey((string) config('app.key', ''), false);

        return $application !== null && hash_equals($application, $recovery);
    }

    private function encrypter(): Encrypter
    {
        $key = $this->recoveryKey();
        if ($key === null) {
            throw new RuntimeException('Backup recovery encryption key is not configured.');
        }

        return new Encrypter($key, self::CIPHER);
    }

    private function recoveryKey(bool $required = true): ?string
    {
        return $this->normalizeKey((string) config('production.backup.recovery_key', ''), $required);
    }

    private function normalizeKey(string $configured, bool $required): ?string
    {
        $configured = trim($configured);
        if ($configured === '') {
            if ($required) {
                throw new RuntimeException('Backup recovery encryption key is not configured.');
            }

            return null;
        }

        $key = $configured;
        if (str_starts_with($configured, 'base64:')) {
            $decoded = base64_decode(substr($configured, 7), true);
            if (! is_string($decoded)) {
                throw new RuntimeException('Backup recovery encryption key is not valid base64.');
            }
            $key = $decoded;
        }

        if (strlen($key) !== 32) {
            throw new RuntimeException('Backup recovery encryption key must contain exactly 32 bytes for AES-256-CBC.');
        }

        return $key;
    }
}
