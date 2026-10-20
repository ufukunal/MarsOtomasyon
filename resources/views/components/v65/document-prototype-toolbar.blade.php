@props(['screenType', 'products' => null, 'quickSearch' => '', 'editable' => true, 'showVat' => true])

<section {{ $attributes->class(['v65-reference-products-bar']) }} aria-label="Ürün ve hizmet giriş araçları">
    <div class="v65-reference-products-title">
        <div>
            <h2>Ürün ve Hizmetler</h2>
            <small>{{ config('document_prototype.'.$screenType.'.hint', 'Ürün ve hizmetleri girin.') }}</small>
        </div>
        @if($editable && $screenType !== 'quote')
            <button type="button" class="v65-reference-fast-indicator" x-on:click="fastEntry = !fastEntry"
                x-bind:aria-pressed="fastEntry.toString()" aria-controls="v65-reference-quick-{{ $screenType }}"
                x-text="fastEntry ? 'Hızlı satır girişi açık' : 'Hızlı satır girişi kapalı'">Hızlı satır girişi açık</button>
        @elseif(!$editable)
            <span class="v65-reference-fast-indicator v65-reference-source-indicator">Kaynak belge satırları</span>
        @endif
    </div>
    @if($editable)
        <div class="v65-reference-products-tools">
            <div class="v65-reference-quick-product" @if($screenType !== 'quote') x-show="fastEntry" @endif x-data="{ selectedIds: [] }">
                <label for="v65-reference-quick-{{ $screenType }}" class="sr-only">Ürün kodu, barkod veya ürün adı ara</label>
                <input id="v65-reference-quick-{{ $screenType }}" type="search"
                    wire:model.live.debounce.250ms="quickProductSearch"
                    data-v65-reference-quick-input
                    x-on:input="selectedIds = []"
                    x-on:keydown.escape="selectedIds = []"
                    placeholder="Ürün kodu, barkod veya ürün adını arayın..."
                    autocomplete="off" aria-label="Ürün kodu, barkod veya ürün adı ara">
                @if(mb_strlen(trim((string) $quickSearch)) >= 2)
                    <div class="v65-reference-suggestions" role="group" aria-label="Ürün arama sonuçları">
                        @forelse($products ?? [] as $product)
                            <button type="button" data-v65-reference-quick-choice
                                data-v65-product-id="{{ $product->id }}"
                                x-bind:aria-pressed="selectedIds.includes({{ $product->id }}) ? 'true' : 'false'"
                                x-bind:class="{ 'is-multi-selected': selectedIds.includes({{ $product->id }}) }"
                                x-on:click.prevent="
                                    if ($event.ctrlKey || $event.metaKey) {
                                        selectedIds = selectedIds.includes({{ $product->id }})
                                            ? selectedIds.filter(id => id !== {{ $product->id }})
                                            : [...selectedIds, {{ $product->id }}];
                                    } else {
                                        selectedIds = [];
                                        $wire.insertQuickProduct({{ $product->id }});
                                    }
                                ">
                                <span class="v65-reference-suggestion-identity">
                                    <strong>{{ $product->name }}</strong>
                                    <small>{{ $product->code }}@if($product->barcode) · {{ $product->barcode }}@endif</small>
                                </span>
                                <span class="v65-reference-suggestion-metrics">
                                    <span class="v65-reference-suggestion-metric">
                                        <small>{{ str_starts_with($screenType, 'purchase_') ? 'Liste Fiyatı' : 'Fiyat' }}</small>
                                        <strong>{{ \App\Support\Formatting\TrFormatter::money((string) $product->lookup_price) }} {{ $product->lookup_price_currency }}</strong>
                                    </span>
                                    @if(auth()->user()?->can('stock.view'))
                                        <span class="v65-reference-suggestion-metric">
                                            <small>Stok</small>
                                            <strong>{{ $product->lookup_physical !== null ? \App\Support\Formatting\TrFormatter::quantity((string) $product->lookup_physical) : '—' }}</strong>
                                        </span>
                                        <span class="v65-reference-suggestion-metric">
                                            <small>Rezerve</small>
                                            <strong>{{ $product->lookup_reserved !== null ? \App\Support\Formatting\TrFormatter::quantity((string) $product->lookup_reserved) : '—' }}</strong>
                                        </span>
                                        <span class="v65-reference-suggestion-metric v65-reference-suggestion-available">
                                            <small>Kullanılabilir</small>
                                            <strong>{{ $product->lookup_available !== null ? \App\Support\Formatting\TrFormatter::quantity((string) $product->lookup_available) : '—' }}</strong>
                                        </span>
                                    @else
                                        <span class="v65-reference-suggestion-stock-locked">Stok görüntüleme yetkisi yok</span>
                                    @endif
                                </span>
                            </button>
                        @empty
                            <span class="v65-reference-no-product">Eşleşen aktif ürün bulunamadı</span>
                        @endforelse
                        <div class="v65-reference-multi-footer" x-show="selectedIds.length > 0" x-cloak>
                            <span x-text="selectedIds.length + ' ürün seçildi'"></span>
                            <button type="button" class="v65-reference-multi-clear"
                                x-on:click.prevent="selectedIds = []">Seçimi Temizle</button>
                            <button type="button" class="v65-reference-multi-apply"
                                wire:loading.attr="disabled" wire:target="insertQuickProducts"
                                x-on:click.prevent="$wire.insertQuickProducts([...selectedIds]).then(() => { selectedIds = []; })">
                                Seçilenleri Ekle
                            </button>
                        </div>
                    </div>
                @endif
                <small class="v65-reference-multi-help">Ctrl + tıklama ile birden fazla ürün seçebilirsiniz.</small>
            </div>
            @if($screenType === 'quote')
                <x-v65.button variant="secondary" size="sm" wire:click="addCustomLine"
                    wire:loading.attr="disabled" wire:target="addCustomLine">+ Özel Üretim Ekle</x-v65.button>
            @endif
            <x-v65.button variant="secondary" size="sm" wire:click="addServiceLine">+ Hizmet Satırı</x-v65.button>
            @if($showVat)
                <div class="v65-reference-vat-select">
                    <label for="v65-reference-vat-{{ $screenType }}">Toplu KDV:</label>
                    <select id="v65-reference-vat-{{ $screenType }}" wire:model.live="bulkVatPreset"
                        aria-label="Tüm satırlara KDV oranı uygula">
                        <option value="">Değiştirme</option>
                        <option value="0">%0</option>
                        <option value="1">%1</option>
                        <option value="10">%10</option>
                        <option value="20">%20</option>
                    </select>
                </div>
            @endif
        </div>
    @endif
</section>
