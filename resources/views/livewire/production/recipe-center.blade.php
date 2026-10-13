<div class="space-y-6 v65-list-page">
    <section class="panel v65-list-panel">
        @teleport('.page-actions.v65-page-actions')
        <div class="v65-list-heading-actions">
            @can('production_recipes.create')
                <button type="button" wire:click="newRecipe">Yeni Reçete</button>
            @endcan
        </div>
    @endteleport
        <x-v65.list-heading title="Üretim Reçeteleri">
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
            <thead><tr><th>Mamul</th><th>Reçete</th><th>Rev.</th><th>Çıktı</th><th>Aktif</th><th></th></tr></thead>
            <tbody>
            @foreach($recipes as $recipe)
                <tr>
                    <td>@can('products.view')<a class="v65-record-link" href="{{ route('products.show', ['product' => $recipe->product_id]) }}">{{ $recipe->product->code }} · {{ $recipe->product->name }}</a>@else{{ $recipe->product->code }} · {{ $recipe->product->name }}@endcan</td>
                    <td><button type="button" class="link-button v65-record-link" wire:click="selectRecipe({{ $recipe->id }})">{{ $recipe->number }}</button></td>
                    <td>{{ $recipe->revision_no }}</td>
                    <td>{{ $recipe->output_quantity }}</td>
                    <td>{{ $recipe->is_active ? 'Evet' : 'Hayır' }}</td>
                    <td>
                        
                        @if(!$recipe->is_active)
                            @can('production_recipes.update')
                                <button type="button" wire:click="activate({{ $recipe->id }})">Aktif Yap</button>
                            @endcan
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <x-v65.list-footer :paginator="$recipes" :per-page="$perPage" />
    </section>

    <section class="panel stack v65-editor-panel">
        <h2>{{ $selectedRecipe ? 'Yeni Revizyon · '.$selectedRecipe->number.' Rev.'.$selectedRecipe->revision_no : 'Yeni Reçete' }}</h2>
        <label>Mamul
            <select wire:model="productId" @disabled($selectedRecipeId)>
                <option value="">Seçin</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }}</option>
                @endforeach
            </select>
        </label>
        <label>Reçete Çıktı Miktarı <input data-tr-decimal wire:model="outputQuantity"></label>

        <div class="table-scroll v65-grid-scroll v65-list-table"><table class="data-table">
            <thead><tr><th>Component</th><th>Birim</th><th>Miktar</th><th></th></tr></thead>
            <tbody>
            @foreach($lines as $index => $line)
                <tr wire:key="recipe-line-{{ $index }}">
                    <td>
                        <select wire:model="lines.{{ $index }}.component_product_id">
                            <option value="">Seçin</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <select wire:model="lines.{{ $index }}.unit_id">
                            <option value="">Seçin</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->code }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td><input data-tr-decimal wire:model="lines.{{ $index }}.quantity"></td>
                    <td><button class="button-danger" type="button" wire:click="removeLine({{ $index }})">Kaldır</button></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <button class="button-secondary" type="button" wire:click="addLine">Component Ekle</button>

        @canany(['production_recipes.create','production_recipes.update'])
            <button type="button" wire:click="save">
                {{ $selectedRecipeId ? 'Yeni Revizyon Oluştur' : 'Reçeteyi Oluştur' }}
            </button>
        @endcanany

        @if($selectedRecipe)
            <div>
                <strong>Seçili immutable snapshot:</strong>
                {{ $selectedRecipe->number }} Rev.{{ $selectedRecipe->revision_no }}
                @foreach($selectedRecipe->lines as $line)
                    <div>{{ $line->componentProduct->code }} · {{ $line->quantity }} {{ $line->unit->code }} · temel {{ $line->base_quantity }}</div>
                @endforeach
            </div>
        @endif
    </section>

    @foreach($errors->all() as $error)
        <div class="field-error">{{ $error }}</div>
    @endforeach
</div>
