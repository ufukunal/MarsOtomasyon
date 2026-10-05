<div class="space-y-6">
    @if(session('status')) <div class="panel">{{ session('status') }}</div> @endif

    <section class="panel">
        <div class="form-row">
            <h1>Kanal Ürün Listingleri</h1>
            @can('channel_listings.create')
                <button type="button" wire:click="newListing">Yeni Mapping</button>
            @endcan
        </div>
        <table class="data-table">
            <thead><tr><th>Account</th><th>Ürün</th><th>External Listing</th><th>SKU</th><th>Mode</th><th>Aktif</th><th></th></tr></thead>
            <tbody>
            @foreach($listings as $listing)
                <tr>
                    <td>#{{ $listing->channel_account_id }}</td>
                    <td>{{ $listing->product->code }} · {{ $listing->product->name }}</td>
                    <td>{{ $listing->external_listing_id }}</td>
                    <td>{{ $listing->external_sku }}</td>
                    <td>{{ $listing->stock_mode ?: 'product default' }}</td>
                    <td>{{ $listing->is_active ? 'Evet' : 'Hayır' }}</td>
                    <td><button type="button" wire:click="selectListing({{ $listing->id }})">Aç</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>

    <section class="panel space-y-3">
        <h2>{{ $selectedListingId ? 'Listing Mapping Düzenle' : 'Yeni Listing Mapping' }}</h2>
        <label>Kanal Hesabı
            <select wire:model="channelAccountId" @disabled($selectedListingId)>
                <option value="">Seçin</option>
                @foreach($accounts as $account)
                    <option value="{{ $account->id }}">{{ $account->platform->label() }} · {{ $account->name }}</option>
                @endforeach
            </select>
        </label>
        <label>Ürün
            <select wire:model="productId" @disabled($selectedListingId)>
                <option value="">Seçin</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }}</option>
                @endforeach
            </select>
        </label>

        <div class="form-row">
            <label>External Product ID <input wire:model="externalProductId"></label>
            <label>External Listing ID <input wire:model="externalListingId"></label>
            <label>External SKU <input wire:model="externalSku"></label>
        </div>

        <label>Stock Mode
            <select wire:model.live="stockMode">
                <option value="">Product Varsayılanı</option>
                <option value="stock">Stock</option>
                <option value="production">Production</option>
                <option value="manual">Manual</option>
            </select>
        </label>

        <div class="form-row">
            <label>Withhold <input data-tr-decimal wire:model="withholdQuantity"></label>
            <label>Max Kanal Miktarı <input data-tr-decimal wire:model="maxChannelQuantity"></label>
            <label>Fixed Quantity <input data-tr-decimal wire:model="fixedQuantity"></label>
            <label>Manual Quantity <input data-tr-decimal wire:model="manualQuantity"></label>
            <label>Lead Time (gün) <input type="number" min="0" wire:model="leadTimeDays"></label>
        </div>

        <h3>Stock Mode Lokasyon Kapsamı</h3>
        @foreach($locations as $location)
            <label>
                <input type="checkbox" value="{{ $location->id }}" wire:model="locationIds">
                {{ $location->code }} · {{ $location->name }}
            </label>
        @endforeach

        <div class="form-row">
            <label>TRY Fiyat Override <input data-tr-decimal wire:model="priceOverride"></label>
            <label>Görsel Collection <input wire:model="imageCollection"></label>
        </div>
        <label>Başlık Override <input wire:model="titleOverride"></label>
        <label>Açıklama Override <textarea wire:model="descriptionOverride"></textarea></label>
        <label>Kategori / Özellik Metadata JSON <textarea wire:model="categoryMetadataJson"></textarea></label>
        <label><input type="checkbox" wire:model="isActive"> Aktif</label>

        @canany(['channel_listings.create','channel_listings.update'])
            <button type="button" wire:click="save">Mapping Kaydet</button>
        @endcanany

        @if($selectedListingId)
            <div>
                <strong>Preview:</strong>
                stok {{ $stockPreview }} · fiyat {{ $pricePreview }} TRY
            </div>

            @if($adapterAvailable)
                @can('channel_listings.update')
                    <div class="form-row">
                        <button type="button" wire:click="publish">Publish</button>
                        <button type="button" wire:click="syncContent">İçerik/Görsel Sync</button>
                        <button type="button" wire:click="syncStock">Stok Sync</button>
                        <button type="button" wire:click="syncPrice">Fiyat Sync</button>
                    </div>
                @endcan
            @else
                <div>Seçili hesabın adapter'ı henüz etkin değil.</div>
            @endif
        @endif
    </section>

    @foreach($errors->all() as $error)
        <div class="field-error">{{ $error }}</div>
    @endforeach
</div>
