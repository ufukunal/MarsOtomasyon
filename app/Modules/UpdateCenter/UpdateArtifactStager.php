<?php

namespace App\Modules\UpdateCenter;

use DomainException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\File;
use RuntimeException;
use stdClass;
use ZipArchive;

final class UpdateArtifactStager
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly UpdateUrlPolicy $urlPolicy,
        private readonly UpdateRunStore $runs,
    ) {}

    public function stage(int $runId): stdClass
    {
        $run = $this->runs->find($runId);
        if ((string) $run->status !== UpdateRunState::Verified->value) {
            throw new DomainException('Only a verified update run can be staged.');
        }

        $metadata = $this->metadata($run);
        $packageUrl = trim((string) ($metadata['package_url'] ?? ''));
        $allowedHosts = config('update-center.allowed_hosts', []);
        if (! is_array($allowedHosts) || $allowedHosts === []) {
            throw new RuntimeException('Update host allowlist is not configured.');
        }
        $this->urlPolicy->assertAllowedHttpsUrl($packageUrl, 'package_url', $allowedHosts);

        $root = storage_path('app/update-center/'.$runId);
        $archive = $root.'/package.zip';
        $release = $root.'/release';
        File::deleteDirectory($root);
        File::ensureDirectoryExists($release, 0750, true);

        try {
            $bytes = $this->download($packageUrl, $archive);
            $actualHash = hash_file('sha256', $archive);
            if (! is_string($actualHash) || ! hash_equals(strtolower((string) $run->package_sha256), strtolower($actualHash))) {
                throw new RuntimeException('Update package SHA-256 verification failed.');
            }

            $this->extractSafeZip($archive, $release);
            foreach (['artisan', 'composer.json', 'Dockerfile.production', 'docker-compose.production.yml'] as $required) {
                if (! is_file($release.'/'.$required)) {
                    throw new RuntimeException('Update package is missing required release files.');
                }
            }

            return $this->runs->transition($runId, UpdateRunState::Staged, [
                'staged_at' => now()->toIso8601String(),
                'stage_path' => $release,
                'artifact_size_bytes' => $bytes,
            ]);
        } catch (\Throwable $exception) {
            File::deleteDirectory($root);
            try {
                $this->runs->fail($runId, 'stage_failed', 'Artifact staging failed security or integrity validation.');
            } catch (\Throwable) {
                // Preserve the original staging error; lifecycle may already be terminal.
            }
            throw $exception;
        }
    }

    private function download(string $url, string $destination): int
    {
        $timeout = max(1, min(30, (int) config('update-center.timeout', 10)));
        $maximum = max(1048576, (int) config('update-center.max_artifact_bytes', 1073741824));
        $response = $this->http
            ->connectTimeout(min(5, $timeout))
            ->timeout($timeout)
            ->withOptions(['allow_redirects' => false, 'stream' => true])
            ->get($url)
            ->throw();

        $declaredLength = (int) ($response->header('Content-Length') ?? 0);
        if ($declaredLength > $maximum) {
            throw new RuntimeException('Update package exceeds the configured size limit.');
        }

        $input = $response->toPsrResponse()->getBody();
        $output = fopen($destination, 'wb');
        if ($output === false) {
            throw new RuntimeException('Update staging file could not be created.');
        }

        $total = 0;
        try {
            while (! $input->eof()) {
                $chunk = $input->read(1024 * 1024);
                $total += strlen($chunk);
                if ($total > $maximum) {
                    throw new RuntimeException('Update package exceeds the configured size limit.');
                }
                if ($chunk !== '' && fwrite($output, $chunk) !== strlen($chunk)) {
                    throw new RuntimeException('Update package could not be written completely.');
                }
            }
        } finally {
            fclose($output);
        }

        if ($total === 0) {
            throw new RuntimeException('Update package is empty.');
        }

        return $total;
    }

    private function extractSafeZip(string $archive, string $release): void
    {
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Update package is not a readable ZIP archive.');
        }

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $raw = $zip->getNameIndex($index);
                if (! is_string($raw) || $raw === '' || str_contains($raw, "\0")) {
                    throw new RuntimeException('Update package contains an invalid archive path.');
                }

                $name = str_replace('\\', '/', $raw);
                if (str_starts_with($name, '/') || preg_match('/^[A-Za-z]:\//', $name) === 1) {
                    throw new RuntimeException('Update package contains an absolute archive path.');
                }
                $segments = array_values(array_filter(explode('/', rtrim($name, '/')), static fn (string $part): bool => $part !== ''));
                if ($segments === [] || in_array('..', $segments, true)) {
                    throw new RuntimeException('Update package contains path traversal.');
                }

                $opsys = 0;
                $attributes = 0;
                if ($zip->getExternalAttributesIndex($index, $opsys, $attributes)) {
                    $type = ($attributes >> 16) & 0xF000;
                    if ($type === 0xA000) {
                        throw new RuntimeException('Update package contains an unsafe symbolic link.');
                    }
                }

                $target = $release.'/'.implode('/', $segments);
                if (str_ends_with($name, '/')) {
                    File::ensureDirectoryExists($target, 0750, true);
                    continue;
                }

                File::ensureDirectoryExists(dirname($target), 0750, true);
                $stream = $zip->getStream($raw);
                if (! is_resource($stream)) {
                    throw new RuntimeException('Update package entry could not be read.');
                }
                $output = fopen($target, 'wb');
                if ($output === false) {
                    fclose($stream);
                    throw new RuntimeException('Update package entry could not be created.');
                }
                stream_copy_to_stream($stream, $output);
                fclose($stream);
                fclose($output);
                chmod($target, 0640);
            }
        } finally {
            $zip->close();
        }
    }

    /** @return array<string, mixed> */
    private function metadata(stdClass $run): array
    {
        $value = json_decode((string) ($run->metadata ?? '{}'), true, flags: JSON_THROW_ON_ERROR);

        return is_array($value) ? $value : [];
    }
}
