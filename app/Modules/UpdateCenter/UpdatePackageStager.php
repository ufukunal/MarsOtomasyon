<?php

namespace App\Modules\UpdateCenter;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;
use Throwable;
use ZipArchive;

final class UpdatePackageStager
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly Filesystem $files,
        private readonly UpdateUrlPolicy $urlPolicy,
        private readonly UpdateRunStore $runs,
    ) {
        // Dependencies are constructor-promoted and intentionally immutable.
    }

    /**
     * @param array<string, bool|int|string|null> $release
     */
    public function stage(array $release, ?int $userId = null): int
    {
        $version = (string) ($release['version'] ?? '');
        $channel = (string) ($release['channel'] ?? '');
        $packageUrl = (string) ($release['package_url'] ?? '');
        $packageSha = strtolower((string) ($release['package_sha256'] ?? ''));
        $manifestSha = strtolower((string) ($release['manifest_sha256'] ?? ''));

        if (($release['update_available'] ?? false) !== true || ($release['compatible'] ?? false) !== true) {
            throw new RuntimeException('Release is not eligible for staging.');
        }

        $allowedHosts = config('update-center.allowed_hosts', []);
        if (! is_array($allowedHosts) || $allowedHosts === []) {
            throw new RuntimeException('Update host allowlist is not configured.');
        }
        $this->urlPolicy->assertAllowedHttpsUrl($packageUrl, 'package_url', $allowedHosts);

        $run = $this->runs->request(
            targetVersion: $version,
            channel: $channel,
            manifestSha256: $manifestSha,
            packageSha256: $packageSha,
            requestedByUserId: $userId,
            metadata: ['release_checked_at' => now()->toIso8601String()],
        );
        $runId = (int) $run->id;

        $this->runs->transition($runId, UpdateRunState::Verified);
        $this->runs->transition($runId, UpdateRunState::Staging);

        $root = $this->stagingRoot();
        $runDirectory = $root.'/runs/'.$runId;
        $packagePath = $runDirectory.'/package.zip';
        $partialPath = $packagePath.'.partial';
        $releasePath = $runDirectory.'/release';

        try {
            $this->files->deleteDirectory($runDirectory);
            $this->files->ensureDirectoryExists($runDirectory, 0700, true);

            $this->download($packageUrl, $partialPath);
            $actualSize = $this->files->size($partialPath);
            $maxPackageBytes = max(1, (int) config('update-center.max_package_bytes', 268435456));
            if ($actualSize < 1 || $actualSize > $maxPackageBytes) {
                throw new RuntimeException('Update package size is outside the configured limit.');
            }

            $actualSha = hash_file('sha256', $partialPath);
            if (! is_string($actualSha) || ! hash_equals($packageSha, strtolower($actualSha))) {
                throw new RuntimeException('Update package SHA-256 verification failed.');
            }

            if (! $this->files->move($partialPath, $packagePath)) {
                throw new RuntimeException('Verified update package could not be finalized.');
            }

            [$fileCount, $extractedBytes] = $this->extractVerifiedZip($packagePath, $releasePath);
            $this->assertReleaseLayout($releasePath);

            $this->runs->recordArtifact(
                runId: $runId,
                packagePath: $packagePath,
                releasePath: $releasePath,
                packageSha256: $packageSha,
                packageSizeBytes: $actualSize,
                fileCount: $fileCount,
                extractedSizeBytes: $extractedBytes,
                metadata: ['staged_at' => now()->toIso8601String()],
            );

            return $runId;
        } catch (Throwable $exception) {
            $this->files->delete($partialPath);

            try {
                $this->runs->fail($runId, 'staging_failed', $exception->getMessage());
            } catch (Throwable) {
                // Preserve the original staging exception.
            }

            throw $exception;
        }
    }

    private function download(string $url, string $target): void
    {
        $timeout = max(5, min(300, (int) config('update-center.package_timeout', 120)));
        $maxBytes = max(1, (int) config('update-center.max_package_bytes', 268435456));

        $response = $this->http
            ->connectTimeout(min(10, $timeout))
            ->timeout($timeout)
            ->withOptions([
                'allow_redirects' => false,
                'sink' => $target,
                'progress' => static function (int $downloadTotal, int $downloadedBytes) use ($maxBytes): void {
                    if ($downloadTotal > $maxBytes || $downloadedBytes > $maxBytes) {
                        throw new RuntimeException('Update package exceeded the configured download limit.');
                    }
                },
            ])
            ->accept('application/zip, application/octet-stream')
            ->get($url);

        if ($response->redirect()) {
            throw new RuntimeException('Update package redirects are not allowed.');
        }
        $response->throw();
    }

    /** @return array{int,int} */
    private function extractVerifiedZip(string $packagePath, string $releasePath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($packagePath) !== true) {
            throw new RuntimeException('Update package is not a valid ZIP archive.');
        }

        $maxFiles = max(1, (int) config('update-center.max_files', 20000));
        $maxExtractedBytes = max(1, (int) config('update-center.max_extracted_bytes', 1073741824));
        if ($zip->numFiles < 1 || $zip->numFiles > $maxFiles) {
            $zip->close();
            throw new RuntimeException('Update package file count is outside the configured limit.');
        }

        $this->files->deleteDirectory($releasePath);
        $this->files->ensureDirectoryExists($releasePath, 0700, true);
        $totalBytes = 0;
        $regularFiles = 0;

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                if (! is_array($stat) || ! is_string($stat['name'] ?? null)) {
                    throw new RuntimeException('Update package contains an unreadable archive entry.');
                }

                $name = $this->safeArchivePath($stat['name']);
                if ($name === '') {
                    continue;
                }

                $isDirectory = str_ends_with($stat['name'], '/');
                if ($this->isUnsafeUnixEntry($zip, $index, $isDirectory)) {
                    throw new RuntimeException('Update package contains a symlink, hardlink or special file.');
                }

                $target = $releasePath.'/'.$name;
                if ($isDirectory) {
                    $this->files->ensureDirectoryExists($target, 0700, true);
                    continue;
                }

                $size = (int) ($stat['size'] ?? -1);
                if ($size < 0) {
                    throw new RuntimeException('Update package contains an invalid file size.');
                }
                $totalBytes += $size;
                if ($totalBytes > $maxExtractedBytes) {
                    throw new RuntimeException('Update package exceeds the configured extraction size limit.');
                }

                $stream = $zip->getStream($stat['name']);
                if (! is_resource($stream)) {
                    throw new RuntimeException('Update package entry could not be read.');
                }
                $this->files->ensureDirectoryExists(dirname($target), 0700, true);
                $output = fopen($target, 'xb');
                if (! is_resource($output)) {
                    fclose($stream);
                    throw new RuntimeException('Update package entry could not be written.');
                }
                $copied = stream_copy_to_stream($stream, $output, $size + 1);
                fclose($stream);
                fclose($output);
                if ($copied !== $size) {
                    throw new RuntimeException('Update package entry size verification failed.');
                }
                chmod($target, 0600);
                $regularFiles++;
            }
        } finally {
            $zip->close();
        }

        if ($regularFiles < 1) {
            throw new RuntimeException('Update package does not contain release files.');
        }

        return [$regularFiles, $totalBytes];
    }

    private function safeArchivePath(string $raw): string
    {
        if ($raw === '' || str_contains($raw, "\0") || str_contains($raw, '\\')) {
            throw new RuntimeException('Update package contains an unsafe archive path.');
        }
        if (str_starts_with($raw, '/') || preg_match('/^[A-Za-z]:\//D', $raw) === 1) {
            throw new RuntimeException('Update package contains an absolute archive path.');
        }

        $parts = [];
        foreach (explode('/', trim($raw, '/')) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                throw new RuntimeException('Update package contains path traversal.');
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    private function isUnsafeUnixEntry(ZipArchive $zip, int $index, bool $isDirectory): bool
    {
        $opsys = 0;
        $attributes = 0;
        if (! $zip->getExternalAttributesIndex($index, $opsys, $attributes)) {
            return false;
        }
        if ($opsys !== ZipArchive::OPSYS_UNIX) {
            return false;
        }

        $type = ($attributes >> 16) & 0170000;
        if ($type === 0) {
            return false;
        }

        return $isDirectory ? $type !== 0040000 : $type !== 0100000;
    }

    private function assertReleaseLayout(string $releasePath): void
    {
        foreach (['artisan', 'composer.json', 'Dockerfile.production', 'docker-compose.production.yml'] as $required) {
            if (! $this->files->isFile($releasePath.'/'.$required)) {
                throw new RuntimeException("Update package is missing required release file: {$required}.");
            }
        }
    }

    private function stagingRoot(): string
    {
        $root = rtrim(trim((string) config('update-center.staging_root', '')), DIRECTORY_SEPARATOR);
        if ($root === '' || ! str_starts_with($root, DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Update Center staging root must be an absolute path.');
        }

        $this->files->ensureDirectoryExists($root, 0700, true);

        return $root;
    }
}
