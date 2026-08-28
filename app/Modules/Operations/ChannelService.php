<?php

namespace App\Modules\Operations;

use App\Modules\Operations\Jobs\ProcessIntegrationEvent;
use App\Modules\Operations\Jobs\ProcessIntegrationSync;
use DomainException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class ChannelService
{
    /** @param array<string,mixed> $credentials */
    public function createConnection(int $companyId, string $provider, string $name, ?string $baseUrl, array $credentials, string $webhookSecret): int
    {
        $provider = strtolower(trim($provider));
        $name = trim($name);
        if (! in_array($provider, config('m11.integrations.supported_providers', []), true)) {
            throw new DomainException('Unsupported integration provider.');
        }
        if ($name === '') {
            throw new DomainException('Integration connection name is required.');
        }
        if ($webhookSecret === '') {
            throw new DomainException('Webhook secret is required.');
        }

        return (int) DB::table('integration_connections')->insertGetId([
            'company_id' => $companyId,
            'provider' => $provider,
            'name' => $name,
            'status' => 'active',
            'base_url' => $baseUrl === null ? null : rtrim(trim($baseUrl), '/'),
            'credentials_ciphertext' => Crypt::encryptString(json_encode($credentials, JSON_THROW_ON_ERROR)),
            'webhook_secret_ciphertext' => Crypt::encryptString($webhookSecret),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function ingestWebhook(int $connectionId, string $externalEventId, string $eventType, string $rawPayload, string $signature): int
    {
        if (strlen($rawPayload) > (int) config('m11.integrations.max_payload_bytes', 1048576)) {
            throw new DomainException('Integration payload exceeds configured limit.');
        }
        $connection = DB::table('integration_connections')->where('id', $connectionId)->first();
        if ($connection === null || (string) $connection->status !== 'active') {
            throw new DomainException('Integration connection is not active.');
        }
        $secretCiphertext = (string) ($connection->webhook_secret_ciphertext ?? '');
        if ($secretCiphertext === '') {
            throw new DomainException('Integration webhook secret is not configured.');
        }
        $secret = Crypt::decryptString($secretCiphertext);
        if (! $this->signatureMatches((string) $connection->provider, $rawPayload, $signature, $secret)) {
            throw new DomainException('Integration webhook signature is invalid.');
        }

        $payload = json_decode($rawPayload, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($payload)) {
            throw new DomainException('Integration payload must be a JSON object or array.');
        }
        $eventType = $this->canonicalEventType($eventType);
        $externalEventId = trim($externalEventId);
        if ($externalEventId === '') {
            $externalEventId = hash('sha256', $eventType.'|'.$rawPayload);
        }
        $hash = hash('sha256', $rawPayload);

        return DB::transaction(function () use ($connection, $externalEventId, $eventType, $payload, $hash): int {
            $inserted = DB::table('integration_events')->insertOrIgnore([
                'company_id' => $connection->company_id,
                'connection_id' => $connection->id,
                'external_event_id' => $externalEventId,
                'event_type' => $eventType,
                'payload_sha256' => $hash,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'status' => 'received',
                'attempts' => 0,
                'available_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $event = DB::table('integration_events')
                ->where('company_id', $connection->company_id)
                ->where('connection_id', $connection->id)
                ->where('external_event_id', $externalEventId)
                ->lockForUpdate()
                ->first();
            if ($event === null) {
                throw new RuntimeException('Integration event could not be persisted.');
            }
            if ((string) $event->payload_sha256 !== $hash || (string) $event->event_type !== $eventType) {
                throw new DomainException('Webhook replay payload drift detected.');
            }

            if ($inserted > 0) {
                ProcessIntegrationEvent::dispatch((int) $event->id)->afterCommit();
            }

            return (int) $event->id;
        });
    }

    /** @param array<string,mixed> $payload */
    public function scheduleSync(int $companyId, int $connectionId, string $operation, string $entityType, string $entityId, array $payload, ?string $operationKey = null): int
    {
        $operation = strtolower(trim($operation));
        $entityType = trim($entityType);
        $entityId = trim($entityId);
        if (! in_array($operation, config('m11.integrations.supported_operations', []), true)) {
            throw new DomainException('Unsupported integration operation.');
        }
        if ($entityType === '' || $entityId === '') {
            throw new DomainException('Integration entity type and id are required.');
        }
        $connection = DB::table('integration_connections')
            ->where('company_id', $companyId)
            ->where('id', $connectionId)
            ->where('status', 'active')
            ->first();
        if ($connection === null) {
            throw new DomainException('Integration connection is not active for company.');
        }
        $operationKey ??= (string) Str::uuid();
        if (! Str::isUuid($operationKey)) {
            throw new DomainException('Integration operation key must be a UUID.');
        }
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        $hash = hash('sha256', $encoded);

        return DB::transaction(function () use ($companyId, $connectionId, $operationKey, $operation, $entityType, $entityId, $payload, $hash): int {
            $inserted = DB::table('integration_sync_effects')->insertOrIgnore([
                'company_id' => $companyId,
                'connection_id' => $connectionId,
                'operation_key' => $operationKey,
                'operation' => $operation,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'payload_sha256' => $hash,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'status' => 'queued',
                'attempts' => 0,
                'available_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $effect = DB::table('integration_sync_effects')
                ->where('company_id', $companyId)
                ->where('operation_key', $operationKey)
                ->lockForUpdate()
                ->first();
            if ($effect === null) {
                throw new RuntimeException('Integration sync effect could not be persisted.');
            }
            if (
                (int) $effect->connection_id !== $connectionId
                || (string) $effect->operation !== $operation
                || (string) $effect->entity_type !== $entityType
                || (string) $effect->entity_id !== $entityId
                || (string) $effect->payload_sha256 !== $hash
            ) {
                throw new DomainException('Integration sync idempotency payload drift detected.');
            }

            if ($inserted > 0) {
                ProcessIntegrationSync::dispatch((int) $effect->id)->afterCommit();
            }

            return (int) $effect->id;
        });
    }

    public function processEvent(int $eventId, AutomationService $automation): void
    {
        $event = DB::transaction(function () use ($eventId): ?object {
            $event = DB::table('integration_events')->where('id', $eventId)->lockForUpdate()->first();
            if ($event === null || in_array((string) $event->status, ['processing', 'processed', 'ignored'], true)) {
                return null;
            }
            if (! in_array((string) $event->status, ['received', 'failed'], true)) {
                throw new DomainException('Integration event cannot be processed from current status.');
            }
            DB::table('integration_events')->where('id', $eventId)->update([
                'status' => 'processing',
                'attempts' => (int) $event->attempts + 1,
                'last_error' => null,
                'updated_at' => now(),
            ]);

            return $event;
        });
        if ($event === null) {
            return;
        }

        try {
            $payload = json_decode((string) $event->payload, true, flags: JSON_THROW_ON_ERROR);
            $automation->fire((int) $event->company_id, 'channel.'.(string) $event->event_type, 'integration-event:'.$eventId, is_array($payload) ? $payload : []);
            DB::table('integration_events')->where('id', $eventId)->where('status', 'processing')->update([
                'status' => 'processed',
                'processed_at' => now(),
                'last_error' => null,
                'updated_at' => now(),
            ]);
            DB::table('integration_connections')
                ->where('company_id', $event->company_id)
                ->where('id', $event->connection_id)
                ->update([
                    'last_success_at' => now(),
                    'last_error' => null,
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $exception) {
            DB::table('integration_events')->where('id', $eventId)->where('status', 'processing')->update([
                'status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 4000),
                'updated_at' => now(),
            ]);
            DB::table('integration_connections')
                ->where('company_id', $event->company_id)
                ->where('id', $event->connection_id)
                ->update([
                    'last_error_at' => now(),
                    'last_error' => mb_substr($exception->getMessage(), 0, 4000),
                    'updated_at' => now(),
                ]);
            throw $exception;
        }
    }

    public function processSync(int $effectId): void
    {
        $effect = DB::transaction(function () use ($effectId): ?object {
            $effect = DB::table('integration_sync_effects')->where('id', $effectId)->lockForUpdate()->first();
            if ($effect === null || in_array((string) $effect->status, ['sending', 'succeeded', 'ignored'], true)) {
                return null;
            }
            if (! in_array((string) $effect->status, ['queued', 'failed'], true)) {
                throw new DomainException('Integration sync cannot execute from current status.');
            }
            DB::table('integration_sync_effects')->where('id', $effectId)->update([
                'status' => 'sending',
                'attempts' => (int) $effect->attempts + 1,
                'last_error' => null,
                'updated_at' => now(),
            ]);

            return $effect;
        });
        if ($effect === null) {
            return;
        }

        try {
            $connection = DB::table('integration_connections')
                ->where('company_id', $effect->company_id)
                ->where('id', $effect->connection_id)
                ->where('status', 'active')
                ->first();
            if ($connection === null) {
                throw new RuntimeException('Integration connection is not active for sync effect.');
            }

            $credentials = $this->decryptCredentials((string) ($connection->credentials_ciphertext ?? ''));
            $payload = json_decode((string) $effect->payload, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($payload)) {
                throw new RuntimeException('Integration sync payload is invalid.');
            }
            $response = $this->sendProviderRequest(
                (string) $connection->provider,
                (string) ($connection->base_url ?? ''),
                (string) $effect->operation,
                (string) $effect->entity_id,
                (string) $effect->operation_key,
                $credentials,
                $payload,
            );
            if (! $response->successful()) {
                throw new RuntimeException('Provider returned HTTP '.$response->status().': '.mb_substr($response->body(), 0, 1000));
            }
            $responseData = $response->json();
            $externalId = is_array($responseData) ? ($responseData['id'] ?? $responseData['shipmentPackageId'] ?? null) : null;
            DB::table('integration_sync_effects')->where('id', $effectId)->where('status', 'sending')->update([
                'status' => 'succeeded',
                'completed_at' => now(),
                'external_id' => $externalId === null ? null : (string) $externalId,
                'last_error' => null,
                'updated_at' => now(),
            ]);
            DB::table('integration_connections')
                ->where('company_id', $effect->company_id)
                ->where('id', $effect->connection_id)
                ->update([
                    'last_sync_at' => now(),
                    'last_success_at' => now(),
                    'last_error' => null,
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $exception) {
            DB::table('integration_sync_effects')->where('id', $effectId)->where('status', 'sending')->update([
                'status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 4000),
                'updated_at' => now(),
            ]);
            DB::table('integration_connections')
                ->where('company_id', $effect->company_id)
                ->where('id', $effect->connection_id)
                ->update([
                    'last_error_at' => now(),
                    'last_error' => mb_substr($exception->getMessage(), 0, 4000),
                    'updated_at' => now(),
                ]);
            throw $exception;
        }
    }

    private function signatureMatches(string $provider, string $payload, string $signature, string $secret): bool
    {
        $signature = trim($signature);
        if ($provider === 'woocommerce') {
            return hash_equals(base64_encode(hash_hmac('sha256', $payload, $secret, true)), $signature);
        }
        $signature = str_starts_with($signature, 'sha256=') ? substr($signature, 7) : $signature;

        return hash_equals(hash_hmac('sha256', $payload, $secret), strtolower($signature));
    }

    private function canonicalEventType(string $eventType): string
    {
        $eventType = strtolower(trim($eventType));
        $eventType = str_replace(['/', ':', ' '], '.', $eventType);
        $eventType = preg_replace('/[^a-z0-9._-]+/', '', $eventType) ?? '';
        if ($eventType === '' || strlen($eventType) > 96) {
            throw new DomainException('Invalid integration event type.');
        }

        return $eventType;
    }

    /** @return array<string,mixed> */
    private function decryptCredentials(string $ciphertext): array
    {
        if ($ciphertext === '') {
            return [];
        }
        $decoded = json_decode(Crypt::decryptString($ciphertext), true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string,mixed> $credentials @param array<string,mixed> $payload */
    private function sendProviderRequest(string $provider, string $baseUrl, string $operation, string $entityId, string $operationKey, array $credentials, array $payload): Response
    {
        if ($provider === 'woocommerce') {
            if ($baseUrl === '') {
                throw new RuntimeException('WooCommerce base URL is missing.');
            }
            $key = (string) ($credentials['consumer_key'] ?? '');
            $secret = (string) ($credentials['consumer_secret'] ?? '');
            if ($key === '' || $secret === '') {
                throw new RuntimeException('WooCommerce API credentials are missing.');
            }
            $path = match ($operation) {
                'order' => '/wp-json/wc/v3/orders/'.$entityId,
                'product', 'price', 'stock' => '/wp-json/wc/v3/products/'.$entityId,
                'refund' => '/wp-json/wc/v3/orders/'.$entityId.'/refunds',
                'invoice' => '/wp-json/wc/v3/orders/'.$entityId,
                default => throw new RuntimeException('Unsupported WooCommerce operation.'),
            };
            $request = Http::acceptJson()
                ->withBasicAuth($key, $secret)
                ->withHeaders(['Idempotency-Key' => $operationKey])
                ->timeout(30);

            return $operation === 'refund' ? $request->post($baseUrl.$path, $payload) : $request->put($baseUrl.$path, $payload);
        }

        if ($provider === 'trendyol') {
            $endpoint = data_get($credentials, 'endpoints.'.$operation);
            if (! is_string($endpoint) || trim($endpoint) === '') {
                throw new RuntimeException('Trendyol endpoint is not configured for '.$operation.'.');
            }
            $key = (string) ($credentials['api_key'] ?? '');
            $secret = (string) ($credentials['api_secret'] ?? '');
            if ($key === '' || $secret === '') {
                throw new RuntimeException('Trendyol API credentials are missing.');
            }
            $request = Http::acceptJson()
                ->withBasicAuth($key, $secret)
                ->withHeaders([
                    'User-Agent' => (string) ($credentials['user_agent'] ?? 'MarsOtomasyon'),
                    'Idempotency-Key' => $operationKey,
                ])
                ->timeout(30);

            return $request->post($endpoint, $payload);
        }

        throw new RuntimeException('Unsupported integration provider.');
    }
}
