<form wire:submit="save" class="stack">
    <x-tabs :tabs="['general'=>'Genel','price'=>'Fiyat','stock'=>'Stok','type'=>'Tip','components'=>'Bileşenler','config'=>'Konfigürasyon','images'=>'Görseller']" :active="$activeTab" />

    @if($activeTab === 'general')
        <section class="panel form-grid">
            <x-field.text label="Kod" wire:model="code" :disabled="(bool) $product" />
            <x-field.text label="Ad" wire:model="name" />
            <x-field.textarea label="Açıklama" wire:model="description" />
            <label class="field"><span class="field-label">Kategori</span><select wire:model="categoryId"><option value="">—</option>@foreach($categories as $row)<option value="{{ $row->id }}">{{ $row->name }}</option>@endforeach</select></label>
            <label class="field"><span class="field-label">Marka</span><select wire:model="brandId"><option value="">—</option>@foreach($brands as $row)<option value="{{ $row->id }}">{{ $row->name }}</option>@endforeach</select></label>
            <label class="field"><span class="field-label">Temel Birim</span><select wire:model="unitId">@foreach($units as $row)<option value="{{ $row->id }}">{{ $row->name }}</option>@endforeach</select></label>
            <x-field.text label="Barkod" wire:model="barcode" />
        </section>
    @elseif($activeTab === 'price')
        <section class="panel form-grid">
            <x-field.number label="Liste Fiyatı" wire:model="listPrice" />
            <x-field.number label="KDV %" wire:model="vatRate" />
            <x-field.toggle label="Girilen fiyat KDV dahil" wire:model="priceVatIncluded" />
            <x-field.text label="Para Birimi" wire:model="currency" />
        </section>
    @elseif($activeTab === 'stock')
        <section class="panel form-grid">
            <x-field.toggle label="Negatif stok izni" wire:model="allowNegativeStock" />
            <x-field.number label="Minimum Stok" kind="quantity" wire:model="minStock" />
            <x-field.select label="Kanal Stok Modu" wire:model="channelStockMode" :options="['stock'=>'Stok','production'=>'Üretim','manual'=>'Manuel']" />
        </section>
    @elseif($activeTab === 'type')
        <section class="panel form-grid">
            <x-field.select label="Ürün Tipi" wire:model.live="kind" :options="['normal'=>'Normal','set'=>'Set','configurable'=>'Konfigüre']" />
            <label class="field"><span class="field-label">Varyant Grubu</span><select wire:model="variantGroupId"><option value="">—</option>@foreach($variantGroups as $row)<option value="{{ $row->id }}">{{ $row->name }}</option>@endforeach</select></label>
            <x-field.toggle label="Aktif" wire:model="isActive" />
        </section>
    @elseif($activeTab === 'components')
        <section class="panel stack">
            @if(!$product || $kind !== 'set')
                <p>Bileşenler yalnız kaydedilmiş set ürünlerde düzenlenir.</p>
            @else
                <div class="form-row">
                    <x-field.lookup label="Bileşen Ürün" wire:model="componentProductId" :model="\App\Models\Period\Product::class" label-field="name" code-field="code" barcode-field="barcode" />
                    <input data-tr-decimal wire:model="componentQuantity" inputmode="decimal" placeholder="Miktar">
                    <button type="button" wire:click="saveSetComponent">{{ $componentLineId ? 'Güncelle' : 'Ekle' }}</button>
                </div>
                <table class="data-table"><thead><tr><th>Kod</th><th>Ürün</th><th>Miktar</th><th></th></tr></thead><tbody>
                @foreach($setLines as $line)<tr><td>{{ $line->componentProduct->code }}</td><td>{{ $line->componentProduct->name }}</td><td>{{ $line->quantity }}</td><td><button type="button" wire:click="editSetComponent({{ $line->id }})">Düzenle</button> <button type="button" wire:click="removeSetComponent({{ $line->id }})">Kaldır</button></td></tr>@endforeach
                </tbody></table>
                <strong>Satılabilir: {{ $product->setAvailability() }}</strong>
            @endif
        </section>
    @elseif($activeTab === 'config')
        <section class="panel stack">
            @if(!$product || $kind !== 'configurable')
                <p>Konfigürasyon yalnız kaydedilmiş konfigüre ürünlerde düzenlenir.</p>
            @else
                @foreach($configDefinitions as $definition)
                    <div class="panel">
                        <strong>{{ $definition->name }}</strong>
                        @foreach($definition->options as $option)<span class="badge">{{ $option->label }}</span>@endforeach
                        <button type="button" wire:click="editConfigGroup({{ $definition->id }})">Düzenle</button>
                    </div>
                @endforeach
                <x-field.text :label="$configDefinitionId ? 'Grup Adı' : 'Yeni Grup Adı'" wire:model="configName" />
                <x-field.toggle label="Zorunlu" wire:model="configRequired" />
                @foreach($configOptions as $i => $option)
                    <div class="form-row" wire:key="config-option-{{ $i }}">
                        <input wire:model="configOptions.{{ $i }}.label" placeholder="Seçenek etiketi">
                        <select wire:model="configOptions.{{ $i }}.component_product_id"><option value="">Bileşen yok</option>@foreach($products as $row)<option value="{{ $row->id }}">{{ $row->name }}</option>@endforeach</select>
                        <label><input type="checkbox" wire:model="configOptions.{{ $i }}.is_default"> Varsayılan</label>
                        <button type="button" wire:click="removeConfigOptionRow({{ $i }})">Kaldır</button>
                    </div>
                @endforeach
                <button type="button" wire:click="addConfigOptionRow">Seçenek Ekle</button>
                <button type="button" wire:click="saveConfigGroup">{{ $configDefinitionId ? 'Grubu Güncelle' : 'Grubu Kaydet' }}</button>
                @if($configDefinitionId)<button type="button" wire:click="cancelConfigEdit">Vazgeç</button>@endif
            @endif
        </section>
    @elseif($activeTab === 'images')
        @if($product)
            <livewire:products.product-images :product="$product" :key="'product-images-'.$product->id" />
        @else
            <section class="panel"><p>Görseller için önce ürün kartını kaydedin.</p></section>
        @endif
    @endif

    @foreach($errors->all() as $error)<div class="field-error">{{ $error }}</div>@endforeach
    <button type="submit">Ürünü Kaydet</button>
</form>
