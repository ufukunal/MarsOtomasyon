from pathlib import Path


def replace_once(path: str, old: str, new: str, label: str) -> None:
    p = Path(path)
    text = p.read_text()
    if old not in text:
        raise SystemExit(f"{label}: pattern not found")
    p.write_text(text.replace(old, new, 1))


# ChannelService: shared event persistence, rate-limit handling, ambiguous outcomes,
# and invoice-sync projection.
p = Path("app/Modules/Operations/ChannelService.php")
s = p.read_text()
if "use Illuminate\\Http\\Client\\ConnectionException;" not in s:
    s = s.replace(
        "use Illuminate\\Http\\Client\\Response;\n",
        "use Illuminate\\Http\\Client\\ConnectionException;\nuse Illuminate\\Http\\Client\\Response;\n",
        1,
    )
old = "final class ChannelService\n{\n"
new = "final class ChannelService\n{\n    public function __construct(private readonly ChannelEventStore $events) {}\n\n"
if old not in s:
    raise SystemExit("ChannelService constructor marker not found")
s = s.replace(old, new, 1)
start = s.index(
    "        $eventType = $this->canonicalEventType($eventType);",
    s.index("public function ingestWebhook"),
)
end_marker = "    /** @param array<string,mixed> $payload */\n    public function scheduleSync"
end = s.index(end_marker, start)
s = (
    s[:start]
    + "        return $this->events->persist($connection, $externalEventId, $eventType, $payload);\n    }\n\n"
    + s[end:]
)
old = """            if (! $response->successful()) {
                throw new RuntimeException('Provider returned HTTP '.$response->status().': '.mb_substr($response->body(), 0, 1000));
            }
"""
new = """            if ($response->status() === 429) {
                $retryAfter = (int) $response->header('Retry-After');
                $retryAfter = max(60, min($retryAfter > 0 ? $retryAfter : 300, 3600));
                throw new ProviderRateLimitException('Provider rate limit exceeded.', $retryAfter);
            }
            if (! $response->successful()) {
                throw new RuntimeException('Provider returned HTTP '.$response->status().': '.mb_substr($response->body(), 0, 1000));
            }
"""
if old not in s:
    raise SystemExit("ChannelService response status marker not found")
s = s.replace(old, new, 1)
old = """            DB::table('integration_connections')
                ->where('company_id', $effect->company_id)
                ->where('id', $effect->connection_id)
                ->update([
                    'last_sync_at' => now(),
                    'last_success_at' => now(),
                    'last_error' => null,
                    'updated_at' => now(),
                ]);
        } catch (\\Throwable $exception) {
"""
new = """            DB::table('channel_invoice_syncs')->where('sync_effect_id', $effectId)->update([
                'status' => 'synced',
                'synced_at' => now(),
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
        } catch (ConnectionException $exception) {
            $message = 'Ambiguous provider outcome after connection failure: '.mb_substr($exception->getMessage(), 0, 3800);
            DB::table('integration_sync_effects')->where('id', $effectId)->where('status', 'sending')->update([
                'status' => 'ambiguous',
                'last_error' => $message,
                'updated_at' => now(),
            ]);
            DB::table('channel_invoice_syncs')->where('sync_effect_id', $effectId)->update([
                'status' => 'failed',
                'last_error' => $message,
                'updated_at' => now(),
            ]);
            if ((string) ($effect->guard_type ?? '') === 'listing_state' && $effect->guard_id !== null && $effect->guard_version !== null) {
                DB::table('channel_listing_states')
                    ->where('id', (int) $effect->guard_id)
                    ->where('desired_version', (int) $effect->guard_version)
                    ->update([
                        'status' => 'failed',
                        'last_error' => $message,
                        'updated_at' => now(),
                    ]);
            }
            DB::table('integration_connections')
                ->where('company_id', $effect->company_id)
                ->where('id', $effect->connection_id)
                ->update([
                    'last_error_at' => now(),
                    'last_error' => $message,
                    'updated_at' => now(),
                ]);
        } catch (\\Throwable $exception) {
"""
if old not in s:
    raise SystemExit("ChannelService success/catch marker not found")
s = s.replace(old, new, 1)
old = """            DB::table('integration_connections')
                ->where('company_id', $effect->company_id)
                ->where('id', $effect->connection_id)
                ->update([
                    'last_error_at' => now(),
                    'last_error' => mb_substr($exception->getMessage(), 0, 4000),
                    'updated_at' => now(),
                ]);
            throw $exception;
"""
new = """            DB::table('channel_invoice_syncs')->where('sync_effect_id', $effectId)->update([
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
"""
if old not in s:
    raise SystemExit("ChannelService failure marker not found")
s = s.replace(old, new, 1)
p.write_text(s)

# ProcessIntegrationSync: Retry-After contract.
replace_once(
    "app/Modules/Operations/Jobs/ProcessIntegrationSync.php",
    "use App\\Modules\\Operations\\ChannelService;\n",
    "use App\\Modules\\Operations\\ChannelService;\nuse App\\Modules\\Operations\\ProviderRateLimitException;\n",
    "ProcessIntegrationSync import",
)
replace_once(
    "app/Modules/Operations/Jobs/ProcessIntegrationSync.php",
    """    public function handle(ChannelService $channels): void
    {
        $channels->processSync($this->effectId);
    }
""",
    """    public function handle(ChannelService $channels): void
    {
        try {
            $channels->processSync($this->effectId);
        } catch (ProviderRateLimitException $exception) {
            $this->release($exception->retryAfterSeconds);
        }
    }
""",
    "ProcessIntegrationSync handle",
)

# ChannelCenterService: polling through the same ChannelEventStore and invoice sync.
replace_once(
    "app/Modules/Commerce/ChannelCenterService.php",
    "use App\\Modules\\Operations\\ChannelService;\n",
    "use App\\Modules\\Operations\\ChannelEventStore;\nuse App\\Modules\\Operations\\ChannelService;\n",
    "ChannelCenterService event store import",
)
replace_once(
    "app/Modules/Commerce/ChannelCenterService.php",
    """    public function __construct(
        private ChannelService $channels,
        private ProviderRegistry $providers,
    ) {}
""",
    """    public function __construct(
        private ChannelService $channels,
        private ChannelEventStore $events,
        private ProviderRegistry $providers,
    ) {}
""",
    "ChannelCenterService constructor",
)
methods = r'''    /** @return list<int> */
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

'''
replace_once(
    "app/Modules/Commerce/ChannelCenterService.php",
    "    /** @param array<string,mixed> $evidence */\n    public function recordReturnEvidence(",
    methods + "    /** @param array<string,mixed> $evidence */\n    public function recordReturnEvidence(",
    "ChannelCenterService capability methods",
)

# Commerce controller data + actions.
replace_once(
    "app/Modules/Commerce/Http/CommerceController.php",
    "            'settlements' => DB::table('channel_settlement_evidence as s')\n",
    """            'finalizedInvoices' => DB::table('sales_invoices')->where('company_id', $companyId)->where('status', 'finalized')->latest('id')->limit(200)->get(['id', 'number', 'invoice_date', 'gross_total']),
            'invoiceSyncs' => DB::table('channel_invoice_syncs as i')
                ->join('integration_connections as c', 'c.id', '=', 'i.connection_id')
                ->join('sales_invoices as si', 'si.id', '=', 'i.sales_invoice_id')
                ->where('i.company_id', $companyId)->latest('i.id')->limit(50)
                ->get(['i.public_id', 'i.external_order_id', 'i.status', 'i.synced_at', 'i.last_error', 'c.name as connection_name', 'si.number as invoice_number']),
            'settlements' => DB::table('channel_settlement_evidence as s')
""",
    "CommerceController index capability data",
)
controller_methods = r'''    public function pollOrders(Request $request, ChannelCenterService $commerce): RedirectResponse
    {
        $validated = $request->validate([
            'connection_public_id' => ['required', 'string', 'size:26'],
            'modified_after' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);
        $eventIds = $commerce->pollOrders(
            $this->companyId(),
            (string) $validated['connection_public_id'],
            isset($validated['modified_after']) ? (string) $validated['modified_after'] : null,
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 50),
        );

        return back()->with('status', count($eventIds).' WooCommerce siparişi ortak inbox hattına alındı.');
    }

    public function queueInvoice(Request $request, ChannelCenterService $commerce): RedirectResponse
    {
        $validated = $request->validate([
            'connection_public_id' => ['required', 'string', 'size:26'],
            'sales_invoice_id' => ['required', 'integer', 'min:1'],
            'external_order_id' => ['required', 'string', 'max:192'],
        ]);
        $commerce->queueInvoiceSync(
            $this->companyId(),
            (string) $validated['connection_public_id'],
            (int) $validated['sales_invoice_id'],
            (string) $validated['external_order_id'],
        );

        return back()->with('status', 'Fatura kanal senkronizasyonuna alındı.');
    }

'''
replace_once(
    "app/Modules/Commerce/Http/CommerceController.php",
    "    public function retryOrder(string $order, ChannelCenterService $commerce): RedirectResponse\n",
    controller_methods + "    public function retryOrder(string $order, ChannelCenterService $commerce): RedirectResponse\n",
    "CommerceController capability actions",
)

# Routes.
replace_once(
    "routes/operations.php",
    """        Route::post('/listing-state', [CommerceController::class, 'queueListing'])->name('listing-state.store');
        Route::post('/orders/{order}/retry', [CommerceController::class, 'retryOrder'])->where('order', '[0-9A-HJKMNP-TV-Z]{26}')->name('orders.retry');
""",
    """        Route::post('/listing-state', [CommerceController::class, 'queueListing'])->name('listing-state.store');
        Route::post('/poll-orders', [CommerceController::class, 'pollOrders'])->name('orders.poll');
        Route::post('/invoice-syncs', [CommerceController::class, 'queueInvoice'])->name('invoice-syncs.store');
        Route::post('/orders/{order}/retry', [CommerceController::class, 'retryOrder'])->where('order', '[0-9A-HJKMNP-TV-Z]{26}')->name('orders.retry');
""",
    "Commerce capability routes",
)

# UI.
view_blocks = r'''@can('integrations.manage')
<section class="detail-card"><h2>WooCommerce Polling</h2>
<form method="post" action="{{ route('commerce.orders.poll') }}">@csrf
<div class="form-grid">
<label>Bağlantı<select name="connection_public_id" required>@foreach($connections as $connection)@if($connection->provider==='woocommerce')<option value="{{ $connection->public_id }}">{{ $connection->name }}</option>@endif @endforeach</select></label>
<label>Modified After<input name="modified_after" type="datetime-local"></label>
<label>Sayfa<input name="page" type="number" min="1" value="1"></label>
<label>Adet<input name="per_page" type="number" min="1" max="100" value="50"></label>
</div><button class="button-secondary">Siparişleri Ortak Inbox'a Al</button></form>
</section>
<section class="detail-card"><h2>Fatura Sync</h2>
<form method="post" action="{{ route('commerce.invoice-syncs.store') }}">@csrf
<div class="form-grid">
<label>Bağlantı<select name="connection_public_id" required>@foreach($connections as $connection)@if($connection->provider==='woocommerce')<option value="{{ $connection->public_id }}">{{ $connection->name }}</option>@endif @endforeach</select></label>
<label>Mars Fatura<select name="sales_invoice_id" required>@foreach($finalizedInvoices as $invoice)<option value="{{ $invoice->id }}">{{ $invoice->number }} — {{ $invoice->invoice_date }}</option>@endforeach</select></label>
<label>External Order ID<input name="external_order_id" required maxlength="192"></label>
</div><button class="button-primary">Faturayı Sync'e Al</button></form>
</section>
@endcan
<section class="statement-table-card"><h2>Fatura Sync Durumu</h2><table class="data-table"><thead><tr><th>Bağlantı</th><th>Mars Fatura</th><th>External Order</th><th>Durum</th><th>Sync</th><th>Hata</th></tr></thead><tbody>
@forelse($invoiceSyncs as $sync)<tr><td>{{ $sync->connection_name }}</td><td>{{ $sync->invoice_number }}</td><td>{{ $sync->external_order_id }}</td><td>{{ $sync->status }}</td><td>{{ $sync->synced_at }}</td><td>{{ $sync->last_error }}</td></tr>@empty<tr><td colspan="6">Fatura sync kaydı yok.</td></tr>@endforelse
</tbody></table></section>

'''
replace_once(
    "resources/views/commerce/index.blade.php",
    '<section class="statement-table-card"><h2>Sipariş Inbox / Problem Center</h2>',
    view_blocks + '<section class="statement-table-card"><h2>Sipariş Inbox / Problem Center</h2>',
    "Commerce view capability blocks",
)

# Acceptance: polling enters same event store / domain path.
replace_once(
    "tests/Integration/Commerce/M17CommerceTest.php",
    """        'https://shop.example.test/wp-json/wc/v3/products/*' => Http::response(['id' => 501], 200),
        'https://shop.example.test/wp-json/wc/v3/system_status' => Http::response(['environment' => []], 200),
""",
    """        'https://shop.example.test/wp-json/wc/v3/products/*' => Http::response(['id' => 501], 200),
        'https://shop.example.test/wp-json/wc/v3/system_status' => Http::response(['environment' => []], 200),
        'https://shop.example.test/wp-json/wc/v3/orders*' => Http::response([m17WooOrderPayload(9003, 'WOO-SKU-1', 1, '120.000000', 'poll@example.test')], 200),
""",
    "M17 acceptance Woo polling fake",
)
poll_assertions = """    $polled = $commerce->pollOrders((int) $company->getKey(), $connectionPublicId, '2026-08-30T00:00:00+03:00', 1, 20);
    expect($polled)->toHaveCount(1);
    $pollResult = $domain->process($polled[0]);
    expect($pollResult)->not->toBeNull()
        ->and(DB::table('channel_order_inbox')->where('external_order_id', '9003')->value('status'))->toBe('imported')
        ->and(DB::table('integration_events')->where('id', $polled[0])->value('event_type'))->toBe('order.updated');

"""
replace_once(
    "tests/Integration/Commerce/M17CommerceTest.php",
    "    $returnId = $commerce->recordReturnEvidence(\n",
    poll_assertions + "    $returnId = $commerce->recordReturnEvidence(\n",
    "M17 acceptance polling assertions",
)
