<?php

namespace App\Modules\Dispatches\Shipping;

use App\Modules\Dispatches\Models\Dispatch;
use DomainException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use stdClass;

final class ShippingService
{
    private readonly ShippingProviderRegistry $providers;

    public function __construct(ShippingProviderRegistry $providers)
    {
        $this->providers = $providers;
    }

    /** @param array<string, scalar|null> $credentials */
    public function configureConnection(int $companyId, string $provider, string $label, array $credentials): int
    {
        $gateway = $this->providers->get($provider);
        $provider = strtolower(trim($gateway->provider()));
        $label = trim($label);
        if ($label === '') {
            throw new DomainException('Shipping connection label is required.');
        }

        $credentialsJson = json_encode($credentials, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        DB::table('shipping_connections')->updateOrInsert(
            ['company_id' => $companyId, 'provider' => $provider],
            [
                'label' => mb_substr($label, 0, 160),
                'credentials_encrypted' => Crypt::encryptString($credentialsJson),
                'capabilities' => json_encode(array_values(array_unique($gateway->capabilities())), JSON_THROW_ON_ERROR),
                'is_enabled' => true,
                'last_error' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $row = DB::table('shipping_connections')->where('company_id', $companyId)->where('provider', $provider)->first();
        if ($row === null) {
            throw new RuntimeException('Shipping connection could not be persisted.');
        }

        return (int) $row->id;
    }

    /**
     * @param  array<string, mixed>  $shipment
     * @return array{id:int,dispatch_id:int,provider:string,external_shipment_id:string,tracking_number:?string,label_reference:?string,status:string}
     */
    public function createShipment(int $companyId, int $dispatchId, string $provider, string $idempotencyKey, array $shipment = []): array
    {
        if (! Str::isUuid($idempotencyKey)) {
            throw new DomainException('Shipping idempotency key must be a UUID.');
        }

        $provider = strtolower(trim($provider));
        $gateway = $this->providers->get($provider);
        if (! $this->providers->supports($provider, 'shipment_create')) {
            throw new DomainException('Shipping provider does not support shipment creation.');
        }
        $connection = $this->connection($companyId, $provider);
        $dispatch = Dispatch::query()->where('company_id', $companyId)->find($dispatchId);
        if (! $dispatch instanceof Dispatch) {
            throw new DomainException('Dispatch not found for company.');
        }

        $request = [
            'dispatch' => [
                'id' => (int) $dispatch->getKey(),
                'number' => (string) $dispatch->number,
                'dispatch_date' => $dispatch->dispatch_date?->format('Y-m-d'),
                'recipient_name' => $dispatch->recipient_name === null ? null : (string) $dispatch->recipient_name,
                'address_line1' => $dispatch->address_line1 === null ? null : (string) $dispatch->address_line1,
                'address_line2' => $dispatch->address_line2 === null ? null : (string) $dispatch->address_line2,
                'district' => $dispatch->district === null ? null : (string) $dispatch->district,
                'city' => $dispatch->city === null ? null : (string) $dispatch->city,
                'postal_code' => $dispatch->postal_code === null ? null : (string) $dispatch->postal_code,
                'country_code' => $dispatch->country_code === null ? null : (string) $dispatch->country_code,
                'carrier_service' => $dispatch->carrier_service === null ? null : (string) $dispatch->carrier_service,
            ],
            'shipment' => $shipment,
        ];
        $requestHash = hash('sha256', $this->canonicalJson($request));

        $mapping = DB::table('external_shipment_mappings')
            ->where('company_id', $companyId)
            ->where('dispatch_id', $dispatchId)
            ->where('provider', $provider)
            ->first();
        if ($mapping !== null) {
            if (! hash_equals((string) $mapping->request_sha256, $requestHash)) {
                throw new DomainException('Shipment create payload drift detected for dispatch.');
            }

            return $this->mappingArray($mapping);
        }

        /** @var array{id:int,recover:bool} $claim */
        $claim = DB::transaction(function () use ($companyId, $dispatchId, $provider, $idempotencyKey, $requestHash): array {
            $inserted = DB::table('shipping_provider_attempts')->insertOrIgnore([
                'company_id' => $companyId,
                'dispatch_id' => $dispatchId,
                'provider' => $provider,
                'operation' => 'create',
                'idempotency_key' => $idempotencyKey,
                'request_sha256' => $requestHash,
                'status' => 'sending',
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $attempt = DB::table('shipping_provider_attempts')
                ->where('company_id', $companyId)
                ->where('provider', $provider)
                ->where('operation', 'create')
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($attempt === null) {
                throw new RuntimeException('Shipping provider attempt could not be persisted.');
            }
            if ((int) $attempt->dispatch_id !== $dispatchId || ! hash_equals((string) $attempt->request_sha256, $requestHash)) {
                throw new DomainException('Shipping idempotency payload drift detected.');
            }
            if ($inserted === 0 && (string) $attempt->status === 'sending') {
                throw new DomainException('Shipping request with this idempotency key is still in progress.');
            }
            if ((string) $attempt->status === 'succeeded') {
                throw new DomainException('Shipping attempt succeeded but mapping is missing.');
            }

            $recover = $inserted === 0 && in_array((string) $attempt->status, ['ambiguous', 'failed'], true);
            if ($recover) {
                DB::table('shipping_provider_attempts')->where('id', $attempt->id)->update([
                    'status' => 'sending',
                    'error' => null,
                    'started_at' => now(),
                    'finished_at' => null,
                    'updated_at' => now(),
                ]);
            }

            return ['id' => (int) $attempt->id, 'recover' => $recover];
        });

        try {
            $result = $claim['recover'] ? $gateway->findShipment($idempotencyKey) : null;
            $result ??= $gateway->createShipment($idempotencyKey, $request);

            return $this->persistCreatedShipment($companyId, $dispatchId, (int) $connection->id, $provider, $requestHash, $claim['id'], $result);
        } catch (AmbiguousShippingOutcome $exception) {
            $this->failAttempt($claim['id'], 'ambiguous', $exception);
            throw $exception;
        } catch (\Throwable $exception) {
            $this->failAttempt($claim['id'], 'failed', $exception);
            throw $exception;
        }
    }

    public function cancelShipment(int $companyId, int $dispatchId, string $provider, string $idempotencyKey): void
    {
        if (! Str::isUuid($idempotencyKey)) {
            throw new DomainException('Shipping idempotency key must be a UUID.');
        }
        $provider = strtolower(trim($provider));
        $gateway = $this->providers->get($provider);
        if (! $this->providers->supports($provider, 'shipment_cancel')) {
            throw new DomainException('Shipping provider does not support shipment cancellation.');
        }
        $this->connection($companyId, $provider);
        $mapping = $this->mapping($companyId, $dispatchId, $provider);
        $requestHash = hash('sha256', 'cancel|'.(string) $mapping->external_shipment_id);

        $existing = DB::table('shipping_provider_attempts')
            ->where('company_id', $companyId)
            ->where('provider', $provider)
            ->where('operation', 'cancel')
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($existing !== null) {
            if (! hash_equals((string) $existing->request_sha256, $requestHash)) {
                throw new DomainException('Shipping cancellation idempotency payload drift detected.');
            }
            if ((string) $existing->status === 'succeeded') {
                return;
            }
            throw new DomainException('Shipping cancellation with this idempotency key already failed or is in progress.');
        }

        $attemptId = (int) DB::table('shipping_provider_attempts')->insertGetId([
            'company_id' => $companyId,
            'dispatch_id' => $dispatchId,
            'provider' => $provider,
            'operation' => 'cancel',
            'idempotency_key' => $idempotencyKey,
            'request_sha256' => $requestHash,
            'status' => 'sending',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $gateway->cancelShipment((string) $mapping->external_shipment_id);
            DB::transaction(function () use ($attemptId, $mapping): void {
                DB::table('external_shipment_mappings')->where('id', $mapping->id)->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('shipping_provider_attempts')->where('id', $attemptId)->update([
                    'status' => 'succeeded',
                    'external_shipment_id' => $mapping->external_shipment_id,
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (\Throwable $exception) {
            $this->failAttempt($attemptId, 'failed', $exception);
            throw $exception;
        }
    }

    public function labelReference(int $companyId, int $dispatchId, string $provider): ?string
    {
        $provider = strtolower(trim($provider));
        $gateway = $this->providers->get($provider);
        $this->connection($companyId, $provider);
        $mapping = $this->mapping($companyId, $dispatchId, $provider);
        if ($mapping->label_reference !== null && (string) $mapping->label_reference !== '') {
            return (string) $mapping->label_reference;
        }
        if (! $this->providers->supports($provider, 'label_read')) {
            return null;
        }

        $label = $gateway->label((string) $mapping->external_shipment_id);
        if ($label !== null && $label !== '') {
            DB::table('external_shipment_mappings')->where('id', $mapping->id)->update([
                'label_reference' => $label,
                'updated_at' => now(),
            ]);
        }

        return $label;
    }

    /** @return array{status:string,occurred_at:?string,payload:array<string,mixed>} */
    public function refreshTracking(int $companyId, int $dispatchId, string $provider): array
    {
        $provider = strtolower(trim($provider));
        $gateway = $this->providers->get($provider);
        if (! $this->providers->supports($provider, 'tracking_read')) {
            throw new DomainException('Shipping provider does not support tracking.');
        }
        $this->connection($companyId, $provider);
        $mapping = $this->mapping($companyId, $dispatchId, $provider);
        $evidence = $gateway->tracking((string) $mapping->external_shipment_id);
        $evidenceHash = hash('sha256', $this->canonicalJson($evidence));

        DB::transaction(function () use ($companyId, $mapping, $evidence, $evidenceHash): void {
            DB::table('shipping_tracking_evidence')->insertOrIgnore([
                'company_id' => $companyId,
                'external_shipment_mapping_id' => $mapping->id,
                'provider_status' => $evidence['status'],
                'occurred_at' => $evidence['occurred_at'],
                'payload' => json_encode($evidence['payload'], JSON_THROW_ON_ERROR),
                'evidence_sha256' => $evidenceHash,
                'recorded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('external_shipment_mappings')->where('id', $mapping->id)->update([
                'status' => $evidence['status'],
                'last_synced_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return $evidence;
    }

    /**
     * @param  array{external_id:string,tracking_number:?string,label_reference:?string,status:string}  $result
     * @return array{id:int,dispatch_id:int,provider:string,external_shipment_id:string,tracking_number:?string,label_reference:?string,status:string}
     */
    private function persistCreatedShipment(int $companyId, int $dispatchId, int $connectionId, string $provider, string $requestHash, int $attemptId, array $result): array
    {
        $externalId = trim($result['external_id']);
        if ($externalId === '') {
            throw new DomainException('Shipping provider returned an empty external shipment id.');
        }

        return DB::transaction(function () use ($companyId, $dispatchId, $connectionId, $provider, $requestHash, $attemptId, $result, $externalId): array {
            DB::table('external_shipment_mappings')->insertOrIgnore([
                'company_id' => $companyId,
                'dispatch_id' => $dispatchId,
                'shipping_connection_id' => $connectionId,
                'provider' => $provider,
                'external_shipment_id' => $externalId,
                'tracking_number' => $result['tracking_number'],
                'label_reference' => $result['label_reference'],
                'status' => $result['status'],
                'request_sha256' => $requestHash,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $mapping = DB::table('external_shipment_mappings')
                ->where('company_id', $companyId)
                ->where('dispatch_id', $dispatchId)
                ->where('provider', $provider)
                ->lockForUpdate()
                ->first();
            if ($mapping === null) {
                throw new RuntimeException('External shipment mapping could not be persisted.');
            }
            if (! hash_equals((string) $mapping->request_sha256, $requestHash) || (string) $mapping->external_shipment_id !== $externalId) {
                throw new DomainException('External shipment mapping conflict detected.');
            }

            DB::table('external_shipment_mappings')->where('id', $mapping->id)->update([
                'tracking_number' => $result['tracking_number'],
                'label_reference' => $result['label_reference'],
                'status' => $result['status'],
                'updated_at' => now(),
            ]);
            DB::table('shipping_provider_attempts')->where('id', $attemptId)->update([
                'status' => 'succeeded',
                'external_shipment_id' => $externalId,
                'error' => null,
                'finished_at' => now(),
                'updated_at' => now(),
            ]);

            $mapping = DB::table('external_shipment_mappings')->where('id', $mapping->id)->first();
            if ($mapping === null) {
                throw new RuntimeException('External shipment mapping disappeared after persistence.');
            }

            return $this->mappingArray($mapping);
        });
    }

    private function connection(int $companyId, string $provider): stdClass
    {
        $connection = DB::table('shipping_connections')
            ->where('company_id', $companyId)
            ->where('provider', $provider)
            ->where('is_enabled', true)
            ->first();
        if ($connection === null) {
            throw new DomainException('Active shipping connection not found for company.');
        }

        return $connection;
    }

    private function mapping(int $companyId, int $dispatchId, string $provider): stdClass
    {
        $mapping = DB::table('external_shipment_mappings')
            ->where('company_id', $companyId)
            ->where('dispatch_id', $dispatchId)
            ->where('provider', $provider)
            ->first();
        if ($mapping === null) {
            throw new DomainException('External shipment mapping not found.');
        }

        return $mapping;
    }

    /** @return array{id:int,dispatch_id:int,provider:string,external_shipment_id:string,tracking_number:?string,label_reference:?string,status:string} */
    private function mappingArray(stdClass $mapping): array
    {
        return [
            'id' => (int) $mapping->id,
            'dispatch_id' => (int) $mapping->dispatch_id,
            'provider' => (string) $mapping->provider,
            'external_shipment_id' => (string) $mapping->external_shipment_id,
            'tracking_number' => $mapping->tracking_number === null ? null : (string) $mapping->tracking_number,
            'label_reference' => $mapping->label_reference === null ? null : (string) $mapping->label_reference,
            'status' => (string) $mapping->status,
        ];
    }

    private function failAttempt(int $attemptId, string $status, \Throwable $exception): void
    {
        DB::table('shipping_provider_attempts')->where('id', $attemptId)->update([
            'status' => $status,
            'error' => mb_substr($exception->getMessage(), 0, 4000),
            'finished_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<array-key, mixed> $value */
    private function canonicalJson(array $value): string
    {
        $value = $this->sortRecursive($value);

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function sortRecursive(array $value): array
    {
        ksort($value);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                /** @var array<array-key, mixed> $item */
                $value[$key] = $this->sortRecursive($item);
            }
        }

        return $value;
    }
}
