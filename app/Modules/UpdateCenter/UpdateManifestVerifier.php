<?php

namespace App\Modules\UpdateCenter;

use InvalidArgumentException;
use RuntimeException;

final class UpdateManifestVerifier
{
    private const SCHEMA = 1;

    private const ALLOWED_CHANNELS = ['stable', 'beta', 'development'];

    private const ALLOWED_KEYS = [
        'schema',
        'version',
        'channel',
        'package_url',
        'package_sha256',
        'min_php',
        'min_app_version',
        'released_at',
        'release_notes_url',
        'signature',
    ];

    /** @param array<string, mixed> $payload */
    public function verify(array $payload, string $publicKeyPath): UpdateManifest
    {
        $this->assertPayloadShape($payload);
        $this->assertPublicKeyPath($publicKeyPath);

        $signature = base64_decode((string) $payload['signature'], true);
        if ($signature === false || $signature === '') {
            throw new InvalidArgumentException('Update manifest signature is not valid base64.');
        }

        $publicKeyContents = file_get_contents($publicKeyPath);
        if ($publicKeyContents === false) {
            throw new RuntimeException('Update manifest public key could not be read.');
        }

        $publicKey = openssl_pkey_get_public($publicKeyContents);
        if ($publicKey === false) {
            throw new RuntimeException('Update manifest public key is invalid.');
        }

        $signedPayload = $payload;
        unset($signedPayload['signature']);

        $verified = openssl_verify(
            $this->canonicalJson($signedPayload),
            $signature,
            $publicKey,
            OPENSSL_ALGO_SHA256,
        );

        if ($verified !== 1) {
            throw new InvalidArgumentException('Update manifest signature verification failed.');
        }

        return new UpdateManifest(
            schema: (int) $payload['schema'],
            version: (string) $payload['version'],
            channel: (string) $payload['channel'],
            packageUrl: (string) $payload['package_url'],
            packageSha256: strtolower((string) $payload['package_sha256']),
            minPhp: (string) $payload['min_php'],
            minAppVersion: $this->nullableString($payload['min_app_version']),
            releasedAt: (string) $payload['released_at'],
            releaseNotesUrl: $this->nullableString($payload['release_notes_url']),
        );
    }

    /** @param array<string, mixed> $payload */
    private function assertPayloadShape(array $payload): void
    {
        $unknown = array_diff(array_keys($payload), self::ALLOWED_KEYS);
        if ($unknown !== []) {
            throw new InvalidArgumentException('Update manifest contains unsupported fields.');
        }

        foreach (self::ALLOWED_KEYS as $key) {
            if (! array_key_exists($key, $payload)) {
                throw new InvalidArgumentException("Update manifest field [{$key}] is missing.");
            }
        }

        if ($payload['schema'] !== self::SCHEMA) {
            throw new InvalidArgumentException('Update manifest schema is not supported.');
        }

        foreach (['version', 'channel', 'package_url', 'package_sha256', 'min_php', 'released_at', 'signature'] as $key) {
            if (! is_string($payload[$key]) || trim($payload[$key]) === '') {
                throw new InvalidArgumentException("Update manifest field [{$key}] is invalid.");
            }
        }

        foreach (['min_app_version', 'release_notes_url'] as $key) {
            if ($payload[$key] !== null && ! is_string($payload[$key])) {
                throw new InvalidArgumentException("Update manifest field [{$key}] is invalid.");
            }
        }

        if (! preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/', (string) $payload['version'])) {
            throw new InvalidArgumentException('Update manifest version is invalid.');
        }

        if (! in_array($payload['channel'], self::ALLOWED_CHANNELS, true)) {
            throw new InvalidArgumentException('Update manifest channel is invalid.');
        }

        $this->assertHttpsUrl((string) $payload['package_url'], 'package_url');

        if ($payload['release_notes_url'] !== null && $payload['release_notes_url'] !== '') {
            $this->assertHttpsUrl((string) $payload['release_notes_url'], 'release_notes_url');
        }

        if (! preg_match('/^[a-fA-F0-9]{64}$/', (string) $payload['package_sha256'])) {
            throw new InvalidArgumentException('Update manifest package_sha256 is invalid.');
        }

        if (! preg_match('/^\d+\.\d+(?:\.\d+)?$/', (string) $payload['min_php'])) {
            throw new InvalidArgumentException('Update manifest min_php is invalid.');
        }

        if ($payload['min_app_version'] !== null && $payload['min_app_version'] !== ''
            && ! preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/', (string) $payload['min_app_version'])) {
            throw new InvalidArgumentException('Update manifest min_app_version is invalid.');
        }

        if (strtotime((string) $payload['released_at']) === false) {
            throw new InvalidArgumentException('Update manifest released_at is invalid.');
        }
    }

    private function assertPublicKeyPath(string $publicKeyPath): void
    {
        if ($publicKeyPath === '' || ! str_starts_with($publicKeyPath, DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('Update manifest public key path must be absolute.');
        }

        if (! is_file($publicKeyPath) || ! is_readable($publicKeyPath)) {
            throw new InvalidArgumentException('Update manifest public key is not readable.');
        }
    }

    private function assertHttpsUrl(string $url, string $field): void
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw new InvalidArgumentException("Update manifest field [{$field}] must use HTTPS.");
        }
    }

    /** @param array<string, mixed> $payload */
    private function canonicalJson(array $payload): string
    {
        $this->sortRecursively($payload);

        return json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /** @param array<string, mixed> $value */
    private function sortRecursively(array &$value): void
    {
        ksort($value, SORT_STRING);

        foreach ($value as &$item) {
            if (is_array($item)) {
                $this->sortRecursively($item);
            }
        }
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
