<?php

namespace App\Modules\UpdateCenter;

final readonly class UpdateManifest
{
    public function __construct(
        public int $schema,
        public string $version,
        public string $channel,
        public string $packageUrl,
        public string $packageSha256,
        public string $minPhp,
        public ?string $minAppVersion,
        public string $releasedAt,
        public ?string $releaseNotesUrl,
    ) {}

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'schema' => $this->schema,
            'version' => $this->version,
            'channel' => $this->channel,
            'package_url' => $this->packageUrl,
            'package_sha256' => $this->packageSha256,
            'min_php' => $this->minPhp,
            'min_app_version' => $this->minAppVersion,
            'released_at' => $this->releasedAt,
            'release_notes_url' => $this->releaseNotesUrl,
        ];
    }
}
