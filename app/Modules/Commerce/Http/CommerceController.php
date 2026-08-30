<?php

namespace App\Modules\Commerce\Http;

use App\Modules\Commerce\ChannelCenterService;
use App\Modules\Commerce\ProviderRegistry;
use App\Modules\Core\Company\ActiveCompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final readonly class CommerceController
{
    public function __construct(private ActiveCompanyContext $companyContext) {}

    public function index(ProviderRegistry $providers): View
    {
        $companyId = $this->companyId();

        return view('commerce.index', [
            'providers' => $providers->all(),
            'connections' => DB::table('integration_connections')
                ->where('company_id', $companyId)
                ->select(['public_id', 'provider', 'name', 'status', 'base_url', 'financial_mode', 'default_account_id', 'clearing_account_id', 'connection_test_status', 'connection_tested_at', 'connection_test_message', 'last_success_at', 'last_error'])
                ->orderBy('provider')->orderBy('name')->get(),
            'accounts' => DB::table('accounts')->where('company_id', $companyId)->where('status', 'active')->orderBy('code')->get(['id', 'code', 'legal_name', 'type']),
            'products' => DB::table('products')->where('company_id', $companyId)->where('status', 'active')->orderBy('code')->limit(500)->get(['id', 'code', 'name']),
            'warehouses' => DB::table('warehouses')->where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'locations' => DB::table('warehouse_locations as l')
                ->join('warehouses as w', 'w.id', '=', 'l.warehouse_id')
                ->where('l.company_id', $companyId)->where('l.is_active', true)->where('w.is_active', true)
                ->orderBy('w.code')->orderBy('l.code')
                ->get(['l.id', 'l.warehouse_id', 'l.code', 'l.name', 'w.code as warehouse_code']),
            'mappings' => DB::table('channel_product_mappings as m')
                ->join('integration_connections as c', 'c.id', '=', 'm.connection_id')
                ->join('products as p', 'p.id', '=', 'm.product_id')
                ->where('m.company_id', $companyId)
                ->select(['m.public_id', 'm.external_product_id', 'm.external_variant_id', 'm.external_sku', 'm.status', 'c.public_id as connection_public_id', 'c.provider', 'c.name as connection_name', 'p.code as product_code', 'p.name as product_name'])
                ->orderByDesc('m.id')->limit(100)->get(),
            'listingStates' => DB::table('channel_listing_states as s')
                ->join('channel_product_mappings as m', 'm.id', '=', 's.mapping_id')
                ->join('products as p', 'p.id', '=', 'm.product_id')
                ->join('integration_connections as c', 'c.id', '=', 's.connection_id')
                ->where('s.company_id', $companyId)
                ->select(['s.public_id', 's.desired_version', 's.desired_stock', 's.desired_price', 's.desired_currency_code', 's.published_version', 's.status', 's.last_error', 'm.public_id as mapping_public_id', 'p.code as product_code', 'c.name as connection_name'])
                ->orderByDesc('s.id')->limit(100)->get(),
            'orders' => DB::table('channel_order_inbox as o')
                ->join('integration_connections as c', 'c.id', '=', 'o.connection_id')
                ->where('o.company_id', $companyId)
                ->select(['o.public_id', 'o.external_order_id', 'o.status', 'o.problem_code', 'o.problem_message', 'o.sales_order_id', 'o.financial_mode', 'o.received_at', 'o.imported_at', 'c.provider', 'c.name as connection_name'])
                ->orderByDesc('o.id')->limit(100)->get(),
            'problems' => DB::table('channel_problems as p')
                ->join('integration_connections as c', 'c.id', '=', 'p.connection_id')
                ->where('p.company_id', $companyId)
                ->where('p.status', 'open')
                ->select(['p.public_id', 'p.type', 'p.message', 'p.created_at', 'c.provider', 'c.name as connection_name'])
                ->orderByDesc('p.id')->limit(100)->get(),
            'returns' => DB::table('channel_return_events as r')
                ->join('integration_connections as c', 'c.id', '=', 'r.connection_id')
                ->where('r.company_id', $companyId)
                ->select(['r.public_id', 'r.external_return_id', 'r.external_order_id', 'r.status', 'r.sales_return_id', 'r.last_error', 'c.name as connection_name'])
                ->orderByDesc('r.id')->limit(100)->get(),
            'settlements' => DB::table('channel_settlement_evidence as s')
                ->join('integration_connections as c', 'c.id', '=', 's.connection_id')
                ->where('s.company_id', $companyId)
                ->select(['s.public_id', 's.external_settlement_id', 's.currency_code', 's.gross_amount', 's.fee_amount', 's.net_amount', 's.status', 's.occurred_at', 'c.name as connection_name'])
                ->orderByDesc('s.id')->limit(100)->get(),
        ]);
    }

    public function storeConnection(Request $request, ChannelCenterService $commerce): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'in:woocommerce'],
            'name' => ['required', 'string', 'max:96'],
            'base_url' => ['required', 'url', 'max:512'],
            'webhook_secret' => ['required', 'string', 'min:16', 'max:512'],
            'consumer_key' => ['required', 'string', 'max:512'],
            'consumer_secret' => ['required', 'string', 'max:512'],
            'price_basis' => ['required', 'in:net,gross'],
            'order_series' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/'],
            'financial_mode' => ['required', 'in:direct_account,clearing_account'],
            'default_account_id' => ['nullable', 'integer', 'min:1'],
            'clearing_account_id' => ['nullable', 'integer', 'min:1'],
            'default_warehouse_id' => ['nullable', 'integer', 'min:1', 'required_with:default_location_id'],
            'default_location_id' => ['nullable', 'integer', 'min:1', 'required_with:default_warehouse_id'],
        ]);
        $publicId = $commerce->createConnection(
            companyId: $this->companyId(),
            provider: (string) $validated['provider'],
            name: (string) $validated['name'],
            baseUrl: (string) $validated['base_url'],
            credentials: [
                'consumer_key' => (string) $validated['consumer_key'],
                'consumer_secret' => (string) $validated['consumer_secret'],
                'price_basis' => (string) $validated['price_basis'],
                'order_series' => (string) $validated['order_series'],
                'default_warehouse_id' => isset($validated['default_warehouse_id']) ? (int) $validated['default_warehouse_id'] : null,
                'default_location_id' => isset($validated['default_location_id']) ? (int) $validated['default_location_id'] : null,
            ],
            webhookSecret: (string) $validated['webhook_secret'],
            financialMode: (string) $validated['financial_mode'],
            defaultAccountId: isset($validated['default_account_id']) ? (int) $validated['default_account_id'] : null,
            clearingAccountId: isset($validated['clearing_account_id']) ? (int) $validated['clearing_account_id'] : null,
        );

        return back()->with('status', 'WooCommerce bağlantısı oluşturuldu: '.$publicId);
    }

    public function testConnection(string $connection, ChannelCenterService $commerce): RedirectResponse
    {
        $commerce->testConnection($this->companyId(), $connection);

        return back()->with('status', 'Bağlantı testi başarılı.');
    }

    public function storeMapping(Request $request, ChannelCenterService $commerce): RedirectResponse
    {
        $validated = $request->validate([
            'connection' => ['required', 'string', 'size:26'],
            'product_id' => ['required', 'integer', 'min:1'],
            'external_product_id' => ['nullable', 'string', 'max:192'],
            'external_variant_id' => ['nullable', 'string', 'max:192'],
            'external_sku' => ['nullable', 'string', 'max:192'],
        ]);
        $publicId = $commerce->mapProduct(
            $this->companyId(),
            (string) $validated['connection'],
            (int) $validated['product_id'],
            $validated['external_product_id'] ?? null,
            $validated['external_variant_id'] ?? null,
            $validated['external_sku'] ?? null,
        );

        return back()->with('status', 'Kanal ürün eşlemesi kaydedildi: '.$publicId);
    }

    public function publish(Request $request, ChannelCenterService $commerce): RedirectResponse
    {
        $validated = $request->validate([
            'mapping' => ['required', 'string', 'size:26'],
            'stock' => ['nullable', 'numeric', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'media_urls' => ['nullable', 'string', 'max:20000'],
        ]);
        $media = [];
        if (isset($validated['media_urls']) && trim((string) $validated['media_urls']) !== '') {
            $media = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $validated['media_urls']) ?: [])));
        }
        $result = $commerce->queueDesiredState(
            $this->companyId(),
            (string) $validated['mapping'],
            isset($validated['stock']) ? (string) $validated['stock'] : null,
            isset($validated['price']) ? (string) $validated['price'] : null,
            isset($validated['currency_code']) ? (string) $validated['currency_code'] : null,
            $media,
        );

        return back()->with('status', 'Desired-state v'.$result['version'].' kuyruğa alındı.');
    }

    public function retryOrder(string $order, ChannelCenterService $commerce): RedirectResponse
    {
        $commerce->retryOrder($this->companyId(), $order);

        return back()->with('status', 'Kanal siparişi yeniden işleme alındı.');
    }

    public function storeReturn(Request $request, ChannelCenterService $commerce): RedirectResponse
    {
        $validated = $request->validate([
            'connection' => ['required', 'string', 'size:26'],
            'external_return_id' => ['required', 'string', 'max:192'],
            'external_order_id' => ['required', 'string', 'max:192'],
            'evidence_json' => ['required', 'json', 'max:20000'],
        ]);
        $evidence = json_decode((string) $validated['evidence_json'], true, flags: JSON_THROW_ON_ERROR);
        abort_unless(is_array($evidence), 422, 'İade evidence JSON object/array olmalıdır.');
        $commerce->recordReturnEvidence(
            $this->companyId(),
            (string) $validated['connection'],
            (string) $validated['external_return_id'],
            (string) $validated['external_order_id'],
            $evidence,
        );

        return back()->with('status', 'İade kanıtı M12 handoff için kaydedildi.');
    }

    public function storeSettlement(Request $request, ChannelCenterService $commerce): RedirectResponse
    {
        $validated = $request->validate([
            'connection' => ['required', 'string', 'size:26'],
            'external_settlement_id' => ['required', 'string', 'max:192'],
            'currency_code' => ['required', 'string', 'size:3'],
            'gross_amount' => ['required', 'numeric', 'min:0'],
            'fee_amount' => ['required', 'numeric', 'min:0'],
            'occurred_at' => ['required', 'date'],
            'evidence_json' => ['required', 'json', 'max:20000'],
        ]);
        $evidence = json_decode((string) $validated['evidence_json'], true, flags: JSON_THROW_ON_ERROR);
        abort_unless(is_array($evidence), 422, 'Settlement evidence JSON object/array olmalıdır.');
        $commerce->recordSettlementEvidence(
            $this->companyId(),
            (string) $validated['connection'],
            (string) $validated['external_settlement_id'],
            (string) $validated['currency_code'],
            (string) $validated['gross_amount'],
            (string) $validated['fee_amount'],
            (string) $validated['occurred_at'],
            $evidence,
        );

        return back()->with('status', 'Settlement evidence kaydedildi.');
    }

    public function handoffSettlement(string $settlement, ChannelCenterService $commerce): RedirectResponse
    {
        $commerce->markSettlementHandedOff($this->companyId(), $settlement);

        return back()->with('status', 'Settlement finance handoff olarak işaretlendi.');
    }

    private function companyId(): int
    {
        return (int) $this->companyContext->requireCompany()->getKey();
    }
}
