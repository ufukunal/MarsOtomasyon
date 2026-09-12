<?php

namespace App\Modules\UpdateCenter;

use App\Modules\Operations\OperationsHealth;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

final class UpdateCenterService
{
    private const ALLOWED_CHANNELS = ['stable', 'beta', 'development'];

    public function __construct(
        private readonly HttpFactory $http,
        private readonly UpdateManifestVerifier $verifier,
        private readonly UpdateUrlPolicy $urlPolicy,
        private readonly OperationsHealth $health,
    ) {}

    /** @return array<string, mixed> */
    public function status(): array
    {
        $manifestUrl = trim((string) config('update-center.manifest_url', ''));
        $publicKeyPath = trim((string) config('update-center.public_key_path', ''));
        $allowedHosts = config('update-center.allowed_hosts', []);
        $readiness = $this->readiness();

        return [
            'current_version' => (string) config('update-center.current_version', '0.0.0-dev'),
            'current_build' => (string) config('update-center.current_build', 'unknown'),
            'channel' => (string) config('update-center.channel', 'stable'),
            'manifest_url' => $manifestUrl,
            'manifest_configured' => $manifestUrl !== '',
            'public_key_configured' => $publicKeyPath !== '',
            'allowed_hosts_configured' => is_array($allowedHosts) && $allowedHosts !== [],
            'install_enabled' => true,
            ...$readiness,
        ];
    }

    /** @return array<string, mixed> */
    public function readiness(): array
    {
        $operations = $this->health->snapshot();
        $freeBytes = @disk_free_space(storage_path());
        $minimumFree = max(268435456, (int) config('update-center.min_free_bytes', 1073741824));
        $diskReady = is_float($freeBytes) && $freeBytes >= $minimumFree;
        $backup = $this->backupReadiness();
        $systemReady = (bool) ($operations['database_ok'] ?? false)
            && (bool) ($operations['valkey_ok'] ?? false)
            && (bool) ($operations['worker_alive'] ?? false)
            && (bool) ($operations['scheduler_alive'] ?? false)
            && $diskReady;

        return [
            'database_ready' => (bool) ($operations['database_ok'] ?? false),
            'valkey_ready' => (bool) ($operations['valkey_ok'] ?? false),
            'worker_ready' => (bool) ($operations['worker_alive'] ?? false),
            'scheduler_ready' => (bool) ($operations['scheduler_alive'] ?? false),
            'disk_ready' => $diskReady,
            'disk_free_bytes' => is_float($freeBytes) ? (int) $freeBytes : null,
            'backup_ready' => $backup['ready'],
            'backup_reason' => $backup['reason'],
            'backup_offsite_required' => $backup['offsite_required'],
            'system_ready' => $systemReady,
            'apply_ready' => $systemReady && $backup['ready'],
        ];
    }

    /** @return array<string, mixed> */
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

        if (strlen($response->body()) > (int) config('update-center.max_manifest_bytes', 262144)) {
            throw new InvalidArgumentException('Update manifest exceeds the configured size limit.');
        }

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
        $comparison = SemanticVersion::compare($manifest->version, $currentVersion);
        $manifestData = $manifest->toArray();
        $manifestSha = hash('sha256', json_encode($manifestData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return [
            ...$manifestData,
            'manifest_sha256' => $manifestSha,
            'current_version' => $currentVersion,
            'update_available' => $comparison > 0,
            'same_version' => $comparison === 0,
            'downgrade' => $comparison < 0,
            'php_compatible' => $phpCompatible,
            'app_compatible' => $appCompatible,
            'compatible' => $phpCompatible && $appCompatible,
            'install_enabled' => true,
        ];
    }

    /** @return array{ready:bool,reason:string,offsite_required:bool} */
    private function backupReadiness(): array
    {
        $offsiteRequired = (bool) config('production.backup.offsite_required', true);
        $offsiteTarget = trim((string) config('production.backup.offsite_target', ''));
        if ($offsiteRequired && $offsiteTarget === '') {
            return ['ready' => false, 'reason' => 'Offsite backup target is required but not configured.', 'offsite_required' => true];
        }

        if (! Schema::hasTable('backup_artifacts')) {
            return ['ready' => false, 'reason' => 'Backup ledger is not available.', 'offsite_required' => $offsiteRequired];
        }

        $rpoHours = max(1, (int) config('production.backup.rpo_hours', 24));
        try {
            $backup = DB::table('backup_artifacts')
                ->where('status', 'ready')
                ->whereNotNull('verified_at')
                ->where('created_at', '>=', now()->subHours($rpoHours))
                ->latest('created_at')
                ->first();
        } catch (Throwable) {
            $backup = null;
        }

        if ($backup === null) {
            return ['ready' => false, 'reason' => "No verified backup within the {$rpoHours}h RPO window.", 'offsite_required' => $offsiteRequired];
        }

        return ['ready' => true, 'reason' => 'Verified backup is within the configured RPO window.', 'offsite_required' => $offsiteRequired];
    }
}
