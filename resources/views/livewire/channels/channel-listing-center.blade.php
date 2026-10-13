<div class="space-y-6 v65-list-page">
    @if(session('status')) <div class="panel">{{ session('status') }}</div> @endif

    <section class="panel v65-list-panel">
        @teleport('.page-actions.v65-page-actions')
        <div class="v65-list-heading-actions">
            @can('channel_listings.create')
                <button type="button" wire:click="newListing">Yeni Mapping</button>
            @endcan
        </div>
    @endteleport
        <x-v65.list-heading title="Kanal Ürün Eşleştirmeleri">
            <div class="v65-filter-toolbar">
            <x-v65.filter-drawer :count="$this->activeV65FilterCount()" :tab="$v65FilterTab">
                <x-slot:filters>
                    <x-v65.filter-field label="Genel Arama">
                        <input type="search" wire:model="v65DraftFilters.search" maxlength="255" placeholder="Kod, belge veya ürün ara">
                    </x-v65.filter-field>
                </x-slot:filters>
                <x-slot:sorting>
                    <x-v65.filter-field label="Sıralama Alanı">
                        <select wire:model="v65DraftFilters.sort">
                            <option value="">Varsayılan</option>
                            <option value="id">Kayıt Numarası</option>
                        </select>
                    </x-v65.filter-field>
                    <x-v65.filter-field label="Sıralama Yönü">
                        <select wire:model="v65DraftFilters.direction">
                            <option value="asc">Artan</option><option value="desc">Azalan</option>
                        </select>
                    </x-v65.filter-field>
                </x-slot:sorting>
            </x-v65.filter-drawer>
        </div>
        </x-v65.list-heading>
        <x-v65.active-filters :items="$this->v65ActiveFilterLabels()" />
        <div class="table-scroll v65-grid-scroll v65-list-table"><table class="data-table">
            <thead><tr><th>Account</th><th>Ürün</th><th>External Listing</th><th>SKU</th><th>Mode</th><th>Aktif</th></tr></thead>
            <tbody>
            @foreach($listings as $listing)
                <tr>
                    <td>#{{ $listing->channel_account_id }}</td>
                    <td>@can('products.view')<a class="v65-record-link" href="{{ route('products.show', ['product' => $listing->product_id]) }}">{{ $listing->product->code }} · {{ $listing->product->name }}</a>@else{{ $listing->product->code }} · {{ $listing->product->name }}@endcan</td>
                    <td><button type="button" class="link-button v65-record-link" wire:click="selectListing({{ $listing->id }})">{{ $listing->external_listing_id ?? ('#'.$listing->id) }}</button></td>
                    <td>@if(filled($listing->external_sku))<button type="button" class="link-button v65-record-link" wire:click="selectListing({{ $listing->id }})">{{ $listing->external_sku }}</button>@else—@endif</td>
                    <td>{{ $listing->stock_mode ?: 'product default' }}</td>
                    <td>{{ $listing->is_active ? 'Evet' : 'Hayır' }}</td>
                    
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <x-v65.list-footer :paginator="$listings" :per-page="$perPage" />
    </section>

    <section class="panel stack v65-editor-panel v65-workspace-form">
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
                @if(($accounts->firstWhere('id', $channelAccountId)?->platform?->value ?? null) === 'n11')
                    <div>
                        N11 ürün güncelleme REST servisi açıklama, durum, hazırlık süresi, kargo şablonu,
                        maksimum adet, ana ürün kodu, KDV ve desteklenen attribute alanlarını günceller.
                        Resmi update endpointinde title/görsel alanı tanımlı olmadığı için bu alanlar publish sonrası gönderilmez.
                    </div>
                @elseif(($accounts->firstWhere('id', $channelAccountId)?->platform?->value ?? null) === 'woocommerce')
                    <div>
                        WooCommerce core REST API ürün adı, açıklama, görsel, stok ve fiyat güncellemelerini destekler.
                        Lead-time ve standart shipment-tracking endpointi core API'de bulunmadığından bu iki alan outbound gönderilmez.
                    </div>
                @endif
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
