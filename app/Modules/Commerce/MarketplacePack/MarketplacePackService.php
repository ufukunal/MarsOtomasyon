<?php

namespace App\Modules\Commerce\MarketplacePack;

use App\Modules\Commerce\MarketplacePack\Jobs\ProcessMarketplacePackSync;
use App\Modules\Commerce\ProviderRegistry;
use App\Modules\Operations\ChannelEventStore;
use DomainException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class MarketplacePackService
{
    /** @var list<string> */
    private const PROVIDERS = ['hepsiburada', 'amazon', 'n11', 'pttavm', 'idefix', 'allesgo'];

    public function __construct(
        private MarketplacePackGateway $gateway,
        private ProviderRegistry $registry,
        private ChannelEventStore $events,
    ) {}

    public function testConnection(int $companyId, string $connectionPublicId): bool
    {
        $connection = $this->connection($companyId, $connectionPublicId);
        $provider = (string) $connection->provider;
        $this->assertProvider($provider);
        if (! $this->registry->supports($provider, 'connection_test')) {
            throw new DomainException('Marketplace provider does not support connection testing.');
        }

        try {
            $response = $this->gateway->connectionTest($provider, $this->credentials((string) $connection->credentials_ciphertext));
            if ($response->status() === 429) {
                throw new DomainException('Marketplace connection test is rate limited.');
            }
            if (! $response->successful()) {
                throw new RuntimeException('Marketplace connection test returned HTTP '.$response->status().'.');
            }
            DB::table('integration_connections')->where('id', $connection->id)->update([
                'connection_test_status' => 'ok',
                'connection_tested_at' => now(),
                'connection_test_message' => null,
                'last_success_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        } catch (\Throwable $exception) {
            DB::table('integration_connections')->where('id', $connection->id)->update([
                'connection_test_status' => 'failed',
                'connection_tested_at' => now(),
                'connection_test_message' => mb_substr($exception->getMessage(), 0, 4000),
                'last_error_at' => now(),
                'last_error' => mb_substr($exception->getMessage(), 0, 4000),
                'updated_at' => now(),
            ]);
            throw $exception;
        }
    }

    /** @return array{state_id:int,effect_id:int,version:int} */
    public function queueDesiredState(
        int $companyId,
        string $mappingPublicId,
        ?string $stock,
        ?string $price,
        ?string $currencyCode,
    ): array {
        /** @var object{id:int,connection_id:int,external_product_id:mixed,external_variant_id:mixed,external_sku:mixed,metadata:mixed}|null $mapping */
        $mapping = DB::table('channel_product_mappings')
            ->where('company_id', $companyId)
            ->where('public_id', strtoupper(trim($mappingPublicId)))
            ->where('status', 'active')
            ->first();
        if ($mapping === null) {
            throw new DomainException('Active marketplace product mapping not found.');
        }
        $connection = DB::table('integration_connections')
            ->where('company_id', $companyId)
            ->where('id', $mapping->connection_id)
            ->where('status', 'active')
            ->first();
        if ($connection === null) {
            throw new DomainException('Marketplace connection is not active.');
        }
        $provider = (string) $connection->provider;
        $this->assertProvider($provider);
        if ($stock === null && $price === null) {
            throw new DomainException('Marketplace desired-state requires stock or price.');
        }
        if ($stock !== null && ! $this->registry->supports($provider, 'stock_publish')) {
            throw new DomainException('Marketplace provider does not support stock publishing.');
        }
        if ($price !== null && ! $this->registry->supports($provider, 'price_publish')) {
            throw new DomainException('Marketplace provider does not support price publishing.');
        }

        $stock = $stock === null ? null : $this->decimal($stock, 'stock');
        $price = $price === null ? null : $this->decimal($price, 'price');
        if ($stock !== null && (float) $stock < 0) {
            throw new DomainException('Marketplace stock cannot be negative.');
        }
        if ($price !== null && (float) $price < 0) {
            throw new DomainException('Marketplace price cannot be negative.');
        }
        $currencyCode = $currencyCode === null ? null : strtoupper(trim($currencyCode));
        if ($price !== null && ($currencyCode === null || preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1)) {
            throw new DomainException('Marketplace price requires a three-letter currency code.');
        }

        $metadata = json_decode((string) ($mapping->metadata ?? ''), true);
        $metadata = is_array($metadata) ? $metadata : [];
        $identity = $this->mappingIdentity($provider, $mapping, $metadata);
        $payload = [
            'quantity' => $stock === null ? null : (int) $stock,
            'price' => $price,
            'currency_code' => $currencyCode,
            'fulfillment' => isset($metadata['fulfillment']) ? (string) $metadata['fulfillment'] : 'FBM',
            'product_type' => isset($metadata['product_type']) ? (string) $metadata['product_type'] : 'PRODUCT',
        ];

        return DB::transaction(function () use ($companyId, $connection, $mapping, $identity, $payload, $stock, $price, $currencyCode): array {
            $state = DB::table('channel_listing_states')
                ->where('company_id', $companyId)
                ->where('connection_id', $connection->id)
                ->where('mapping_id', $mapping->id)
                ->lockForUpdate()
                ->first();
            if ($state === null) {
                $stateId = (int) DB::table('channel_listing_states')->insertGetId([
                    'public_id' => (string) Str::ulid(),
                    'company_id' => $companyId,
                    'connection_id' => $connection->id,
                    'mapping_id' => $mapping->id,
                    'desired_version' => 0,
                    'published_version' => 0,
                    'status' => 'idle',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $state = DB::table('channel_listing_states')->where('id', $stateId)->lockForUpdate()->first();
            }
            if ($state === null) {
                throw new RuntimeException('Marketplace listing state could not be created.');
            }

            $version = (int) $state->desired_version + 1;
            DB::table('channel_listing_states')->where('id', $state->id)->update([
                'desired_version' => $version,
                'desired_stock' => $stock,
                'desired_price' => $price,
                'desired_currency_code' => $currencyCode,
                'status' => 'queued',
                'last_error' => null,
                'updated_at' => now(),
            ]);
            $operationKey = (string) Str::uuid();
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
            $effectId = (int) DB::table('integration_sync_effects')->insertGetId([
                'company_id' => $companyId,
                'connection_id' => $connection->id,
                'operation_key' => $operationKey,
                'operation' => $stock !== null ? 'stock' : 'price',
                'entity_type' => 'product',
                'entity_id' => $identity,
                'payload_sha256' => hash('sha256', $encoded),
                'payload' => $encoded,
                'status' => 'queued',
                'attempts' => 0,
                'available_at' => now(),
                'guard_type' => 'listing_state',
                'guard_id' => $state->id,
                'guard_version' => $version,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            ProcessMarketplacePackSync::dispatch($effectId)->afterCommit();

            return ['state_id' => (int) $state->id, 'effect_id' => $effectId, 'version' => $version];
        });
    }

    public function processSync(int $effectId): void
    {
        $effect = DB::transaction(function () use ($effectId): ?object {
            $effect = DB::table('integration_sync_effects')->where('id', $effectId)->lockForUpdate()->first();
            if ($effect === null || in_array((string) $effect->status, ['sending', 'succeeded', 'ignored'], true)) {
                return null;
            }
            if (! in_array((string) $effect->status, ['queued', 'failed'], true)) {
                throw new DomainException('Marketplace sync cannot run from current status.');
            }
            $state = DB::table('channel_listing_states')->where('id', (int) $effect->guard_id)->lockForUpdate()->first();
            if ($state === null || (int) $state->desired_version !== (int) $effect->guard_version) {
                DB::table('integration_sync_effects')->where('id', $effectId)->update([
                    'status' => 'ignored',
                    'completed_at' => now(),
                    'ignored_reason' => 'stale desired-state version',
                    'updated_at' => now(),
                ]);

                return null;
            }
            $duplicate = DB::table('integration_sync_effects')
                ->where('connection_id', $effect->connection_id)
                ->where('payload_sha256', $effect->payload_sha256)
                ->where('status', 'succeeded')
                ->where('completed_at', '>=', now()->subMinutes(5))
                ->exists();
            if ($duplicate) {
                DB::table('integration_sync_effects')->where('id', $effectId)->update([
                    'status' => 'ignored',
                    'completed_at' => now(),
                    'ignored_reason' => 'marketplace duplicate desired-state cooldown',
                    'updated_at' => now(),
                ]);
                $this->publishState((int) $effect->guard_id, (int) $effect->guard_version);

                return null;
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
            $connection = DB::table('integration_connections')->where('id', $effect->connection_id)->where('status', 'active')->first();
            if ($connection === null) {
                throw new RuntimeException('Marketplace connection is not active for sync.');
            }
            $provider = (string) $connection->provider;
            $this->assertProvider($provider);
            $payload = json_decode((string) $effect->payload, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($payload)) {
                throw new RuntimeException('Marketplace sync payload is invalid.');
            }
            $response = $this->gateway->publishDesiredState(
                $provider,
                $this->credentials((string) $connection->credentials_ciphertext),
                (string) $effect->entity_id,
                $payload,
            );
            if ($response->status() === 429) {
                $retryAfter = max(60, min((int) $response->header('Retry-After') ?: 300, 3600));
                DB::table('integration_sync_effects')->where('id', $effectId)->update([
                    'status' => 'failed',
                    'available_at' => now()->addSeconds($retryAfter),
                    'last_error' => 'Marketplace provider rate limit exceeded.',
                    'updated_at' => now(),
                ]);
                throw new DomainException('Marketplace provider rate limit exceeded.');
            }
            if (! $response->successful()) {
                throw new RuntimeException('Marketplace provider returned HTTP '.$response->status().': '.mb_substr($response->body(), 0, 1000));
            }
            $body = $response->json();
            $externalId = is_array($body) ? ($body['batchRequestId'] ?? $body['trackingId'] ?? $body['id'] ?? null) : null;
            DB::table('integration_sync_effects')->where('id', $effectId)->update([
                'status' => 'succeeded',
                'completed_at' => now(),
                'external_id' => $externalId === null ? null : (string) $externalId,
                'last_error' => null,
                'updated_at' => now(),
            ]);
            $this->publishState((int) $effect->guard_id, (int) $effect->guard_version);
            DB::table('integration_connections')->where('id', $connection->id)->update([
                'last_sync_at' => now(),
                'last_success_at' => now(),
                'last_error' => null,
                'updated_at' => now(),
            ]);
        } catch (ConnectionException $exception) {
            DB::table('integration_sync_effects')->where('id', $effectId)->update([
                'status' => 'ambiguous',
                'last_error' => 'Ambiguous marketplace outcome: '.mb_substr($exception->getMessage(), 0, 3900),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $status = DB::table('integration_sync_effects')->where('id', $effectId)->value('status');
            if ($status === 'sending') {
                DB::table('integration_sync_effects')->where('id', $effectId)->update([
                    'status' => 'failed',
                    'last_error' => mb_substr($exception->getMessage(), 0, 4000),
                    'updated_at' => now(),
                ]);
            }
            throw $exception;
        }
    }

    /** @return list<int> */
    public function pollOrders(int $companyId, string $connectionPublicId, ?string $modifiedAfter = null, int $page = 1, int $perPage = 50): array
    {
        $connection = $this->connection($companyId, $connectionPublicId);
        $provider = (string) $connection->provider;
        $this->assertProvider($provider);
        if (! $this->registry->supports($provider, 'order_polling')) {
            throw new DomainException('Marketplace provider does not support order polling.');
        }
        if ($page < 1 || $perPage < 1 || $perPage > 100) {
            throw new DomainException('Invalid marketplace polling page window.');
        }
        $start = $modifiedAfter === null || trim($modifiedAfter) === '' ? now()->subDays(7)->toIso8601String() : $modifiedAfter;
        $response = $this->gateway->orders($provider, $this->credentials((string) $connection->credentials_ciphertext), [
            'page' => $page,
            'size' => $perPage,
            'start' => $start,
            'end' => now()->toIso8601String(),
        ]);
        if ($response->status() === 429) {
            throw new DomainException('Marketplace order polling is rate limited.');
        }
        if (! $response->successful()) {
            throw new RuntimeException('Marketplace order polling returned HTTP '.$response->status().'.');
        }

        $records = $this->orderRecords($provider, $response->json());
        $eventIds = [];
        foreach ($records as $record) {
            $encoded = json_encode($record, JSON_THROW_ON_ERROR);
            $identity = $this->orderIdentity($provider, $record);
            $status = $this->orderStatus($record);
            $eventIds[] = $this->events->persist(
                $connection,
                $provider.'-poll-'.$identity.'-'.substr(hash('sha256', $encoded), 0, 32),
                'order.'.$status,
                $record,
            );
        }

        return $eventIds;
    }

    /** @param array<string,mixed> $metadata */
    private function mappingIdentity(string $provider, object $mapping, array $metadata): string
    {
        $externalProduct = trim((string) ($mapping->external_product_id ?? ''));
        $externalVariant = trim((string) ($mapping->external_variant_id ?? ''));
        $externalSku = trim((string) ($mapping->external_sku ?? ''));
        $barcode = isset($metadata['barcode']) && is_scalar($metadata['barcode']) ? trim((string) $metadata['barcode']) : '';
        $identity = match ($provider) {
            'amazon', 'n11', 'hepsiburada' => $externalSku,
            'pttavm', 'idefix' => $barcode !== '' ? $barcode : $externalSku,
            'allesgo' => $externalVariant !== '' ? $externalVariant : $externalProduct,
            default => '',
        };
        if ($identity === '') {
            throw new DomainException('Marketplace mapping is missing provider publish identity.');
        }

        return $identity;
    }

    /** @return list<array<string,mixed>> */
    private function orderRecords(string $provider, mixed $body): array
    {
        if (! is_array($body)) {
            throw new RuntimeException('Marketplace order response must be JSON.');
        }
        $records = match ($provider) {
            'amazon' => $body['payload']['Orders'] ?? $body['Orders'] ?? [],
            'n11', 'idefix' => $body['content'] ?? $body['items'] ?? $body['shipments'] ?? [],
            'allesgo' => $body['data'] ?? $body['orders'] ?? [],
            'hepsiburada' => $body['items'] ?? $body,
            'pttavm' => $body,
            default => [],
        };
        if (! is_array($records) || ! array_is_list($records)) {
            throw new RuntimeException('Marketplace order response must contain a list.');
        }

        return array_values(array_filter($records, 'is_array'));
    }

    /** @param array<string,mixed> $record */
    private function orderIdentity(string $provider, array $record): string
    {
        foreach (['AmazonOrderId', 'orderNumber', 'siparisNo', 'order_id', 'orderId', 'id', 'shipmentPackageId'] as $key) {
            if (isset($record[$key]) && is_scalar($record[$key]) && trim((string) $record[$key]) !== '') {
                return trim((string) $record[$key]);
            }
        }
        throw new RuntimeException('Marketplace order record has no identity for '.$provider.'.');
    }

    /** @param array<string,mixed> $record */
    private function orderStatus(array $record): string
    {
        foreach (['shipmentPackageStatus', 'status', 'orderStatus', 'SiparisDurumu'] as $key) {
            if (isset($record[$key]) && is_scalar($record[$key]) && trim((string) $record[$key]) !== '') {
                return strtolower(preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim((string) $record[$key])) ?? 'updated');
            }
        }

        return 'updated';
    }

    private function publishState(int $stateId, int $version): void
    {
        $state = DB::table('channel_listing_states')->where('id', $stateId)->where('desired_version', $version)->first();
        if ($state === null) {
            return;
        }
        DB::table('channel_listing_states')->where('id', $stateId)->update([
            'published_version' => $version,
            'published_stock' => $state->desired_stock,
            'published_price' => $state->desired_price,
            'published_currency_code' => $state->desired_currency_code,
            'published_media' => $state->desired_media,
            'status' => 'synced',
            'last_error' => null,
            'updated_at' => now(),
        ]);
    }

    private function decimal(string $value, string $field): string
    {
        if (! is_numeric(trim($value))) {
            throw new DomainException('Marketplace '.$field.' must be numeric.');
        }

        return number_format((float) $value, 6, '.', '');
    }

    private function assertProvider(string $provider): void
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            throw new DomainException('Connection is not a verified marketplace-pack provider.');
        }
    }

    /** @return array<string,mixed> */
    private function credentials(string $ciphertext): array
    {
        if ($ciphertext === '') {
            throw new DomainException('Marketplace credentials are not configured.');
        }
        $decoded = json_decode(Crypt::decryptString($ciphertext), true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return object{id:mixed,company_id:mixed,provider:mixed,credentials_ciphertext:mixed} */
    private function connection(int $companyId, string $publicId): object
    {
        /** @var object{id:mixed,company_id:mixed,provider:mixed,credentials_ciphertext:mixed}|null $connection */
        $connection = DB::table('integration_connections')
            ->where('company_id', $companyId)
            ->where('public_id', strtoupper(trim($publicId)))
            ->where('status', 'active')
            ->first();
        if ($connection === null) {
            throw new DomainException('Active marketplace connection not found.');
        }

        return $connection;
    }
}
