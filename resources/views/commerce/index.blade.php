@extends('layouts.app')

@section('title', 'Kanal Merkezi')

@section('app-content')
<section class="workspace-hero">
    <div>
        <p class="eyebrow">M17 / E-Ticaret Integration Core + WooCommerce</p>
        <h1>Kanal Merkezi</h1>
        <p>WooCommerce bağlantıları, ürün eşlemeleri, versioned stock/price publish, sipariş inbox, problem merkezi, iade ve settlement kanıtları tek ekranda yönetilir.</p>
    </div>
    <div class="page-actions"><a class="button-secondary" href="{{ route('operations.index') }}">Operasyon Merkezi</a></div>
</section>

@if(session('status'))<div class="detail-card"><strong>{{ session('status') }}</strong></div>@endif
@if($errors->any())<div class="detail-card"><strong>Doğrulama hatası:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<section class="detail-card">
    <h2>Provider Capability Registry</h2>
    <div class="form-grid">
        @foreach($providers as $key => $provider)
            <div>
                <strong>{{ $provider['label'] }}</strong><br>
                Durum: <code>{{ $provider['status'] }}</code><br>
                <small>{{ implode(', ', $provider['capabilities']) }}</small>
            </div>
        @endforeach
    </div>
    <p><strong>Not:</strong> <code>transport_only</code> provider, M18 doğrulanmış marketplace adapter anlamına gelmez.</p>
</section>

@can('integrations.manage')
<section class="detail-card">
    <h2>WooCommerce Bağlantısı</h2>
    <form method="post" action="{{ route('commerce.connections.store') }}">@csrf
        <input type="hidden" name="provider" value="woocommerce">
        <div class="form-grid">
            <label>Ad<input name="name" required maxlength="96"></label>
            <label>Base URL<input name="base_url" type="url" required placeholder="https://shop.example.com"></label>
            <label>Consumer Key<input name="consumer_key" required autocomplete="off"></label>
            <label>Consumer Secret<input name="consumer_secret" type="password" required autocomplete="new-password"></label>
            <label>Webhook Secret<input name="webhook_secret" type="password" required minlength="16" autocomplete="new-password"></label>
            <label>Fiyat Bazı<select name="price_basis"><option value="net">Net</option><option value="gross">Brüt</option></select></label>
            <label>Sipariş Serisi<input name="order_series" value="default" required></label>
            <label>Finans Modu<select name="financial_mode"><option value="direct_account">Doğrudan Cari</option><option value="clearing_account">Kanal Clearing</option></select></label>
            <label>Varsayılan Cari<select name="default_account_id"><option value="">—</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->legal_name }} ({{ $account->type }})</option>@endforeach</select></label>
            <label>Clearing Cari<select name="clearing_account_id"><option value="">—</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->legal_name }} ({{ $account->type }})</option>@endforeach</select></label>
            <label>Rezervasyon Deposu<select name="default_warehouse_id"><option value="">Rezervasyonsuz</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>@endforeach</select></label>
            <label>Rezervasyon Lokasyonu<select name="default_location_id"><option value="">Rezervasyonsuz</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->warehouse_code }} / {{ $location->code }} — {{ $location->name }}</option>@endforeach</select></label>
        </div>
        <p>Credential değerleri şifreli saklanır ve bu ekranda geri gösterilmez. Webhook URL dışarıya yalnız 26 karakterlik public ULID ile açılır.</p>
        <button class="button-primary" type="submit">Bağlantı Oluştur</button>
    </form>
</section>
@endcan

<section class="statement-table-card">
<h2>Bağlantılar</h2>
<table class="data-table"><thead><tr><th>Public ID</th><th>Provider / Ad</th><th>Finans</th><th>Test</th><th>Son Başarı</th><th>Hata</th><th></th></tr></thead><tbody>
@forelse($connections as $connection)
<tr>
<td><code>{{ $connection->public_id }}</code></td>
<td>{{ $connection->provider }} / {{ $connection->name }}</td>
<td>{{ $connection->financial_mode }}</td>
<td>{{ $connection->connection_test_status }}<br><small>{{ $connection->connection_tested_at }}</small></td>
<td>{{ $connection->last_success_at }}</td>
<td>{{ $connection->connection_test_message ?: $connection->last_error }}</td>
<td>@can('integrations.manage')<form method="post" action="{{ route('commerce.connections.test', $connection->public_id) }}">@csrf<button class="button-secondary">Test</button></form>@endcan</td>
</tr>
@empty<tr><td colspan="7">Bağlantı yok.</td></tr>@endforelse
</tbody></table>
</section>

@can('integrations.manage')
<section class="detail-card">
<h2>Ürün Eşleme</h2>
<form method="post" action="{{ route('commerce.mappings.store') }}">@csrf
<div class="form-grid">
<label>Bağlantı<select name="connection" required>@foreach($connections as $connection)<option value="{{ $connection->public_id }}">{{ $connection->provider }} / {{ $connection->name }}</option>@endforeach</select></label>
<label>Mars Ürün<select name="product_id" required>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->code }} — {{ $product->name }}</option>@endforeach</select></label>
<label>External Product ID<input name="external_product_id" maxlength="192"></label>
<label>External Variant ID<input name="external_variant_id" maxlength="192"></label>
<label>External SKU<input name="external_sku" maxlength="192"></label>
</div>
<button class="button-primary">Eşlemeyi Kaydet</button>
</form>
</section>
@endcan

<section class="statement-table-card">
<h2>Ürün Eşlemeleri</h2>
<table class="data-table"><thead><tr><th>Public ID</th><th>Kanal</th><th>Mars Ürün</th><th>External Product</th><th>Variant</th><th>SKU</th><th>Durum</th></tr></thead><tbody>
@forelse($mappings as $mapping)<tr><td><code>{{ $mapping->public_id }}</code></td><td>{{ $mapping->provider }} / {{ $mapping->connection_name }}</td><td>{{ $mapping->product_code }} — {{ $mapping->product_name }}</td><td>{{ $mapping->external_product_id }}</td><td>{{ $mapping->external_variant_id }}</td><td>{{ $mapping->external_sku }}</td><td>{{ $mapping->status }}</td></tr>@empty<tr><td colspan="7">Eşleme yok.</td></tr>@endforelse
</tbody></table>
</section>

@can('integrations.manage')
<section class="detail-card">
<h2>Desired-State Publish</h2>
<form method="post" action="{{ route('commerce.publish') }}">@csrf
<div class="form-grid">
<label>Eşleme<select name="mapping" required>@foreach($mappings as $mapping)<option value="{{ $mapping->public_id }}">{{ $mapping->connection_name }} / {{ $mapping->product_code }}</option>@endforeach</select></label>
<label>Stok<input name="stock" inputmode="decimal"></label>
<label>Fiyat<input name="price" inputmode="decimal"></label>
<label>Para Birimi<input name="currency_code" maxlength="3" placeholder="TRY"></label>
</div>
<label>Medya URL'leri (satır başına bir adet)<textarea name="media_urls" rows="4"></textarea></label>
<p>Her publish yeni desired version üretir. Eski retry yeni version'ı ezemez; stale effect otomatik <code>ignored</code> olur.</p>
<button class="button-primary">Publish Kuyruğuna Al</button>
</form>
</section>
@endcan

<section class="statement-table-card">
<h2>Listing State</h2>
<table class="data-table"><thead><tr><th>Ürün</th><th>Kanal</th><th>Desired v</th><th>Published v</th><th>Stok</th><th>Fiyat</th><th>Durum</th><th>Hata</th></tr></thead><tbody>
@forelse($listingStates as $state)<tr><td>{{ $state->product_code }}</td><td>{{ $state->connection_name }}</td><td>{{ $state->desired_version }}</td><td>{{ $state->published_version }}</td><td>{{ $state->desired_stock }}</td><td>{{ $state->desired_price }} {{ $state->desired_currency_code }}</td><td>{{ $state->status }}</td><td>{{ $state->last_error }}</td></tr>@empty<tr><td colspan="8">Listing state yok.</td></tr>@endforelse
</tbody></table>
</section>

@can('integrations.manage')
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

<section class="statement-table-card">
<h2>Sipariş Inbox</h2>
<table class="data-table"><thead><tr><th>Public ID</th><th>Kanal</th><th>External Sipariş</th><th>Durum</th><th>Mars Sipariş</th><th>Problem</th><th></th></tr></thead><tbody>
@forelse($orders as $order)<tr><td><code>{{ $order->public_id }}</code></td><td>{{ $order->provider }} / {{ $order->connection_name }}</td><td>{{ $order->external_order_id }}</td><td>{{ $order->status }}</td><td>{{ $order->sales_order_id }}</td><td>{{ $order->problem_code }} {{ $order->problem_message }}</td><td>@if(in_array($order->status, ['stock_problem','failed'], true)) @can('integrations.manage')<form method="post" action="{{ route('commerce.orders.retry', $order->public_id) }}">@csrf<button class="button-secondary">Retry</button></form>@endcan @endif</td></tr>@empty<tr><td colspan="7">Kanal siparişi yok.</td></tr>@endforelse
</tbody></table>
</section>

<section class="statement-table-card">
<h2>Problem Merkezi</h2>
<table class="data-table"><thead><tr><th>Public ID</th><th>Kanal</th><th>Tip</th><th>Mesaj</th><th>Zaman</th></tr></thead><tbody>@forelse($problems as $problem)<tr><td><code>{{ $problem->public_id }}</code></td><td>{{ $problem->provider }} / {{ $problem->connection_name }}</td><td>{{ $problem->type }}</td><td>{{ $problem->message }}</td><td>{{ $problem->created_at }}</td></tr>@empty<tr><td colspan="5">Açık problem yok.</td></tr>@endforelse</tbody></table>
</section>

@can('integrations.manage')
<section class="detail-card">
<h2>İade Evidence → M12 Handoff</h2>
<form method="post" action="{{ route('commerce.returns.store') }}">@csrf
<div class="form-grid"><label>Bağlantı<select name="connection" required>@foreach($connections as $connection)<option value="{{ $connection->public_id }}">{{ $connection->name }}</option>@endforeach</select></label><label>External Return ID<input name="external_return_id" required></label><label>External Order ID<input name="external_order_id" required></label></div>
<label>Evidence JSON<textarea name="evidence_json" rows="4" required>{}</textarea></label><button class="button-primary">İade Kanıtını Kaydet</button>
</form>
</section>
@endcan
<section class="statement-table-card"><h2>İade Evidence</h2><table class="data-table"><thead><tr><th>ID</th><th>Kanal</th><th>Return</th><th>Order</th><th>Durum</th><th>M12 Return</th></tr></thead><tbody>@forelse($returns as $return)<tr><td><code>{{ $return->public_id }}</code></td><td>{{ $return->connection_name }}</td><td>{{ $return->external_return_id }}</td><td>{{ $return->external_order_id }}</td><td>{{ $return->status }}</td><td>{{ $return->sales_return_id }}</td></tr>@empty<tr><td colspan="6">İade evidence yok.</td></tr>@endforelse</tbody></table></section>

@can('integrations.manage')
<section class="detail-card">
<h2>Settlement Evidence</h2>
<form method="post" action="{{ route('commerce.settlements.store') }}">@csrf
<div class="form-grid"><label>Bağlantı<select name="connection" required>@foreach($connections as $connection)<option value="{{ $connection->public_id }}">{{ $connection->name }}</option>@endforeach</select></label><label>External Settlement ID<input name="external_settlement_id" required></label><label>Para Birimi<input name="currency_code" value="TRY" required maxlength="3"></label><label>Gross<input name="gross_amount" required inputmode="decimal"></label><label>Fee<input name="fee_amount" required inputmode="decimal"></label><label>Oluşma Zamanı<input name="occurred_at" type="datetime-local" required></label></div>
<label>Evidence JSON<textarea name="evidence_json" rows="4" required>{}</textarea></label><button class="button-primary">Settlement Kaydet</button>
</form>
</section>
@endcan
<section class="statement-table-card"><h2>Settlement / Clearing Handoff</h2><table class="data-table"><thead><tr><th>ID</th><th>Kanal</th><th>External</th><th>Gross</th><th>Fee</th><th>Net</th><th>Durum</th><th></th></tr></thead><tbody>@forelse($settlements as $settlement)<tr><td><code>{{ $settlement->public_id }}</code></td><td>{{ $settlement->connection_name }}</td><td>{{ $settlement->external_settlement_id }}</td><td>{{ $settlement->gross_amount }} {{ $settlement->currency_code }}</td><td>{{ $settlement->fee_amount }}</td><td>{{ $settlement->net_amount }}</td><td>{{ $settlement->status }}</td><td>@if($settlement->status==='received') @can('integrations.manage')<form method="post" action="{{ route('commerce.settlements.handoff', $settlement->public_id) }}">@csrf<button class="button-secondary">Finance Handoff</button></form>@endcan @endif</td></tr>@empty<tr><td colspan="8">Settlement evidence yok.</td></tr>@endforelse</tbody></table></section>
@endsection
