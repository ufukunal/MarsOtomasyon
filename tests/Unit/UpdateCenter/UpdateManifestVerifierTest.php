<?php

namespace Tests\Unit\UpdateCenter;

use App\Modules\UpdateCenter\UpdateManifestVerifier;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UpdateManifestVerifierTest extends TestCase
{
    private string $publicKeyPath;

    private string $privateKey;

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        self::assertNotFalse($key);

        $privateKey = '';
        self::assertTrue(openssl_pkey_export($key, $privateKey));
        $this->privateKey = $privateKey;

        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        self::assertArrayHasKey('key', $details);

        $this->publicKeyPath = sys_get_temp_dir().'/mars-update-public-'.bin2hex(random_bytes(8)).'.pem';
        self::assertNotFalse(file_put_contents($this->publicKeyPath, $details['key']));
    }

    protected function tearDown(): void
    {
        if (isset($this->publicKeyPath) && is_file($this->publicKeyPath)) {
            unlink($this->publicKeyPath);
        }

        parent::tearDown();
    }

    public function test_it_accepts_a_valid_signed_manifest(): void
    {
        $manifest = $this->signedManifest();

        $verified = (new UpdateManifestVerifier)->verify($manifest, $this->publicKeyPath);

        self::assertSame('1.5.0', $verified->version);
        self::assertSame('stable', $verified->channel);
        self::assertSame(str_repeat('a', 64), $verified->packageSha256);
    }

    public function test_it_rejects_manifest_tampering_after_signature(): void
    {
        $manifest = $this->signedManifest();
        $manifest['version'] = '9.9.9';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('signature verification failed');

        (new UpdateManifestVerifier)->verify($manifest, $this->publicKeyPath);
    }

    public function test_it_rejects_unknown_manifest_fields(): void
    {
        $manifest = $this->signedManifest();
        $manifest['post_install_command'] = 'php artisan anything';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unsupported fields');

        (new UpdateManifestVerifier)->verify($manifest, $this->publicKeyPath);
    }

    public function test_it_rejects_non_https_package_urls(): void
    {
        $manifest = $this->signedManifest(['package_url' => 'http://updates.example.test/mars-1.5.0.tar.gz']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must use HTTPS');

        (new UpdateManifestVerifier)->verify($manifest, $this->publicKeyPath);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function signedManifest(array $overrides = []): array
    {
        $payload = array_replace([
            'schema' => 1,
            'version' => '1.5.0',
            'channel' => 'stable',
            'package_url' => 'https://updates.example.test/releases/mars-1.5.0.tar.gz',
            'package_sha256' => str_repeat('a', 64),
            'min_php' => '8.5.0',
            'min_app_version' => '1.0.0',
            'released_at' => '2026-09-07T00:00:00Z',
            'release_notes_url' => 'https://updates.example.test/releases/1.5.0',
        ], $overrides);

        $signature = '';
        self::assertTrue(openssl_sign($this->canonicalJson($payload), $signature, $this->privateKey, OPENSSL_ALGO_SHA256));

        return [...$payload, 'signature' => base64_encode($signature)];
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
}
