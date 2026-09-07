<?php

namespace Tests\Feature\UpdateCenter;

use App\Modules\UpdateCenter\UpdateCenterService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class UpdateCenterServiceTest extends TestCase
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

        $this->publicKeyPath = sys_get_temp_dir().'/mars-update-service-public-'.bin2hex(random_bytes(8)).'.pem';
        self::assertNotFalse(file_put_contents($this->publicKeyPath, $details['key']));

        config()->set([
            'update-center.current_version' => '1.4.2',
            'update-center.channel' => 'stable',
            'update-center.manifest_url' => 'https://updates.example.test/stable/manifest.json',
            'update-center.public_key_path' => $this->publicKeyPath,
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

    public function test_check_only_fetches_and_verifies_the_manifest(): void
    {
        Http::fake([
            'https://updates.example.test/stable/manifest.json' => Http::response($this->signedManifest(), 200),
            '*' => Http::response([], 500),
        ]);

        $result = app(UpdateCenterService::class)->check();

        self::assertTrue($result['update_available']);
        self::assertTrue($result['compatible']);
        self::assertFalse($result['install_enabled']);
        self::assertSame('1.5.0', $result['version']);
        self::assertSame('1.4.2', $result['current_version']);

        Http::assertSentCount(1);
        Http::assertSent(static fn (Request $request): bool => $request->url() === 'https://updates.example.test/stable/manifest.json');
    }

    /** @return array<string, mixed> */
    private function signedManifest(): array
    {
        $payload = [
            'schema' => 1,
            'version' => '1.5.0',
            'channel' => 'stable',
            'package_url' => 'https://updates.example.test/releases/mars-1.5.0.tar.gz',
            'package_sha256' => str_repeat('b', 64),
            'min_php' => '8.5.0',
            'min_app_version' => '1.0.0',
            'released_at' => '2026-09-07T00:00:00Z',
            'release_notes_url' => 'https://updates.example.test/releases/1.5.0',
        ];

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
