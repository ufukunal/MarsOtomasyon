<?php

namespace App\Modules\Core\Preview;

use App\Modules\Core\Models\Attachment;
use App\Modules\Core\Models\CadDerivativeJob;
use App\Modules\Core\Models\FileAsset;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class AutodeskApsCadProvider implements CadDerivativeProvider
{
    public function provider(): string
    {
        return 'aps';
    }

    public function version(): string
    {
        return 'model-derivative-v2-svf2';
    }

    public function isCloud(): bool
    {
        return true;
    }

    public function supportedExtensions(): array
    {
        return ['dwg', 'dxf', 'obj'];
    }

    public function start(Attachment $attachment, FileAsset $asset): CadDerivativeResult
    {
        $this->assertConfigured();

        $token = $this->token('bucket:create bucket:read data:create data:read data:write');
        $bucketKey = $this->bucketKey((int) $attachment->company_id);
        $this->ensureTransientBucket($bucketKey, $token);

        $extension = strtolower(ltrim((string) $asset->client_extension, '.'));
        $objectKey = (string) $asset->sha256.'.'.$extension;
        $objectId = $this->uploadObject($bucketKey, $objectKey, $asset, $token);
        $urn = $this->urn($objectId);

        $response = Http::withToken($token)
            ->acceptJson()
            ->post($this->baseUrl().'/modelderivative/v2/designdata/job', [
                'input' => ['urn' => $urn],
                'output' => [
                    'formats' => [[
                        'type' => 'svf2',
                        'views' => ['2d', '3d'],
                    ]],
                ],
            ]);

        $response->throw();

        return new CadDerivativeResult(
            status: 'processing',
            providerJobId: $urn,
            previewKind: null,
            manifest: [
                'renderer' => 'autodesk-aps-viewer',
                'urn' => $urn,
                'progress' => 'submitted',
                'capabilities' => ['pan', 'zoom', 'orbit', 'object_tree', 'properties', 'measure'],
            ],
            derivativeSha256: null,
            expiresAt: null,
        );
    }

    public function refresh(CadDerivativeJob $job): CadDerivativeResult
    {
        $this->assertConfigured();

        $manifestValue = $job->getAttribute('manifest');
        $manifest = is_array($manifestValue) ? $manifestValue : [];
        $urn = $manifest['urn'] ?? $job->provider_job_id;
        if (! is_string($urn) || trim($urn) === '') {
            throw new DomainException('APS derivative URN is missing.');
        }

        $token = $this->token('data:read');
        $response = Http::withToken($token)
            ->acceptJson()
            ->get($this->baseUrl().'/modelderivative/v2/designdata/'.$urn.'/manifest');

        $response->throw();

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('APS manifest response is invalid.');
        }

        $status = strtolower((string) ($payload['status'] ?? ''));
        $progress = (string) ($payload['progress'] ?? '');

        if (in_array($status, ['failed', 'timeout'], true)) {
            throw new DomainException('APS model translation failed.');
        }

        if ($status !== 'success') {
            return new CadDerivativeResult(
                status: 'processing',
                providerJobId: $urn,
                previewKind: null,
                manifest: [
                    'renderer' => 'autodesk-aps-viewer',
                    'urn' => $urn,
                    'progress' => $progress === '' ? $status : $progress,
                    'capabilities' => ['pan', 'zoom', 'orbit', 'object_tree', 'properties', 'measure'],
                ],
                derivativeSha256: null,
                expiresAt: null,
            );
        }

        $previewKind = strtolower((string) $job->source_extension) === 'obj' ? 'model_3d' : 'cad_2d';

        return new CadDerivativeResult(
            status: 'ready',
            providerJobId: $urn,
            previewKind: $previewKind,
            manifest: [
                'renderer' => 'autodesk-aps-viewer',
                'urn' => $urn,
                'progress' => 'complete',
                'capabilities' => ['pan', 'zoom', 'orbit', 'object_tree', 'properties', 'measure'],
            ],
            derivativeSha256: hash('sha256', (string) $job->source_sha256.'|'.$this->provider().'|'.$this->version().'|'.$urn),
            expiresAt: new DateTimeImmutable('+1 day'),
        );
    }

    public function viewerToken(CadDerivativeJob $job): array
    {
        $this->assertConfigured();

        if (! $job->isReady()) {
            throw new DomainException('CAD derivative is not ready.');
        }

        $payload = $this->tokenPayload('viewables:read');
        $accessToken = $payload['access_token'] ?? null;
        $expiresIn = $payload['expires_in'] ?? null;

        if (! is_string($accessToken) || $accessToken === '' || ! is_numeric($expiresIn)) {
            throw new RuntimeException('APS viewer token response is invalid.');
        }

        return [
            'access_token' => $accessToken,
            'expires_in' => max(1, (int) $expiresIn),
        ];
    }

    private function uploadObject(string $bucketKey, string $objectKey, FileAsset $asset, string $token): string
    {
        $endpoint = $this->baseUrl().'/oss/v2/buckets/'.$bucketKey.'/objects/'.rawurlencode($objectKey).'/signeds3upload';
        $signed = Http::withToken($token)->acceptJson()->get($endpoint, ['minutesExpiration' => 10]);
        $signed->throw();

        $signedUrl = $signed->json('urls.0');
        $uploadKey = $signed->json('uploadKey');
        if (! is_string($signedUrl) || $signedUrl === '' || ! is_string($uploadKey) || $uploadKey === '') {
            throw new RuntimeException('APS direct upload response is invalid.');
        }

        $bytes = Storage::disk((string) $asset->storage_disk)->get((string) $asset->storage_key);
        if (! is_string($bytes)) {
            throw new RuntimeException('CAD source content could not be read.');
        }
        Http::withBody($bytes, 'application/octet-stream')->put($signedUrl)->throw();

        $finalized = Http::withToken($token)
            ->acceptJson()
            ->post($endpoint, ['uploadKey' => $uploadKey]);
        $finalized->throw();

        $objectId = $finalized->json('objectId');
        if (! is_string($objectId) || $objectId === '') {
            throw new RuntimeException('APS object identity is missing after upload.');
        }

        return $objectId;
    }

    private function ensureTransientBucket(string $bucketKey, string $token): void
    {
        $response = Http::withToken($token)
            ->acceptJson()
            ->post($this->baseUrl().'/oss/v2/buckets', [
                'bucketKey' => $bucketKey,
                'policyKey' => 'transient',
            ]);

        if (! $response->successful() && $response->status() !== 409) {
            $response->throw();
        }
    }

    private function bucketKey(int $companyId): string
    {
        $clientId = (string) config('services.autodesk_aps.client_id', '');

        return 'mars-'.substr(hash('sha256', $clientId), 0, 16).'-'.$companyId;
    }

    private function urn(string $objectId): string
    {
        return rtrim(strtr(base64_encode($objectId), '+/', '-_'), '=');
    }

    private function token(string $scope): string
    {
        $payload = $this->tokenPayload($scope);
        $token = $payload['access_token'] ?? null;
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('APS access token response is invalid.');
        }

        return $token;
    }

    /** @return array<string, mixed> */
    private function tokenPayload(string $scope): array
    {
        $response = Http::asForm()
            ->withBasicAuth(
                (string) config('services.autodesk_aps.client_id'),
                (string) config('services.autodesk_aps.client_secret'),
            )
            ->post($this->baseUrl().'/authentication/v2/token', [
                'grant_type' => 'client_credentials',
                'scope' => $scope,
            ]);

        return $this->jsonArray($response);
    }

    /** @return array<string, mixed> */
    private function jsonArray(Response $response): array
    {
        $response->throw();
        $payload = $response->json();

        return is_array($payload) ? $payload : throw new RuntimeException('APS response is invalid.');
    }

    private function assertConfigured(): void
    {
        if (trim((string) config('services.autodesk_aps.client_id', '')) === ''
            || trim((string) config('services.autodesk_aps.client_secret', '')) === '') {
            throw new DomainException('APS CAD provider is not configured.');
        }
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.autodesk_aps.base_url', 'https://developer.api.autodesk.com'), '/');
    }
}
