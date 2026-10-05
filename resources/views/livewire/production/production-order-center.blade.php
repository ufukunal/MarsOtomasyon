<div class="space-y-6">
    <section class="panel">
        <div class="form-row">
            <h1>Üretim Emirleri</h1>
            @can('production_orders.create')
                <button type="button" wire:click="newOrder">Yeni Emir</button>
            @endcan
        </div>
        <table class="data-table">
            <thead><tr><th>No</th><th>Tarih</th><th>Mamul</th><th>Tip</th><th>Plan</th><th>Tamamlanan</th><th>Durum</th><th></th></tr></thead>
            <tbody>
            @foreach($orders as $row)
                <tr>
                    <td>{{ $row->number ?? 'Taslak #'.$row->id }}</td>
                    <td>{{ $row->document_date?->format('d.m.Y') }}</td>
                    <td>{{ $row->product->code }} · {{ $row->product->name }}</td>
                    <td>{{ $row->production_type }}</td>
                    <td>{{ $row->planned_quantity }}</td>
                    <td>{{ $row->completed_quantity }}</td>
                    <td>{{ $row->status }}</td>
                    <td><button type="button" wire:click="selectOrder({{ $row->id }})">Aç</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>

    <section class="panel space-y-3">
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
            <table class="data-table">
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
            </table>

            @if(in_array($order->status, ['confirmed','in_progress'], true))
                @can('production_orders.update')
                    <h3>Completion</h3>
                    <label>Tarih <input type="date" wire:model="completionDate"></label>
                    <label>Tamamlanan Miktar <input data-tr-decimal wire:model="completionQuantity"></label>

                    <table class="data-table">
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
                    </table>

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
                            <button type="button" wire:click="removeOutput({{ $index }})">Kaldır</button>
                        </div>
                    @endforeach
                    <button type="button" wire:click="addOutput">Output Lokasyonu Ekle</button>
                    <label>Completion Notu <textarea wire:model="completionNotes"></textarea></label>
                    <button type="button" wire:click="postCompletion">Completion Kesinleştir</button>
                @endcan
                @can('production_orders.cancel')
                    <button type="button" wire:click="cancelRemaining">Kalanı İptal Et</button>
                @endcan
            @endif

            <h3>Completion Geçmişi</h3>
            <table class="data-table">
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
                                    <button type="button" wire:click="reverseCompletion({{ $completion->id }})">Tersle</button>
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
            </table>
        @endif
    </section>

    @foreach($errors->all() as $error)
        <div class="field-error">{{ $error }}</div>
    @endforeach
</div>
