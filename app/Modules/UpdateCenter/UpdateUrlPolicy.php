<?php

namespace App\Modules\UpdateCenter;

use InvalidArgumentException;

final class UpdateUrlPolicy
{
    /** @param array<int, mixed> $allowedHosts */
    public function assertAllowedHttpsUrl(string $url, string $field, array $allowedHosts): void
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException("Update Center {$field} is not a valid URL.");
        }

        $parts = parse_url($url);
        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            throw new InvalidArgumentException("Update Center {$field} must use HTTPS.");
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException("Update Center {$field} must not contain URL credentials.");
        }

        if (isset($parts['fragment'])) {
            throw new InvalidArgumentException("Update Center {$field} must not contain a fragment.");
        }

        if (isset($parts['port']) && $parts['port'] !== 443) {
            throw new InvalidArgumentException("Update Center {$field} must use the default HTTPS port.");
        }

        $host = $parts['host'] ?? null;
        if (! is_string($host) || $host === '') {
            throw new InvalidArgumentException("Update Center {$field} host is missing.");
        }

        $normalizedAllowedHosts = $this->normalizeAllowedHosts($allowedHosts);
        if ($normalizedAllowedHosts === []) {
            throw new InvalidArgumentException('Update Center allowed host list is not configured.');
        }

        $normalizedHost = strtolower(rtrim($host, '.'));
        if (! in_array($normalizedHost, $normalizedAllowedHosts, true)) {
            throw new InvalidArgumentException("Update Center {$field} host is not allowlisted.");
        }
    }

    /**
     * @param  array<int, mixed>  $allowedHosts
     * @return list<string>
     */
    private function normalizeAllowedHosts(array $allowedHosts): array
    {
        $normalized = [];

        foreach ($allowedHosts as $host) {
            if (! is_string($host)) {
                throw new InvalidArgumentException('Update Center allowed host list is invalid.');
            }

            $host = strtolower(rtrim(trim($host), '.'));
            if ($host === '' || str_contains($host, '://') || str_contains($host, '/') || str_contains($host, '@') || str_contains($host, '*')) {
                throw new InvalidArgumentException('Update Center allowed host list is invalid.');
            }

            $validIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
            $validDomain = filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
            if (! $validIp && ! $validDomain) {
                throw new InvalidArgumentException('Update Center allowed host list is invalid.');
            }

            $normalized[] = $host;
        }

        return array_values(array_unique($normalized));
    }
}
