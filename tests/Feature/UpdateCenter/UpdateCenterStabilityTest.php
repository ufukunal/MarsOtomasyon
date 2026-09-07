<?php

namespace Tests\Feature\UpdateCenter;

use App\Modules\UpdateCenter\UpdateCenterService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

final class UpdateCenterStabilityTest extends TestCase
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

        $this->publicKeyPath = sys_get_temp_dir().'/mars-update-stability-public-'.bin2hex(random_bytes(8)).'.pem';
        self::assertNotFalse(file_put_contents($this->publicKeyPath, $details['key']));

        config()->set([
            'update-center.current_version' => '1.4.2',
            'update-center.channel' => 'stable',
            'update-center.manifest_url' => 'https://updates.example.test/stable/manifest.json',
            'update-center.public_key_path' => $this->publicKeyPath,
            'update-center.allowed_hosts' => ['updates.example.test'],
            'update-center.timeout' => 10,
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->publicKeyPath) && is_file($this->publicKeyPath)) {
            unlink($this->publicKeyPath);
        }

        parent::tearDown();
    }

    public function test_server_error_fails_closed(): void
    {
        Http::fake([
            'https://updates.example.test/stable/manifest.json' => Http::response(['error' => 'unavailable'], 503),
        ]);

        $this->expectException(RequestException::class);

        app(UpdateCenterService::class)->check();
    }

    public function test_transport_failure_fails_closed(): void
    {
        Http::fake([
            'https://updates.example.test/stable/manifest.json' => Http::failedConnection(),
        ]);

        $this->expectException(ConnectionException::class);

        app(UpdateCenterService::class)->check();
    }

    public function test_redirect_response_is_not_accepted_as_a_manifest(): void
    {
        Http::fake([
            'https://updates.example.test/stable/manifest.json' => Http::response(
                '',
                302,
                ['Location' => 'https://other.example.test/manifest.json'],
            ),
            '*' => Http::response($this->signedManifest(), 200),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JSON object');

        try {
            app(UpdateCenterService::class)->check();
        } finally {
            Http::assertSentCount(1);
        }
    }

    public function test_manifest_channel_mismatch_fails_closed(): void
    {
        Http::fake([
            'https://updates.example.test/stable/manifest.json' => Http::response(
                $this->signedManifest(['channel' => 'beta']),
                200,
            ),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('channel does not match');

        app(UpdateCenterService::class)->check();
    }

    public function test_incompatible_php_requirement_is_reported_without_enabling_install(): void
    {
        Http::fake([
            'https://updates.example.test/stable/manifest.json' => Http::response(
                $this->signedManifest(['min_php' => '99.0.0']),
                200,
            ),
        ]);

        $result = app(UpdateCenterService::class)->check();

        self::assertFalse($result['php_compatible']);
        self::assertTrue($result['app_compatible']);
        self::assertFalse($result['compatible']);
        self::assertFalse($result['install_enabled']);
    }

    public function test_incompatible_app_requirement_is_reported_without_enabling_install(): void
    {
        Http::fake([
            'https://updates.example.test/stable/manifest.json' => Http::response(
                $this->signedManifest(['min_app_version' => '2.0.0']),
                200,
            ),
        ]);

        $result = app(UpdateCenterService::class)->check();

        self::assertTrue($result['php_compatible']);
        self::assertFalse($result['app_compatible']);
        self::assertFalse($result['compatible']);
        self::assertFalse($result['install_enabled']);
    }

    public function test_invalid_current_version_fails_before_network_io(): void
    {
        config()->set('update-center.current_version', '1.4');
        Http::fake();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('semantic versioning');

        try {
            app(UpdateCenterService::class)->check();
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_invalid_configured_channel_fails_before_network_io(): void
    {
        config()->set('update-center.channel', 'nightly');
        Http::fake();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('configured channel is invalid');

        try {
            app(UpdateCenterService::class)->check();
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_missing_public_key_fails_closed(): void
    {
        config()->set('update-center.public_key_path', $this->publicKeyPath.'.missing');
        Http::fake([
            'https://updates.example.test/stable/manifest.json' => Http::response($this->signedManifest(), 200),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('public key is not readable');

        app(UpdateCenterService::class)->check();
    }

    public function test_invalid_public_key_fails_closed(): void
    {
        self::assertNotFalse(file_put_contents($this->publicKeyPath, 'not a public key'));
        Http::fake([
            'https://updates.example.test/stable/manifest.json' => Http::response($this->signedManifest(), 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('public key is invalid');

        app(UpdateCenterService::class)->check();
    }

    public function test_malformed_signature_fails_closed(): void
    {
        $manifest = $this->signedManifest();
        $manifest['signature'] = '%%%not-base64%%%';

        Http::fake([
            'https://updates.example.test/stable/manifest.json' => Http::response($manifest, 200),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('signature is not valid base64');

        app(UpdateCenterService::class)->check();
    }

    public function test_repeated_checks_are_deterministic_and_remain_check_only(): void
    {
        $manifest = $this->signedManifest();
        Http::fake([
            'https://updates.example.test/stable/manifest.json' => Http::response($manifest, 200),
        ]);

        $first = app(UpdateCenterService::class)->check();
        $second = app(UpdateCenterService::class)->check();

        self::assertSame($first, $second);
        self::assertTrue($first['update_available']);
        self::assertTrue($first['compatible']);
        self::assertFalse($first['install_enabled']);
        Http::assertSentCount(2);
    }

    public function test_matching_beta_channel_is_accepted_without_enabling_install(): void
    {
        config()->set('update-center.channel', 'beta');
        Http::fake([
            'https://updates.example.test/stable/manifest.json' => Http::response(
                $this->signedManifest(['channel' => 'beta', 'version' => '1.5.0-beta.2']),
                200,
            ),
        ]);

        $result = app(UpdateCenterService::class)->check();

        self::assertSame('beta', $result['channel']);
        self::assertTrue($result['update_available']);
        self::assertFalse($result['install_enabled']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function signedManifest(array $overrides = []): array
    {
        $payload = array_replace([
            'schema' => 1,
            'version' => '1.5.0',
            'channel' => 'stable',
            'package_url' => 'https://updates.example.test/releases/mars-1.5.0.tar.gz',
            'package_sha256' => str_repeat('c', 64),
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
        ksort($payload, SORT_STRING);

        return json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }
}
