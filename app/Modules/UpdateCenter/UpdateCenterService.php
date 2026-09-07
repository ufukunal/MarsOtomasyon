<?php

namespace App\Modules\UpdateCenter;

use Illuminate\Http\Client\Factory as HttpFactory;
use InvalidArgumentException;

final class UpdateCenterService
{
    private const ALLOWED_CHANNELS = ['stable', 'beta', 'development'];

    public function __construct(
        private readonly HttpFactory $http,
        private readonly UpdateManifestVerifier $verifier,
        private readonly UpdateUrlPolicy $urlPolicy,
    ) {}

    /** @return array<string, bool|int|string|null> */
    public function status(): array
    {
        $manifestUrl = trim((string) config('update-center.manifest_url', ''));
        $publicKeyPath = trim((string) config('update-center.public_key_path', ''));
        $allowedHosts = config('update-center.allowed_hosts', []);

        return [
            'current_version' => (string) config('update-center.current_version', '0.0.0-dev'),
            'channel' => (string) config('update-center.channel', 'stable'),
            'manifest_configured' => $manifestUrl !== '',
            'public_key_configured' => $publicKeyPath !== '',
            'allowed_hosts_configured' => is_array($allowedHosts) && $allowedHosts !== [],
            'install_enabled' => false,
        ];
    }

    /** @return array<string, bool|int|string|null> */
    public function check(): array
    {
        $manifestUrl = trim((string) config('update-center.manifest_url', ''));
        $publicKeyPath = trim((string) config('update-center.public_key_path', ''));
        $allowedHosts = config('update-center.allowed_hosts', []);
        $timeout = max(1, min(30, (int) config('update-center.timeout', 10)));
        $currentVersion = trim((string) config('update-center.current_version', '0.0.0-dev'));
        $channel = trim((string) config('update-center.channel', 'stable'));

        if ($manifestUrl === '' || $publicKeyPath === '' || ! is_array($allowedHosts) || $allowedHosts === []) {
            throw new InvalidArgumentException('Update Center is not configured.');
        }

        SemanticVersion::assertValid($currentVersion, 'configured current version');

        if (! in_array($channel, self::ALLOWED_CHANNELS, true)) {
            throw new InvalidArgumentException('Update Center configured channel is invalid.');
        }

        $this->urlPolicy->assertAllowedHttpsUrl($manifestUrl, 'manifest_url', $allowedHosts);

        $response = $this->http
            ->connectTimeout(min(5, $timeout))
            ->timeout($timeout)
            ->withOptions(['allow_redirects' => false])
            ->acceptJson()
            ->get($manifestUrl)
            ->throw();

        $payload = $response->json();
        if (! is_array($payload) || array_is_list($payload)) {
            throw new InvalidArgumentException('Update manifest response must be a JSON object.');
        }

        /** @var array<string, mixed> $payload */
        $manifest = $this->verifier->verify($payload, $publicKeyPath, $allowedHosts);

        if ($manifest->channel !== $channel) {
            throw new InvalidArgumentException('Update manifest channel does not match the configured channel.');
        }

        $phpCompatible = version_compare(PHP_VERSION, $manifest->minPhp, '>=');
        $appCompatible = $manifest->minAppVersion === null
            || SemanticVersion::compare($currentVersion, $manifest->minAppVersion) >= 0;

        return [
            ...$manifest->toArray(),
            'current_version' => $currentVersion,
            'update_available' => SemanticVersion::compare($manifest->version, $currentVersion) > 0,
            'php_compatible' => $phpCompatible,
            'app_compatible' => $appCompatible,
            'compatible' => $phpCompatible && $appCompatible,
            'install_enabled' => false,
        ];
    }
}
