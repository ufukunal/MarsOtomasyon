<?php

namespace App\Modules\Commerce;

use App\Modules\Operations\ChannelEventStore;
use App\Modules\Operations\ChannelService;
use App\Modules\Operations\Jobs\ProcessIntegrationEvent;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class ChannelCenterService
{
    public function __construct(
        private ChannelService $channels,
        private ChannelEventStore $events,
        private ProviderRegistry $providers,
    ) {}

    /**
     * @param  array<string,mixed>  $credentials
     */
    public function createConnection(
        int $companyId,
        string $provider,
        string $name,
        ?string $baseUrl,
        array $credentials,
        string $webhookSecret,
        string $financialMode = 'direct_account',
        ?int $defaultAccountId = null,
        ?int $clearingAccountId = null,
    ): string {
        $this->providers->get($provider);
        $financialMode = strtolower(trim($financialMode));
        if (! in_array($financialMode, ['direct_account', 'clearing_account'], true)) {
            throw new DomainException('Invalid channel financial mode.');
        }
        if ($defaultAccountId !== null) {
            $this->assertCompanyAccount($companyId, $defaultAccountId);
        }
        if ($clearingAccountId !== null) {
            $this->assertCompanyAccount($companyId, $clearingAccountId, 'clearing');
        }
        if ($financialMode === 'direct_account' && $defaultAccountId === null) {
            throw new DomainException('Direct-account channel requires a default account.');
        }
        if ($financialMode === 'clearing_account' && $clearingAccountId === null) {
            throw new DomainException('Clearing-account channel requires a clearing account.');
        }

        $connectionId = $this->channels->createConnection(
            $companyId,
            $provider,
            $name,
            $baseUrl,
            $credentials,
            $webhookSecret,
        );
        DB::table('integration_connections')->where('company_id', $companyId)->where('id', $connectionId)->update([
            'financial_mode' => $financialMode,
            'default_account_id' => $defaultAccountId,
            'clearing_account_id' => $clearingAccountId,
            'updated_at' => now(),
        ]);

        $publicId = DB::table('integration_connections')->where('id', $connectionId)->value('public_id');
        if (! is_string($publicId) || $publicId === '') {
            throw new RuntimeException('Channel connection public id was not generated.');
        }

        return $publicId;
    }

    public function testConnection(int $companyId, string $connectionPublicId): bool
    {
        $connection = $this->connection($companyId, $connectionPublicId);
        $provider = (string) $connection->provider;
        if (! $this->providers->supports($provider, 'connection_test')) {
            throw new DomainException('Provider does not support connection testing.');
        }

        try {
            if ($provider !== 'woocommerce') {
                throw new DomainException('Connection test is not implemented for provider.');
            }
            $baseUrl = rtrim((string) ($connection->base_url ?? ''), '/');
            $credentials = $this->credentials((string) ($connection->credentials_ciphertext ?? ''));
            $key = (string) ($credentials['consumer_key'] ?? '');
            $secret = (string) ($credentials['consumer_secret'] ?? '');
            if ($baseUrl === '' || $key === '' || $secret === '') {
                throw new DomainException('WooCommerce connection settings are incomplete.');
            }
            $response = Http::acceptJson()
                ->withBasicAuth($key, $secret)
                ->timeout(15)
                ->get($baseUrl.'/wp-json/wc/v3/system_status');
            if (! $response->successful()) {
                throw new RuntimeException('WooCommerce connection test returned HTTP '.$response->status().'.');
            }

            DB::table('integration_connections')->where('id', $connection->id)->update([
                'connection_test_status' => 'ok',
                'connection_tested_at' => now(),
                'connection_test_message' => null,
                'updated_at' => now(),
            ]);

            return true;
        } catch (\Throwable $exception) {
            DB::table('integration_connections')->where('id', $connection->id)->update([
                'connection_test_status' => 'failed',
                'connection_tested_at' => now(),
                'connection_test_message' => mb_substr($exception->getMessage(), 0, 4000),
                'updated_at' => now(),
            ]);

            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }
    }

    /** @param array<string,mixed> $metadata */
    public function mapProduct(
        int $companyId,
        string $connectionPublicId,
        int $productId,
        ?string $externalProductId,
        ?string $externalVariantId,
        ?string $externalSku,
        array $metadata = [],
    ): string {
        $connection = $this->connection($companyId, $connectionPublicId);
        $product = DB::table('products')
            ->where('company_id', $companyId)
            ->where('id', $productId)
            ->where('status', 'active')
            ->first(['id']);
        if ($product === null) {
            throw new DomainException('Active Mars product not found for channel mapping.');
        }
        $externalProductId = $this->nullableText($externalProductId, 192);
        $externalVariantId = $this->nullableText($externalVariantId, 192);
        $externalSku = $this->nullableText($externalSku, 192);
        if ($externalProductId === null && $externalSku === null) {
            throw new DomainException('Channel product mapping requires external product id or SKU.');
        }

        return DB::transaction(function () use ($companyId, $connection, $productId, $externalProductId, $externalVariantId, $externalSku, $metadata): string {
            /** @var object{id:int,public_id:string}|null $existing */
            $existing = DB::table('channel_product_mappings')
                ->where('company_id', $companyId)
                ->where('connection_id', $connection->id)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();
            if ($existing === null) {
                $publicId = (string) Str::ulid();
                DB::table('channel_product_mappings')->insert([
                    'public_id' => $publicId,
                    'company_id' => $companyId,
                    'connection_id' => $connection->id,
                    'product_id' => $productId,
                    'external_product_id' => $externalProductId,
                    'external_variant_id' => $externalVariantId,
                    'external_sku' => $externalSku,
                    'status' => 'active',
                    'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $publicId;
            }

            DB::table('channel_product_mappings')->where('id', $existing->id)->update([
                'external_product_id' => $externalProductId,
                'external_variant_id' => $externalVariantId,
                'external_sku' => $externalSku,
                'status' => 'active',
                'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

            return (string) $existing->public_id;
        });
    }

    /**
     * @param  list<string>  $mediaUrls
     * @return array{state_id:int,effect_id:int,version:int}
     */
    public function queueDesiredState(
        int $companyId,
        string $mappingPublicId,
        ?string $stock,
        ?string $price,
        ?string $currencyCode,
        array $mediaUrls = [],
    ): array {
        /** @var object{id:int,connection_id:int,external_product_id:mixed}|null $mapping */
        $mapping = DB::table('channel_product_mappings')
            ->where('company_id', $companyId)
            ->where('public_id', strtoupper(trim($mappingPublicId)))
            ->where('status', 'active')
            ->first();
        if ($mapping === null) {
            throw new DomainException('Active channel product mapping not found.');
        }
        /** @var object{id:int,provider:mixed}|null $connection */
        $connection = DB::table('integration_connections')
            ->where('company_id', $companyId)
            ->where('id', $mapping->connection_id)
            ->where('status', 'active')
            ->first();
        if ($connection === null) {
            throw new DomainException('Channel connection is not active.');
        }
        if (! $this->providers->supports((string) $connection->provider, 'product_publish')) {
            throw new DomainException('Provider does not support product publishing.');
        }
        $externalProductId = trim((string) ($mapping->external_product_id ?? ''));
        if ($externalProductId === '') {
            throw new DomainException('Publishing requires an external product id mapping.');
        }
        $stock = $stock === null ? null : $this->nonNegativeDecimal($stock, 'stock');
        $price = $price === null ? null : $this->nonNegativeDecimal($price, 'price');
        $currencyCode = $currencyCode === null ? null : strtoupper(trim($currencyCode));
        if ($currencyCode !== null && ! preg_match('/^[A-Z]{3}$/', $currencyCode)) {
            throw new DomainException('Invalid desired-state currency code.');
        }
        $mediaUrls = $this->mediaUrls($mediaUrls);

        return DB::transaction(function () use ($companyId, $connection, $mapping, $externalProductId, $stock, $price, $currencyCode, $mediaUrls): array {
            /** @var object{id:int,desired_version:int}|null $state */
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
                /** @var object{id:int,desired_version:int}|null $state */
                $state = DB::table('channel_listing_states')->where('id', $stateId)->lockForUpdate()->first();
            }
            if ($state === null) {
                throw new RuntimeException('Channel listing state could not be created.');
            }

            $version = (int) $state->desired_version + 1;
            DB::table('channel_listing_states')->where('id', $state->id)->update([
                'desired_version' => $version,
                'desired_stock' => $stock,
                'desired_price' => $price,
                'desired_currency_code' => $currencyCode,
                'desired_media' => $mediaUrls === [] ? null : json_encode($mediaUrls, JSON_THROW_ON_ERROR),
                'status' => 'queued',
                'last_error' => null,
                'updated_at' => now(),
            ]);

            $payload = [];
            if ($stock !== null) {
                $payload['manage_stock'] = true;
                $payload['stock_quantity'] = $stock;
            }
            if ($price !== null) {
                $payload['regular_price'] = $price;
            }
            if ($mediaUrls !== []) {
                $payload['images'] = array_map(static fn (string $url): array => ['src' => $url], $mediaUrls);
            }
            $effectId = $this->channels->scheduleSync(
                $companyId,
                (int) $connection->id,
                'product',
                'product',
                $externalProductId,
                $payload,
            );
            DB::table('integration_sync_effects')->where('id', $effectId)->update([
                'guard_type' => 'listing_state',
                'guard_id' => (int) $state->id,
                'guard_version' => $version,
                'updated_at' => now(),
            ]);

            return ['state_id' => (int) $state->id, 'effect_id' => $effectId, 'version' => $version];
        });
    }

    public function retryOrder(int $companyId, string $inboxPublicId): int
    {
        return DB::transaction(function () use ($companyId, $inboxPublicId): int {
            /** @var object{id:int,status:mixed,connection_id:int,external_event_id:mixed}|null $inbox */
            $inbox = DB::table('channel_order_inbox')
                ->where('company_id', $companyId)
                ->where('public_id', strtoupper(trim($inboxPublicId)))
                ->lockForUpdate()
                ->first();
            if ($inbox === null || ! in_array((string) $inbox->status, ['stock_problem', 'failed'], true)) {
                throw new DomainException('Channel order is not retryable.');
            }
            /** @var object{id:int}|null $event */
            $event = DB::table('integration_events')
                ->where('company_id', $companyId)
                ->where('connection_id', $inbox->connection_id)
                ->where('external_event_id', $inbox->external_event_id)
                ->lockForUpdate()
                ->first();
            if ($event === null) {
                throw new DomainException('Source integration event was not found for retry.');
            }

            DB::table('channel_order_inbox')->where('id', $inbox->id)->update([
                'status' => 'received',
                'problem_code' => null,
                'problem_message' => null,
                'updated_at' => now(),
            ]);
            DB::table('channel_problems')->where('order_inbox_id', $inbox->id)->where('status', 'open')->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('integration_events')->where('id', $event->id)->update([
                'status' => 'received',
                'processed_at' => null,
                'last_error' => null,
                'available_at' => now(),
                'updated_at' => now(),
            ]);
            ProcessIntegrationEvent::dispatch((int) $event->id)->afterCommit();

            return (int) $event->id;
        });
    }

    /** @return list<int> */
    public function pollOrders(int $companyId, string $connectionPublicId, ?string $modifiedAfter = null, int $page = 1, int $perPage = 50): array
    {
        $connection = $this->connection($companyId, $connectionPublicId);
        if ((string) $connection->provider !== 'woocommerce' || ! $this->providers->supports('woocommerce', 'order_polling')) {
            throw new DomainException('Provider does not support order polling.');
        }
        if ($page < 1 || $perPage < 1 || $perPage > 100) {
            throw new DomainException('Invalid polling page window.');
        }
        $baseUrl = rtrim((string) ($connection->base_url ?? ''), '/');
        $credentials = $this->credentials((string) ($connection->credentials_ciphertext ?? ''));
        $key = (string) ($credentials['consumer_key'] ?? '');
        $secret = (string) ($credentials['consumer_secret'] ?? '');
        if ($baseUrl === '' || $key === '' || $secret === '') {
            throw new DomainException('WooCommerce polling settings are incomplete.');
        }
        $query = ['page' => $page, 'per_page' => $perPage, 'orderby' => 'date', 'order' => 'asc'];
        if ($modifiedAfter !== null && trim($modifiedAfter) !== '') {
            try {
                $query['modified_after'] = CarbonImmutable::parse($modifiedAfter)->utc()->toIso8601String();
                $query['dates_are_gmt'] = 'true';
            } catch (\Throwable) {
                throw new DomainException('Polling modified-after value is invalid.');
            }
        }
        $response = Http::acceptJson()->withBasicAuth($key, $secret)->timeout(30)
            ->get($baseUrl.'/wp-json/wc/v3/orders', $query);
        if ($response->status() === 429) {
            throw new DomainException('WooCommerce polling is rate limited.');
        }
        if (! $response->successful()) {
            throw new RuntimeException('WooCommerce polling returned HTTP '.$response->status().'.');
        }
        $orders = $response->json();
        if (! is_array($orders) || ! array_is_list($orders)) {
            throw new RuntimeException('WooCommerce polling response must be an order list.');
        }
        $eventIds = [];
        foreach ($orders as $order) {
            if (! is_array($order) || ! isset($order['id']) || ! is_scalar($order['id'])) {
                throw new RuntimeException('WooCommerce polling returned an order without an id.');
            }
            $encoded = json_encode($order, JSON_THROW_ON_ERROR);
            $externalEventId = 'woo-poll-'.(string) $order['id'].'-'.substr(hash('sha256', $encoded), 0, 32);
            $eventIds[] = $this->events->persist($connection, $externalEventId, 'order.updated', $order);
        }

        return $eventIds;
    }

    public function queueInvoiceSync(int $companyId, string $connectionPublicId, int $salesInvoiceId, string $externalOrderId): string
    {
        $connection = $this->connection($companyId, $connectionPublicId);
        if (! $this->providers->supports((string) $connection->provider, 'invoice_publish')) {
            throw new DomainException('Provider does not support invoice publishing.');
        }
        $invoice = DB::table('sales_invoices')
            ->where('company_id', $companyId)
            ->where('id', $salesInvoiceId)
            ->where('status', 'finalized')
            ->first(['id', 'number']);
        if ($invoice === null) {
            throw new DomainException('Finalized Mars sales invoice not found.');
        }
        /** @var object{id:mixed,number:mixed} $invoice */
        $externalOrderId = $this->requiredText($externalOrderId, 192, 'External order id');

        return DB::transaction(function () use ($companyId, $connection, $invoice, $externalOrderId): string {
            $existing = DB::table('channel_invoice_syncs')
                ->where('company_id', $companyId)
                ->where('connection_id', $connection->id)
                ->where('sales_invoice_id', $invoice->id)
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                /** @var object{public_id:mixed,external_order_id:mixed,sync_effect_id:mixed} $existing */
                if ((string) $existing->external_order_id !== $externalOrderId) {
                    throw new DomainException('Invoice sync external-order drift detected.');
                }

                return (string) $existing->public_id;
            }

            $publicId = (string) Str::ulid();
            $rowId = (int) DB::table('channel_invoice_syncs')->insertGetId([
                'public_id' => $publicId,
                'company_id' => $companyId,
                'connection_id' => $connection->id,
                'sales_invoice_id' => $invoice->id,
                'external_order_id' => $externalOrderId,
                'status' => 'queued',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $effectId = $this->channels->scheduleSync(
                $companyId,
                (int) $connection->id,
                'invoice',
                'sales_invoice',
                $externalOrderId,
                ['meta_data' => [[
                    'key' => '_mars_sales_invoice_number',
                    'value' => (string) $invoice->number,
                ]]],
            );
            DB::table('channel_invoice_syncs')->where('id', $rowId)->update([
                'sync_effect_id' => $effectId,
                'updated_at' => now(),
            ]);

            return $publicId;
        });
    }

    /** @param array<string,mixed> $evidence */
    public function recordReturnEvidence(
        int $companyId,
        string $connectionPublicId,
        string $externalReturnId,
        string $externalOrderId,
        array $evidence,
    ): int {
        $connection = $this->connection($companyId, $connectionPublicId);
        $externalReturnId = $this->requiredText($externalReturnId, 192, 'External return id');
        $externalOrderId = $this->requiredText($externalOrderId, 192, 'External order id');
        $encoded = json_encode($evidence, JSON_THROW_ON_ERROR);
        $hash = hash('sha256', $encoded);

        return DB::transaction(function () use ($companyId, $connection, $externalReturnId, $externalOrderId, $evidence, $hash): int {
            DB::table('channel_return_events')->insertOrIgnore([
                'public_id' => (string) Str::ulid(),
                'company_id' => $companyId,
                'connection_id' => $connection->id,
                'external_return_id' => $externalReturnId,
                'external_order_id' => $externalOrderId,
                'payload_sha256' => $hash,
                'evidence' => json_encode($evidence, JSON_THROW_ON_ERROR),
                'status' => 'awaiting_invoice',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            /** @var object{id:int,external_order_id:mixed,payload_sha256:mixed}|null $row */
            $row = DB::table('channel_return_events')
                ->where('company_id', $companyId)
                ->where('connection_id', $connection->id)
                ->where('external_return_id', $externalReturnId)
                ->lockForUpdate()
                ->first();
            if ($row === null) {
                throw new RuntimeException('Channel return evidence could not be persisted.');
            }
            if ((string) $row->external_order_id !== $externalOrderId || (string) $row->payload_sha256 !== $hash) {
                throw new DomainException('Channel return evidence replay drift detected.');
            }

            return (int) $row->id;
        });
    }

    /** @param array<string,mixed> $evidence */
    public function recordSettlementEvidence(
        int $companyId,
        string $connectionPublicId,
        string $externalSettlementId,
        string $currencyCode,
        string $grossAmount,
        string $feeAmount,
        string $occurredAt,
        array $evidence,
    ): int {
        $connection = $this->connection($companyId, $connectionPublicId);
        if ((string) $connection->financial_mode !== 'clearing_account' || $connection->clearing_account_id === null) {
            throw new DomainException('Settlement evidence requires a clearing-account channel.');
        }
        $externalSettlementId = $this->requiredText($externalSettlementId, 192, 'External settlement id');
        $currencyCode = strtoupper(trim($currencyCode));
        if (! preg_match('/^[A-Z]{3}$/', $currencyCode)) {
            throw new DomainException('Settlement currency code is invalid.');
        }
        $amounts = DB::selectOne(
            'SELECT CAST(CAST(? AS numeric) AS numeric(20,6))::text AS gross, CAST(CAST(? AS numeric) AS numeric(20,6))::text AS fee, CAST((CAST(? AS numeric) - CAST(? AS numeric)) AS numeric(20,6))::text AS net',
            [$grossAmount, $feeAmount, $grossAmount, $feeAmount],
        );
        if ($amounts === null || (float) $amounts->gross < 0 || (float) $amounts->fee < 0 || (float) $amounts->net < 0) {
            throw new DomainException('Settlement amounts are invalid.');
        }
        try {
            $occurred = CarbonImmutable::parse($occurredAt);
        } catch (\Throwable) {
            throw new DomainException('Settlement occurred-at value is invalid.');
        }
        $encoded = json_encode($evidence, JSON_THROW_ON_ERROR);
        $hash = hash('sha256', implode('|', [$currencyCode, $amounts->gross, $amounts->fee, $amounts->net, $occurred->toIso8601String(), $encoded]));

        return DB::transaction(function () use ($companyId, $connection, $externalSettlementId, $currencyCode, $amounts, $occurred, $evidence, $hash): int {
            DB::table('channel_settlement_evidence')->insertOrIgnore([
                'public_id' => (string) Str::ulid(),
                'company_id' => $companyId,
                'connection_id' => $connection->id,
                'external_settlement_id' => $externalSettlementId,
                'currency_code' => $currencyCode,
                'gross_amount' => $amounts->gross,
                'fee_amount' => $amounts->fee,
                'net_amount' => $amounts->net,
                'clearing_account_id' => $connection->clearing_account_id,
                'occurred_at' => $occurred,
                'evidence' => json_encode(['fingerprint' => $hash, 'payload' => $evidence], JSON_THROW_ON_ERROR),
                'status' => 'received',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            /** @var object{id:int,evidence:mixed}|null $row */
            $row = DB::table('channel_settlement_evidence')
                ->where('company_id', $companyId)
                ->where('connection_id', $connection->id)
                ->where('external_settlement_id', $externalSettlementId)
                ->lockForUpdate()
                ->first();
            if ($row === null) {
                throw new RuntimeException('Channel settlement evidence could not be persisted.');
            }
            $stored = json_decode((string) $row->evidence, true);
            if (! is_array($stored) || ($stored['fingerprint'] ?? null) !== $hash) {
                throw new DomainException('Channel settlement evidence replay drift detected.');
            }

            return (int) $row->id;
        });
    }

    public function markSettlementHandedOff(int $companyId, string $settlementPublicId): void
    {
        $updated = DB::table('channel_settlement_evidence')
            ->where('company_id', $companyId)
            ->where('public_id', strtoupper(trim($settlementPublicId)))
            ->where('status', 'received')
            ->update([
                'status' => 'handed_off',
                'handed_off_at' => now(),
                'last_error' => null,
                'updated_at' => now(),
            ]);
        if ($updated !== 1) {
            throw new DomainException('Settlement evidence is not available for handoff.');
        }
    }

    /** @return object{id:int,company_id:int,provider:string,base_url:mixed,credentials_ciphertext:mixed,financial_mode:string,default_account_id:mixed,clearing_account_id:mixed} */
    private function connection(int $companyId, string $publicId): object
    {
        $publicId = strtoupper(trim($publicId));
        if (! preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $publicId)) {
            throw new DomainException('Invalid channel connection public id.');
        }
        $connection = DB::table('integration_connections')
            ->where('company_id', $companyId)
            ->where('public_id', $publicId)
            ->where('status', 'active')
            ->first();
        /** @var object{id:int,company_id:int,provider:string,base_url:mixed,credentials_ciphertext:mixed,financial_mode:string,default_account_id:mixed,clearing_account_id:mixed}|null $connection */
        if ($connection === null) {
            throw new DomainException('Active channel connection not found.');
        }

        return $connection;
    }

    private function assertCompanyAccount(int $companyId, int $accountId, ?string $requiredType = null): void
    {
        $query = DB::table('accounts')->where('company_id', $companyId)->where('id', $accountId)->where('status', 'active');
        if ($requiredType !== null) {
            $query->whereIn('type', [$requiredType, 'mixed']);
        }
        if (! $query->exists()) {
            throw new DomainException('Channel account does not belong to company or is inactive.');
        }
    }

    /** @return array<string,mixed> */
    private function credentials(string $ciphertext): array
    {
        if ($ciphertext === '') {
            return [];
        }
        $decoded = json_decode(Crypt::decryptString($ciphertext), true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }

    private function nullableText(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $max) {
            throw new DomainException('Channel mapping value is too long.');
        }

        return $value;
    }

    private function requiredText(string $value, int $max, string $field): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $max) {
            throw new DomainException($field.' is invalid.');
        }

        return $value;
    }

    private function nonNegativeDecimal(string $value, string $field): string
    {
        if (! is_numeric($value)) {
            throw new DomainException('Desired-state '.$field.' is invalid.');
        }
        $row = DB::selectOne('SELECT CAST(CAST(? AS numeric) AS numeric(20,6))::text AS value, CAST(? AS numeric) >= 0 AS valid', [$value, $value]);
        if ($row === null || $row->valid !== true) {
            throw new DomainException('Desired-state '.$field.' is invalid.');
        }

        return (string) $row->value;
    }

    /**
     * @param  list<string>  $urls
     * @return list<string>
     */
    private function mediaUrls(array $urls): array
    {
        if (count($urls) > 20) {
            throw new DomainException('At most 20 media URLs can be published at once.');
        }
        $normalized = [];
        foreach ($urls as $url) {
            $url = trim($url);
            if ($url === '' || mb_strlen($url) > 1024 || filter_var($url, FILTER_VALIDATE_URL) === false) {
                throw new DomainException('Invalid channel media URL.');
            }
            $normalized[] = $url;
        }

        return array_values(array_unique($normalized));
    }
}
