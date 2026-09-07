<?php

namespace App\Modules\UpdateCenter;

use Illuminate\Http\Client\Factory as HttpFactory;
use InvalidArgumentException;

final class UpdateCenterService
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly UpdateManifestVerifier $verifier,
    ) {}

    /** @return array<string, bool|int|string|null> */
    public function status(): array
    {
        $manifestUrl = trim((string) config('update-center.manifest_url', ''));
        $publicKeyPath = trim((string) config('update-center.public_key_path', ''));

        return [
            'current_version' => (string) config('update-center.current_version', '0.0.0-dev'),
            'channel' => (string) config('update-center.channel', 'stable'),
            'manifest_configured' => $manifestUrl !== '',
            'public_key_configured' => $publicKeyPath !== '',
            'install_enabled' => false,
        ];
    }

    /** @return array<string, bool|int|string|null> */
    public function check(): array
    {
        $manifestUrl = trim((string) config('update-center.manifest_url', ''));
        $publicKeyPath = trim((string) config('update-center.public_key_path', ''));
        $timeout = max(1, min(30, (int) config('update-center.timeout', 10)));
        $currentVersion = (string) config('update-center.current_version', '0.0.0-dev');
        $channel = (string) config('update-center.channel', 'stable');

        if ($manifestUrl === '' || $publicKeyPath === '') {
            throw new InvalidArgumentException('Update Center is not configured.');
        }

        if (filter_var($manifestUrl, FILTER_VALIDATE_URL) === false || parse_url($manifestUrl, PHP_URL_SCHEME) !== 'https') {
            throw new InvalidArgumentException('Update manifest URL must use HTTPS.');
        }

        /** @var array<string, mixed> $payload */
        $payload = $this->http
            ->connectTimeout(min(5, $timeout))
            ->timeout($timeout)
            ->withOptions(['allow_redirects' => false])
            ->acceptJson()
            ->get($manifestUrl)
            ->throw()
            ->json();

        $manifest = $this->verifier->verify($payload, $publicKeyPath);

        if ($manifest->channel !== $channel) {
            throw new InvalidArgumentException('Update manifest channel does not match the configured channel.');
        }

        $phpCompatible = version_compare(PHP_VERSION, $manifest->minPhp, '>=');
        $appCompatible = $manifest->minAppVersion === null
            || version_compare($currentVersion, $manifest->minAppVersion, '>=');

        return [
            ...$manifest->toArray(),
            'current_version' => $currentVersion,
            'update_available' => version_compare($manifest->version, $currentVersion, '>'),
            'php_compatible' => $phpCompatible,
            'app_compatible' => $appCompatible,
            'compatible' => $phpCompatible && $appCompatible,
            'install_enabled' => false,
        ];
    }
}
