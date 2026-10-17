@props(['screenType', 'products' => null, 'quickSearch' => '', 'editable' => true, 'showVat' => true])

<section {{ $attributes->class(['v65-reference-products-bar']) }} x-data="{ fastEntry: true }" aria-label="Ürün ve hizmet giriş araçları">
    <div class="v65-reference-products-title">
        <div>
            <h2>Ürün ve Hizmetler</h2>
            <small>{{ config('document_prototype.'.$screenType.'.hint', 'Ürün ve hizmetleri girin.') }}</small>
        </div>
        @if($editable)
            <button type="button" class="v65-reference-fast-indicator" x-on:click="fastEntry = !fastEntry"
                x-bind:aria-pressed="fastEntry.toString()" aria-controls="v65-reference-quick-{{ $screenType }}"
                x-text="fastEntry ? 'Hızlı satır girişi açık' : 'Hızlı satır girişi kapalı'">Hızlı satır girişi açık</button>
        @else
            <span class="v65-reference-fast-indicator v65-reference-source-indicator">Kaynak belge satırları</span>
        @endif
    </div>
    @if($editable)
        <div class="v65-reference-products-tools">
            <div class="v65-reference-quick-product" x-show="fastEntry">
                <label for="v65-reference-quick-{{ $screenType }}" class="sr-only">Ürün kodu, barkod veya ürün adı ara</label>
                <input id="v65-reference-quick-{{ $screenType }}" type="search"
                    wire:model.live.debounce.250ms="quickProductSearch"
                    data-v65-reference-quick-input
                    placeholder="Ürün kodu, barkod veya ürün adını arayın..."
                    autocomplete="off" aria-label="Ürün kodu, barkod veya ürün adı ara">
                @if(mb_strlen(trim((string) $quickSearch)) >= 2)
                    <div class="v65-reference-suggestions" role="group" aria-label="Ürün arama sonuçları">
                        @forelse($products ?? [] as $product)
                            <button type="button" wire:click="insertQuickProduct({{ $product->id }})"
                                data-v65-reference-quick-choice>
                                <strong>{{ $product->name }}</strong>
                                <small>{{ $product->code }}@if($product->barcode) · {{ $product->barcode }}@endif</small>
                            </button>
                        @empty
                            <span class="v65-reference-no-product">Eşleşen aktif ürün bulunamadı</span>
                        @endforelse
                    </div>
                @endif
            </div>
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
