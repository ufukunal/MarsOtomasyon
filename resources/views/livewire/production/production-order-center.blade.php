<div class="space-y-6 v65-list-page">
    <section class="panel v65-list-panel">
        @teleport('.page-actions.v65-page-actions')
        <div class="v65-list-heading-actions">
            @can('production_orders.create')
                <button type="button" wire:click="newOrder">Yeni Emir</button>
            @endcan
        </div>
    @endteleport
        <x-v65.list-heading title="Üretim Emirleri">
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
            <thead><tr><th>No</th><th>Tarih</th><th>Mamul</th><th>Tip</th><th>Plan</th><th>Tamamlanan</th><th>Durum</th></tr></thead>
            <tbody>
            @foreach($orders as $row)
                <tr>
                    <td><button type="button" class="link-button v65-record-link" wire:click="selectOrder({{ $row->id }})">{{ $row->number ?? 'Taslak #'.$row->id }}</button></td>
                    <td>{{ $row->document_date?->format('d.m.Y') }}</td>
                    <td>@can('products.view')<a class="v65-record-link" href="{{ route('products.show', ['product' => $row->product_id]) }}">{{ $row->product->code }} · {{ $row->product->name }}</a>@else{{ $row->product->code }} · {{ $row->product->name }}@endcan</td>
                    <td>{{ $row->production_type }}</td>
                    <td>{{ $row->planned_quantity }}</td>
                    <td>{{ $row->completed_quantity }}</td>
                    <td>{{ $row->status }}</td>
                    
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <x-v65.list-footer :paginator="$orders" :per-page="$perPage" />
    </section>

    <section class="panel stack v65-editor-panel">
        <h2>{{ $order?->number ?? 'Yeni Üretim Emri' }}</h2>
        @if(!$order || $order->status === 'draft')
            <label>Tarih <input type="date" wire:model="documentDate"></label>
            <label>Mamul
                <select wire:model="productId">
                    <option value="">Seçin</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Reçete
                <select wire:model="recipeId">
                    <option value="">Seçin</option>
                    @foreach($recipes as $recipe)
                        <option value="{{ $recipe->id }}">#{{ $recipe->id }} · Rev.{{ $recipe->revision_no }}</option>
                    @endforeach
                </select>
            </label>
            <label>Planlanan Miktar <input data-tr-decimal wire:model="plannedQuantity"></label>
            <label>Üretim Tipi
                <select wire:model.live="productionType">
                    <option value="internal">İç Üretim</option>
                    <option value="subcontract">Fason</option>
                </select>
            </label>
            @if($productionType === 'subcontract')
                <label>Fasoncu Cari
                    <select wire:model="subcontractorContactId">
                        <option value="">Seçin</option>
                        @foreach($contacts as $contact)
                            <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Fason Lokasyon
                    <select wire:model="subcontractorLocationId">
                        <option value="">Seçin</option>
                        @foreach($subcontractorLocations as $location)
                            <option value="{{ $location->id }}">{{ $location->code }} · {{ $location->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <label>Not <textarea wire:model="notes"></textarea></label>
            @canany(['production_orders.create','production_orders.update'])
                <button type="button" wire:click="save">Taslağı Kaydet</button>
            @endcanany
            @if($order)
                @can('production_orders.update')
                    <button type="button" wire:click="confirm">Emri Onayla</button>
                @endcan
                @can('production_orders.cancel')
                    <button class="button-danger" type="button" wire:click="cancelRemaining">Taslağı İptal Et</button>
                @endcan
            @endif
        @endif

        @if($order && $order->status !== 'draft')
            <div>
                <strong>{{ $order->number }}</strong> · Reçete Rev.{{ $order->recipe_revision_no }} ·
                Kalan {{ $order->remainingQuantity() }}
                @if($order->sourceSalesOrder)
                    · Kaynak satış siparişi {{ $order->sourceSalesOrder->number }}
                @endif
            </div>

            <h3>Component Snapshot</h3>
            <div class="table-scroll v65-grid-scroll v65-list-table"><table class="data-table">
                <thead><tr><th>Component</th><th>Plan</th><th>Temel Plan</th></tr></thead>
                <tbody>
                @foreach($order->components as $component)
                    <tr>
                        <td>{{ $component->componentProduct->code }} · {{ $component->componentProduct->name }}</td>
                        <td>{{ $component->planned_quantity }}</td>
                        <td>{{ $component->planned_base_quantity }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>

            @if(in_array($order->status, ['confirmed','in_progress'], true))
                @can('production_orders.update')
                    <h3>Completion</h3>
                    <label>Tarih <input type="date" wire:model="completionDate"></label>
                    <label>Tamamlanan Miktar <input data-tr-decimal wire:model="completionQuantity"></label>

                    <div class="table-scroll v65-grid-scroll v65-list-table"><table class="data-table">
                        <thead><tr><th>Component</th><th>Actual Tüketim</th><th>Fire</th><th>Kaynak</th></tr></thead>
                        <tbody>
                        @foreach($order->components as $component)
                            <tr>
                                <td>{{ $component->componentProduct->code }} · {{ $component->componentProduct->name }}</td>
                                <td><input data-tr-decimal wire:model="consumptions.{{ $component->component_product_id }}.consumed_quantity"></td>
                                <td><input data-tr-decimal wire:model="consumptions.{{ $component->component_product_id }}.fire_quantity"></td>
                                <td>
                                    @if($order->production_type === 'subcontract')
                                        {{ $order->subcontractorLocation?->name }}
                                    @else
                                        <select wire:model="consumptions.{{ $component->component_product_id }}.location_id">
                                            <option value="">Seçin</option>
                                            @foreach($normalLocations as $location)
                                                <option value="{{ $location->id }}">{{ $location->code }} · {{ $location->name }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>

                    <h4>Mamul Çıktıları</h4>
                    @foreach($outputs as $index => $output)
                        <div class="form-row" wire:key="production-output-{{ $index }}">
                            <select wire:model="outputs.{{ $index }}.location_id">
                                <option value="">Hedef lokasyon</option>
                                @foreach($normalLocations as $location)
                                    <option value="{{ $location->id }}">{{ $location->code }} · {{ $location->name }}</option>
                                @endforeach
                            </select>
                            <input data-tr-decimal wire:model="outputs.{{ $index }}.quantity">
                            <button class="button-danger" type="button" wire:click="removeOutput({{ $index }})">Kaldır</button>
                        </div>
                    @endforeach
                    <button class="button-secondary" type="button" wire:click="addOutput">Output Lokasyonu Ekle</button>
                    <label>Completion Notu <textarea wire:model="completionNotes"></textarea></label>
                    <button type="button" wire:click="postCompletion">Completion Kesinleştir</button>
                @endcan
                @can('production_orders.cancel')
                    <button class="button-danger" type="button" wire:click="cancelRemaining">Kalanı İptal Et</button>
                @endcan
            @endif

            <h3>Completion Geçmişi</h3>
            <div class="table-scroll v65-grid-scroll v65-list-table"><table class="data-table">
                <thead><tr><th>ID</th><th>Tarih</th><th>Miktar</th>@can('cost.view')<th>Malzeme</th><th>Fason Hizmet</th><th>Birim Maliyet</th>@endcan<th>Ters</th></tr></thead>
                <tbody>
                @foreach($order->completions as $completion)
                    <tr>
                        <td>{{ $completion->id }}</td>
                        <td>{{ $completion->completion_date?->format('d.m.Y') }}</td>
                        <td>{{ $completion->completed_quantity }}</td>
                        @can('cost.view')
                            <td>{{ $completion->material_cost_total }}</td>
                            <td>{{ $completion->subcontract_service_cost_total }}</td>
                            <td>{{ $completion->production_unit_cost }}</td>
                        @endcan
                        <td>
                            @if(!$completion->reversal_of_id && $completion->reversals->isEmpty())
                                @can('production_orders.cancel')
                                    <input type="date" wire:model="reversalDate">
                                    <input wire:model="reversalReason" placeholder="Ters kayıt gerekçesi">
                                    <button class="button-danger" type="button" wire:click="reverseCompletion({{ $completion->id }})">Tersle</button>
                                @endcan
                            @elseif($completion->reversal_of_id)
                                #{{ $completion->reversal_of_id }} ters kaydı
                            @else
                                Terslendi
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </section>

    @foreach($errors->all() as $error)
        <div class="field-error">{{ $error }}</div>
    @endforeach
</div>
