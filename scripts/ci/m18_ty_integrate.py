from pathlib import Path


def replace_between(text: str, start: str, end: str, replacement: str) -> str:
    a = text.index(start)
    b = text.index(end, a)
    return text[:a] + replacement + text[b:]


def require_replace(text: str, old: str, new: str) -> str:
    if old not in text:
        raise RuntimeError(f'missing patch marker: {old[:120]!r}')
    return text.replace(old, new, 1)

# ChannelCenterService
path = Path('app/Modules/Commerce/ChannelCenterService.php')
text = path.read_text()
text = require_replace(
    text,
    "namespace App\\Modules\\Commerce;\n\nuse App\\Modules\\Operations\\ChannelEventStore;",
    "namespace App\\Modules\\Commerce;\n\nuse App\\Modules\\Commerce\\Providers\\Trendyol\\TrendyolClient;\nuse App\\Modules\\Operations\\ChannelEventStore;",
)
text = require_replace(
    text,
    "        private ProviderRegistry $providers,\n    ) {}",
    "        private ProviderRegistry $providers,\n        private TrendyolClient $trendyol,\n    ) {}",
)
text = replace_between(
    text,
    "    public function testConnection(int $companyId, string $connectionPublicId): bool\n",
    "    /** @param array<string,mixed> $metadata */",
    '''    public function testConnection(int $companyId, string $connectionPublicId): bool
    {
        $connection = $this->connection($companyId, $connectionPublicId);
        $provider = (string) $connection->provider;
        if (! $this->providers->supports($provider, 'connection_test')) {
            throw new DomainException('Provider does not support connection testing.');
        }

        try {
            $credentials = $this->credentials((string) ($connection->credentials_ciphertext ?? ''));
            if ($provider === 'woocommerce') {
                $baseUrl = rtrim((string) ($connection->base_url ?? ''), '/');
                $key = (string) ($credentials['consumer_key'] ?? '');
                $secret = (string) ($credentials['consumer_secret'] ?? '');
                if ($baseUrl === '' || $key === '' || $secret === '') {
                    throw new DomainException('WooCommerce connection settings are incomplete.');
                }
                $response = Http::acceptJson()
                    ->withBasicAuth($key, $secret)
                    ->timeout(15)
                    ->get($baseUrl.'/wp-json/wc/v3/system_status');
                $label = 'WooCommerce';
            } elseif ($provider === 'trendyol') {
                $response = $this->trendyol->connectionTest($credentials);
                $label = 'Trendyol';
            } else {
                throw new DomainException('Connection test is not implemented for provider.');
            }
            if ($response->status() === 429) {
                throw new DomainException($label.' connection test is rate limited.');
            }
            if (! $response->successful()) {
                throw new RuntimeException($label.' connection test returned HTTP '.$response->status().'.');
            }

            DB::table('integration_connections')->where('id', $connection->id)->update([
                'connection_test_status' => 'ok',
                'connection_tested_at' => now(),
                'connection_test_message' => null,
                'updated_at' => now(),
            ]);

            return true;
        } catch (\\Throwable $exception) {
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

''',
)
text = replace_between(
    text,
    "    /**\n     * @param  list<string>  $mediaUrls\n     * @return array{state_id:int,effect_id:int,version:int}\n     */\n    public function queueDesiredState",
    "    public function retryOrder",
    '''    /**
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
        /** @var object{id:int,connection_id:int,external_product_id:mixed,external_sku:mixed,metadata:mixed}|null $mapping */
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

        $provider = (string) $connection->provider;
        $externalProductId = trim((string) ($mapping->external_product_id ?? ''));
        $stock = $stock === null ? null : $this->nonNegativeDecimal($stock, 'stock');
        $price = $price === null ? null : $this->nonNegativeDecimal($price, 'price');
        $currencyCode = $currencyCode === null ? null : strtoupper(trim($currencyCode));
        if ($currencyCode !== null && ! preg_match('/^[A-Z]{3}$/', $currencyCode)) {
            throw new DomainException('Invalid desired-state currency code.');
        }
        $mediaUrls = $this->mediaUrls($mediaUrls);
        if ($stock === null && $price === null && $mediaUrls === []) {
            throw new DomainException('Desired-state publish requires stock, price, or media.');
        }

        $barcode = null;
        if ($provider === 'woocommerce') {
            if (! $this->providers->supports($provider, 'product_publish')) {
                throw new DomainException('Provider does not support product publishing.');
            }
            if ($externalProductId === '') {
                throw new DomainException('Publishing requires an external product id mapping.');
            }
        } elseif ($provider === 'trendyol') {
            if ($mediaUrls !== []) {
                throw new DomainException('Trendyol media publishing is manual until a complete Product V2 content payload is supplied.');
            }
            if ($stock !== null && ! $this->providers->supports($provider, 'stock_publish')) {
                throw new DomainException('Provider does not support stock publishing.');
            }
            if ($price !== null && ! $this->providers->supports($provider, 'price_publish')) {
                throw new DomainException('Provider does not support price publishing.');
            }
            if ($price !== null && $currencyCode !== null && $currencyCode !== 'TRY') {
                throw new DomainException('Trendyol Türkiye price publishing requires TRY.');
            }
            if ($stock !== null && (preg_match('/^\\d+\\.0{6}$/', $stock) !== 1 || (int) $stock > 20000)) {
                throw new DomainException('Trendyol sellable stock must be an integer between 0 and 20000.');
            }
            $metadata = json_decode((string) ($mapping->metadata ?? ''), true);
            $barcode = is_array($metadata) && isset($metadata['barcode']) && is_scalar($metadata['barcode'])
                ? trim((string) $metadata['barcode'])
                : '';
            if ($barcode === '') {
                throw new DomainException('Trendyol stock/price publishing requires mapping metadata.barcode.');
            }
        } else {
            throw new DomainException('Desired-state publishing is not implemented for provider.');
        }

        return DB::transaction(function () use ($companyId, $connection, $mapping, $provider, $externalProductId, $barcode, $stock, $price, $currencyCode, $mediaUrls): array {
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

            if ($provider === 'woocommerce') {
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
                $operation = 'product';
                $entityId = $externalProductId;
            } else {
                $item = ['barcode' => $barcode];
                if ($stock !== null) {
                    $item['quantity'] = (int) $stock;
                }
                if ($price !== null) {
                    $item['salePrice'] = (float) $price;
                    $item['listPrice'] = (float) $price;
                }
                $payload = ['items' => [$item]];
                $operation = $stock !== null ? 'stock' : 'price';
                $entityId = (string) $barcode;
            }

            $effectId = $this->channels->scheduleSync(
                $companyId,
                (int) $connection->id,
                $operation,
                'product',
                $entityId,
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

''',
)
text = replace_between(
    text,
    "    /** @return list<int> */\n    public function pollOrders",
    "    public function queueInvoiceSync",
    '''    /** @return list<int> */
    public function pollOrders(int $companyId, string $connectionPublicId, ?string $modifiedAfter = null, int $page = 1, int $perPage = 50): array
    {
        $connection = $this->connection($companyId, $connectionPublicId);
        $provider = (string) $connection->provider;
        if (! $this->providers->supports($provider, 'order_polling')) {
            throw new DomainException('Provider does not support order polling.');
        }
        if ($page < 1 || $perPage < 1 || $perPage > 100) {
            throw new DomainException('Invalid polling page window.');
        }
        $credentials = $this->credentials((string) ($connection->credentials_ciphertext ?? ''));

        if ($provider === 'woocommerce') {
            $baseUrl = rtrim((string) ($connection->base_url ?? ''), '/');
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
                } catch (\\Throwable) {
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

        if ($provider !== 'trendyol') {
            throw new DomainException('Order polling is not implemented for provider.');
        }
        if (($page - 1) * $perPage >= 10000) {
            throw new DomainException('Trendyol V2 polling window cannot exceed 10000 shipment packages.');
        }
        $query = [
            'page' => $page - 1,
            'size' => $perPage,
            'orderByField' => 'PackageLastModifiedDate',
            'orderByDirection' => 'ASC',
        ];
        if ($modifiedAfter !== null && trim($modifiedAfter) !== '') {
            try {
                $start = CarbonImmutable::parse($modifiedAfter);
                $end = CarbonImmutable::now($start->getTimezone());
            } catch (\\Throwable) {
                throw new DomainException('Polling modified-after value is invalid.');
            }
            if ($start->isFuture() || $start->diffInDays($end) > 14) {
                throw new DomainException('Trendyol V2 polling date range must be within fourteen days.');
            }
            $query['startDate'] = $start->getTimestampMs();
            $query['endDate'] = $end->getTimestampMs();
        }
        $response = $this->trendyol->ordersV2($credentials, $query);
        if ($response->status() === 429) {
            throw new DomainException('Trendyol V2 polling is rate limited.');
        }
        if (! $response->successful()) {
            throw new RuntimeException('Trendyol V2 polling returned HTTP '.$response->status().'.');
        }
        $body = $response->json();
        $orders = is_array($body) ? ($body['content'] ?? null) : null;
        if (! is_array($orders) || ! array_is_list($orders)) {
            throw new RuntimeException('Trendyol V2 polling response must contain a content list.');
        }
        $eventIds = [];
        foreach ($orders as $order) {
            if (! is_array($order)) {
                throw new RuntimeException('Trendyol V2 polling returned an invalid shipment package.');
            }
            $packageId = isset($order['shipmentPackageId']) && is_scalar($order['shipmentPackageId']) ? (string) $order['shipmentPackageId'] : '';
            $orderNumber = isset($order['orderNumber']) && is_scalar($order['orderNumber']) ? (string) $order['orderNumber'] : '';
            if ($packageId === '' && $orderNumber === '') {
                throw new RuntimeException('Trendyol V2 polling returned a package without identity.');
            }
            $modified = isset($order['lastModifiedDate']) && is_scalar($order['lastModifiedDate']) ? (string) $order['lastModifiedDate'] : '0';
            $status = isset($order['status']) && is_scalar($order['status']) ? strtolower((string) $order['status']) : 'updated';
            $externalEventId = 'ty-poll-'.($packageId !== '' ? $packageId : $orderNumber).'-'.$modified.'-'.$status;
            $eventIds[] = $this->events->persist($connection, $externalEventId, 'order.'.$status, $order);
        }

        return $eventIds;
    }

''',
)
path.write_text(text)

# ChannelService
path = Path('app/Modules/Operations/ChannelService.php')
text = path.read_text()
text = require_replace(
    text,
    "namespace App\\Modules\\Operations;\n\nuse App\\Modules\\Operations\\Jobs\\ProcessIntegrationSync;",
    "namespace App\\Modules\\Operations;\n\nuse App\\Modules\\Commerce\\Providers\\Trendyol\\TrendyolClient;\nuse App\\Modules\\Operations\\Jobs\\ProcessIntegrationSync;",
)
text = require_replace(
    text,
    "    public function __construct(private readonly ChannelEventStore $events) {}",
    "    public function __construct(\n        private readonly ChannelEventStore $events,\n        private readonly TrendyolClient $trendyol,\n    ) {}",
)
text = require_replace(
    text,
    "/** @var object{id:mixed,company_id:mixed,status:mixed,webhook_secret_ciphertext:mixed,provider:mixed}|null $connection */",
    "/** @var object{id:mixed,company_id:mixed,status:mixed,webhook_secret_ciphertext:mixed,credentials_ciphertext:mixed,provider:mixed}|null $connection */",
)
old = '''        $secret = Crypt::decryptString($secretCiphertext);
        if (! $this->signatureMatches((string) $connection->provider, $rawPayload, $signature, $secret)) {
            throw new DomainException('Integration webhook signature is invalid.');
        }

        $payload = json_decode($rawPayload, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($payload)) {
            throw new DomainException('Integration payload must be a JSON object or array.');
        }

        return $this->events->persist($connection, $externalEventId, $eventType, $payload);
'''
new = '''        $secret = Crypt::decryptString($secretCiphertext);
        $credentials = $this->decryptCredentials((string) ($connection->credentials_ciphertext ?? ''));
        if (! $this->signatureMatches((string) $connection->provider, $rawPayload, $signature, $secret, $credentials)) {
            throw new DomainException('Integration webhook signature is invalid.');
        }

        $payload = json_decode($rawPayload, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($payload)) {
            throw new DomainException('Integration payload must be a JSON object or array.');
        }
        if ((string) $connection->provider === 'trendyol' && array_key_exists('content', $payload)) {
            $content = $payload['content'];
            if (! is_array($content) || ! array_is_list($content) || count($content) !== 1 || ! is_array($content[0])) {
                throw new DomainException('Trendyol webhook must contain exactly one shipment package.');
            }
            $payload = $content[0];
            $status = isset($payload['status']) && is_scalar($payload['status']) ? strtolower((string) $payload['status']) : 'updated';
            $eventType = 'order.'.$status;
            if (trim($externalEventId) === '') {
                $packageId = isset($payload['shipmentPackageId']) && is_scalar($payload['shipmentPackageId']) ? (string) $payload['shipmentPackageId'] : '';
                $modified = isset($payload['lastModifiedDate']) && is_scalar($payload['lastModifiedDate']) ? (string) $payload['lastModifiedDate'] : '0';
                if ($packageId !== '') {
                    $externalEventId = 'ty-webhook-'.$packageId.'-'.$modified.'-'.$status;
                }
            }
        }

        return $this->events->persist($connection, $externalEventId, $eventType, $payload);
'''
text = require_replace(text, old, new)
sending_marker = '''            DB::table('integration_sync_effects')->where('id', $effectId)->update([
                'status' => 'sending',
'''
cooldown = '''            $provider = (string) DB::table('integration_connections')
                ->where('company_id', $effect->company_id)
                ->where('id', $effect->connection_id)
                ->value('provider');
            if ($provider === 'trendyol' && in_array((string) $effect->operation, ['stock', 'price'], true)) {
                $duplicate = DB::table('integration_sync_effects')
                    ->where('connection_id', $effect->connection_id)
                    ->where('payload_sha256', $effect->payload_sha256)
                    ->where('status', 'succeeded')
                    ->where('completed_at', '>=', now()->subMinutes(15))
                    ->exists();
                if ($duplicate) {
                    DB::table('integration_sync_effects')->where('id', $effectId)->update([
                        'status' => 'ignored',
                        'completed_at' => now(),
                        'ignored_reason' => 'trendyol duplicate stock-price cooldown',
                        'last_error' => null,
                        'updated_at' => now(),
                    ]);
                    if ((string) ($effect->guard_type ?? '') === 'listing_state' && $effect->guard_id !== null && $effect->guard_version !== null) {
                        $state = DB::table('channel_listing_states')
                            ->where('id', (int) $effect->guard_id)
                            ->where('desired_version', (int) $effect->guard_version)
                            ->first(['desired_stock', 'desired_price', 'desired_currency_code', 'desired_media']);
                        if ($state !== null) {
                            DB::table('channel_listing_states')->where('id', (int) $effect->guard_id)->update([
                                'published_version' => (int) $effect->guard_version,
                                'published_stock' => $state->desired_stock,
                                'published_price' => $state->desired_price,
                                'published_currency_code' => $state->desired_currency_code,
                                'published_media' => $state->desired_media,
                                'status' => 'synced',
                                'last_error' => null,
                                'updated_at' => now(),
                            ]);
                        }
                    }

                    return null;
                }
            }

'''
text = require_replace(text, sending_marker, cooldown + sending_marker)
text = require_replace(
    text,
    "$externalId = is_array($responseData) ? ($responseData['id'] ?? $responseData['shipmentPackageId'] ?? null) : null;",
    "$externalId = is_array($responseData) ? ($responseData['batchRequestId'] ?? $responseData['id'] ?? $responseData['shipmentPackageId'] ?? null) : null;",
)
text = replace_between(
    text,
    "    private function signatureMatches(string $provider, string $payload, string $signature, string $secret): bool\n",
    "    /** @return array<string,mixed> */\n    private function decryptCredentials",
    '''    /** @param array<string,mixed> $credentials */
    private function signatureMatches(string $provider, string $payload, string $signature, string $secret, array $credentials): bool
    {
        $signature = trim($signature);
        if ($provider === 'woocommerce') {
            return hash_equals(base64_encode(hash_hmac('sha256', $payload, $secret, true)), $signature);
        }
        if ($provider === 'trendyol') {
            $authType = strtoupper(trim((string) ($credentials['webhook_authentication_type'] ?? 'API_KEY')));
            if ($authType === 'API_KEY') {
                return $signature !== '' && hash_equals($secret, $signature);
            }
            if ($authType === 'BASIC_AUTHENTICATION') {
                $username = trim((string) ($credentials['webhook_username'] ?? ''));
                $password = (string) ($credentials['webhook_password'] ?? '');
                if ($username === '' || $password === '') {
                    return false;
                }

                return hash_equals('Basic '.base64_encode($username.':'.$password), $signature);
            }

            return false;
        }
        $signature = str_starts_with($signature, 'sha256=') ? substr($signature, 7) : $signature;

        return hash_equals(hash_hmac('sha256', $payload, $secret), strtolower($signature));
    }

''',
)
old_sender = '''        if ($provider === 'trendyol') {
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
'''
new_sender = '''        if ($provider === 'trendyol') {
            return match ($operation) {
                'stock', 'price' => $this->trendyol->updatePriceAndInventory($credentials, $payload),
                default => throw new RuntimeException('Unsupported Trendyol sync operation.'),
            };
        }
'''
text = require_replace(text, old_sender, new_sender)
path.write_text(text)

# Webhook controller
path = Path('app/Modules/Operations/Http/ChannelWebhookController.php')
text = path.read_text()
old = '''        $provider = (string) $row->provider;
        $signature = $provider === 'woocommerce'
            ? $this->headerString($request, 'X-WC-Webhook-Signature')
            : ($this->headerString($request, 'X-Mars-Signature') ?: $this->headerString($request, 'X-Trendyol-Signature'));
        $eventType = $provider === 'woocommerce'
            ? ($this->headerString($request, 'X-WC-Webhook-Topic') ?: $this->inputString($request, 'event_type', 'unknown'))
            : ($this->headerString($request, 'X-Trendyol-Event') ?: $this->inputString($request, 'event_type', 'unknown'));
        $externalId = $provider === 'woocommerce'
            ? ($this->headerString($request, 'X-WC-Webhook-Delivery-ID') ?: $this->headerString($request, 'X-WC-Webhook-ID'))
            : ($this->headerString($request, 'X-Trendyol-Event-ID') ?: $this->inputString($request, 'event_id'));
'''
new = '''        $provider = (string) $row->provider;
        $signature = match ($provider) {
            'woocommerce' => $this->headerString($request, 'X-WC-Webhook-Signature'),
            'trendyol' => $this->headerString($request, 'x-api-key') ?: $this->headerString($request, 'Authorization'),
            default => $this->headerString($request, 'X-Mars-Signature'),
        };
        $eventType = $provider === 'woocommerce'
            ? ($this->headerString($request, 'X-WC-Webhook-Topic') ?: $this->inputString($request, 'event_type', 'unknown'))
            : ($provider === 'trendyol' ? 'order.updated' : $this->inputString($request, 'event_type', 'unknown'));
        $externalId = $provider === 'woocommerce'
            ? ($this->headerString($request, 'X-WC-Webhook-Delivery-ID') ?: $this->headerString($request, 'X-WC-Webhook-ID'))
            : ($provider === 'trendyol' ? '' : $this->inputString($request, 'event_id'));
'''
text = require_replace(text, old, new)
path.write_text(text)

# Registry capabilities: distinguish operational vs contract/manual seams.
path = Path('config/commerce.php')
text = path.read_text()
old = '''            'capabilities' => [
                'connection_test',
                'category_lookup',
                'attribute_lookup',
                'product_mapping',
                'product_publish',
                'stock_publish',
                'price_publish',
                'media_publish',
                'order_webhook',
                'order_polling',
                'order_cancel',
                'return_evidence',
                'return_create',
                'questions',
                'invoice_publish',
                'settlement_evidence',
                'webhook_registration',
            ],
'''
new = '''            'capabilities' => [
                'connection_test',
                'category_lookup',
                'attribute_lookup',
                'product_mapping',
                'product_contract',
                'stock_publish',
                'price_publish',
                'media_manual',
                'order_webhook',
                'order_polling',
                'order_cancel_contract',
                'return_evidence',
                'return_create_contract',
                'questions_contract',
                'invoice_contract',
                'settlement_evidence',
                'settlement_contract',
                'webhook_registration_contract',
            ],
'''
text = require_replace(text, old, new)
path.write_text(text)

# Legacy M11 Trendyol domain fixture now uses the official API_KEY callback contract.
path = Path('tests/Integration/Operations/M11TrendyolDomainSyncTest.php')
text = path.read_text()
text = require_replace(
    text,
    "            'default_account_id' => (int) $customer->getKey(),\n            'price_basis' => 'gross',",
    "            'seller_id' => '99999',\n            'api_key' => 'm11-api-key',\n            'api_secret' => 'm11-api-secret',\n            'integration_name' => 'MarsOtomasyon',\n            'environment' => 'stage',\n            'webhook_authentication_type' => 'API_KEY',\n            'default_account_id' => (int) $customer->getKey(),\n            'price_basis' => 'gross',",
)
text = require_replace(
    text,
    "    $signature = hash_hmac('sha256', $raw, 'm11-trendyol-webhook-secret');",
    "    $signature = 'm11-trendyol-webhook-secret';",
)
path.write_text(text)

# Integration acceptance fixture.
Path('tests/Integration/Commerce/M18TrendyolTest.php').write_text(r'''<?php

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Commerce\ChannelCenterService;
use App\Modules\Commerce\ProviderRegistry;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Tax;
use App\Modules\Operations\ChannelService;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(DatabaseMigrations::class);

it('runs Trendyol V2 connection stock price cooldown polling and API key webhook seams', function (): void {
    Queue::fake();
    $priceCalls = 0;
    $pricePayload = null;
    $orderPayload = m18TrendyolOrder('TY-9001', 3330111111, 1788100000000, 'Created');

    Http::fake(function (Request $request) use (&$priceCalls, &$pricePayload, $orderPayload) {
        $url = $request->url();
        if (str_ends_with($url, '/integration/sellers/99999/addresses')) {
            return Http::response(['addresses' => []], 200);
        }
        if (str_contains($url, '/integration/inventory/sellers/99999/products/price-and-inventory')) {
            $priceCalls++;
            $pricePayload = $request->data();

            return Http::response(['batchRequestId' => 'ty-batch-1'], 200);
        }
        if (str_contains($url, '/integration/order/sellers/99999/v2/orders')) {
            return Http::response([
                'totalElements' => 1,
                'totalPages' => 1,
                'page' => 0,
                'size' => 50,
                'content' => [$orderPayload],
            ], 200);
        }

        return Http::response(['unexpected' => $url], 500);
    });

    [$company, $customer, $product] = m18TrendyolFixture();
    app(ActiveCompanyContext::class)->set($company);
    $commerce = app(ChannelCenterService::class);
    $channels = app(ChannelService::class);
    $registry = app(ProviderRegistry::class);

    $connectionPublicId = $commerce->createConnection(
        companyId: (int) $company->getKey(),
        provider: 'trendyol',
        name: 'Trendyol Stage',
        baseUrl: null,
        credentials: [
            'seller_id' => '99999',
            'api_key' => 'ty-api-key',
            'api_secret' => 'ty-api-secret',
            'integration_name' => 'MarsOtomasyon',
            'storefront_code' => 'TR',
            'environment' => 'stage',
            'webhook_authentication_type' => 'API_KEY',
            'default_account_id' => (int) $customer->getKey(),
            'price_basis' => 'gross',
            'order_series' => 'trendyol',
        ],
        webhookSecret: 'ty-callback-api-key',
        financialMode: 'direct_account',
        defaultAccountId: (int) $customer->getKey(),
    );

    expect($registry->get('trendyol')['status'])->toBe('contract_verified')
        ->and($registry->isMarketplaceVerified('trendyol'))->toBeFalse()
        ->and($registry->supports('trendyol', 'stock_publish'))->toBeTrue()
        ->and($registry->supports('trendyol', 'media_manual'))->toBeTrue()
        ->and($registry->supports('trendyol', 'invoice_publish'))->toBeFalse();

    expect($commerce->testConnection((int) $company->getKey(), $connectionPublicId))->toBeTrue();
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/integration/sellers/99999/addresses')
        && str_contains((string) ($request->header('User-Agent')[0] ?? ''), '99999 - MarsOtomasyon')
        && ($request->header('storeFrontCode')[0] ?? null) === 'TR');

    $mappingPublicId = $commerce->mapProduct(
        (int) $company->getKey(),
        $connectionPublicId,
        (int) $product->getKey(),
        '123456789',
        null,
        'TY-STOCK-1',
        ['barcode' => '8680000000001'],
    );

    expect(fn () => $commerce->queueDesiredState(
        (int) $company->getKey(),
        $mappingPublicId,
        '7',
        '125',
        'TRY',
        ['https://cdn.example.test/ty.jpg'],
    ))->toThrow(DomainException::class, 'Trendyol media publishing is manual');

    expect(fn () => $commerce->queueDesiredState(
        (int) $company->getKey(),
        $mappingPublicId,
        '7',
        '125',
        'EUR',
    ))->toThrow(DomainException::class, 'requires TRY');

    $first = $commerce->queueDesiredState((int) $company->getKey(), $mappingPublicId, '7', '125', 'TRY');
    $channels->processSync($first['effect_id']);
    expect(DB::table('integration_sync_effects')->where('id', $first['effect_id'])->value('status'))->toBe('succeeded')
        ->and(DB::table('integration_sync_effects')->where('id', $first['effect_id'])->value('external_id'))->toBe('ty-batch-1')
        ->and($priceCalls)->toBe(1)
        ->and($pricePayload)->toBe([
            'items' => [[
                'barcode' => '8680000000001',
                'quantity' => 7,
                'salePrice' => 125.0,
                'listPrice' => 125.0,
            ]],
        ]);

    $second = $commerce->queueDesiredState((int) $company->getKey(), $mappingPublicId, '7', '125', 'TRY');
    $channels->processSync($second['effect_id']);
    expect(DB::table('integration_sync_effects')->where('id', $second['effect_id'])->value('status'))->toBe('ignored')
        ->and(DB::table('integration_sync_effects')->where('id', $second['effect_id'])->value('ignored_reason'))->toBe('trendyol duplicate stock-price cooldown')
        ->and($priceCalls)->toBe(1)
        ->and((int) DB::table('channel_listing_states')->where('id', $second['state_id'])->value('published_version'))->toBe(2);

    $polled = $commerce->pollOrders((int) $company->getKey(), $connectionPublicId, null, 1, 50);
    expect($polled)->toHaveCount(1);
    $pollEvent = DB::table('integration_events')->where('id', $polled[0])->first();
    $pollBody = json_decode((string) $pollEvent->payload, true, flags: JSON_THROW_ON_ERROR);
    expect((string) $pollEvent->event_type)->toBe('order.created')
        ->and($pollBody['shipmentPackageId'])->toBe(3330111111)
        ->and($pollBody['orderNumber'])->toBe('TY-9001');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/integration/order/sellers/99999/v2/orders')
        && (int) ($request->data()['page'] ?? -1) === 0
        && (int) ($request->data()['size'] ?? 0) === 50);

    $connectionId = (int) DB::table('integration_connections')->where('public_id', $connectionPublicId)->value('id');
    $webhookOrder = m18TrendyolOrder('TY-9002', 3330111112, 1788100001000, 'Picking');
    $wrapper = [
        'totalElements' => 1,
        'totalPages' => 1,
        'page' => 0,
        'size' => 1,
        'content' => [$webhookOrder],
    ];
    $raw = json_encode($wrapper, JSON_THROW_ON_ERROR);
    $webhookId = $channels->ingestWebhook($connectionId, '', 'order.updated', $raw, 'ty-callback-api-key');
    $webhookReplay = $channels->ingestWebhook($connectionId, '', 'order.updated', $raw, 'ty-callback-api-key');
    $webhookEvent = DB::table('integration_events')->where('id', $webhookId)->first();
    $webhookBody = json_decode((string) $webhookEvent->payload, true, flags: JSON_THROW_ON_ERROR);
    expect($webhookReplay)->toBe($webhookId)
        ->and((string) $webhookEvent->external_event_id)->toBe('ty-webhook-3330111112-1788100001000-picking')
        ->and((string) $webhookEvent->event_type)->toBe('order.picking')
        ->and($webhookBody['orderNumber'])->toBe('TY-9002')
        ->and($webhookBody)->not->toHaveKey('content');

    expect(fn () => $channels->ingestWebhook($connectionId, '', 'order.updated', $raw, 'wrong-key'))
        ->toThrow(DomainException::class, 'signature is invalid');
});

/** @return array{Company, Account, Product} */
function m18TrendyolFixture(): array
{
    $company = Company::query()->create(['code' => 'M18-TY', 'name' => 'M18 Trendyol']);
    $customer = Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'M18-TY-CUSTOMER',
        'type' => AccountType::Customer,
        'status' => AccountStatus::Active,
        'legal_name' => 'M18 Trendyol Customer',
        'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null,
        'tax_office' => null,
        'book_currency_code' => 'TRY',
        'due_days' => 0,
        'discount_rate' => '0.000000',
        'risk_limit' => '0.000000',
    ]);
    $category = Category::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'M18-TY-CAT',
        'name' => 'M18 Trendyol',
        'is_active' => true,
    ]);
    $unit = Unit::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'ADET',
        'name' => 'Adet',
        'is_active' => true,
    ]);
    $tax = Tax::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'KDV20',
        'name' => 'KDV %20',
        'rate' => '20.000000',
        'is_active' => true,
    ]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'M18-TY-SKU',
        'status' => ProductStatus::Active,
        'name' => 'M18 Trendyol Product',
        'category_id' => $category->getKey(),
        'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(),
        'sale_price_net' => '125.000000',
        'purchase_price_net' => '80.000000',
    ]);

    return [$company, $customer, $product];
}

/** @return array<string,mixed> */
function m18TrendyolOrder(string $orderNumber, int $packageId, int $lastModifiedDate, string $status): array
{
    return [
        'orderNumber' => $orderNumber,
        'shipmentPackageId' => $packageId,
        'lastModifiedDate' => $lastModifiedDate,
        'status' => $status,
        'shipmentPackageStatus' => $status,
        'orderDate' => 1788099000000,
        'currencyCode' => 'TRY',
        'customerFirstName' => 'Ada',
        'customerLastName' => 'Lovelace',
        'customerEmail' => 'ada@example.test',
        'lines' => [[
            'lineId' => 7001,
            'stockCode' => 'TY-STOCK-1',
            'barcode' => '8680000000001',
            'productName' => 'M18 Trendyol Product',
            'quantity' => 1,
            'salePrice' => 125.0,
            'lineGrossAmount' => 125.0,
            'lineTotalDiscount' => 0.0,
        ]],
    ];
}
''')
