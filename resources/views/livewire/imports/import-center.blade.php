<div class="space-y-6">
    <div class="flex gap-2 items-center">
        <h1>İthalat Merkezi</h1>
        @can('import_shipments.create')
            <button type="button" wire:click="newFile">Yeni İthalat Dosyası</button>
        @endcan
    </div>

    @can('import_shipments.create')
        @if($sourcePeriods->isNotEmpty())
            <section class="space-y-3">
                <h2>Önceki Dönemden Açık İthalat Taşı</h2>
                <label>Kaynak Dönem
                    <select wire:model.live="carrySourcePeriodId">
                        <option value="">Seçin</option>
                        @foreach($sourcePeriods as $sourcePeriod)
                            <option value="{{ $sourcePeriod->id }}">{{ $sourcePeriod->year }}</option>
                        @endforeach
                    </select>
                </label>
                @if($carrySourcePeriodId)
                    <label>Kaynak İthalat Dosyası
                        <select wire:model="carrySourceImportFileId">
                            <option value="">Seçin</option>
                            @foreach($carrySourceFiles as $sourceFile)
                                <option value="{{ $sourceFile->id }}">
                                    {{ $sourceFile->number }} · {{ $sourceFile->status }} · ETA {{ $sourceFile->eta ?? '—' }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <button type="button" wire:click="carryFromPeriod" @disabled(!$carrySourceImportFileId)>
                        Yeni Döneme Taşı
                    </button>
                    <p>Kaynak kayıt değişmez; kur kilidi ve hesaplanmış landed-cost snapshotları hedef dönemde yeniden oluşturulur.</p>
                @endif
            </section>
        @endif
    @endcan

    <section>
        <h2>İthalat Dosyaları</h2>
        <table>
            <thead><tr><th>No</th><th>Tedarikçi</th><th>Ülke</th><th>ETD</th><th>ETA</th><th>PB</th><th>Kur</th><th>Durum</th><th></th></tr></thead>
            <tbody>
            @foreach($files as $row)
                <tr>
                    <td>{{ $row->number }}</td>
                    <td>{{ $row->supplier?->title }}</td>
                    <td>{{ $row->country }}</td>
                    <td>{{ $row->etd?->format('d.m.Y') }}</td>
                    <td>{{ $row->eta?->format('d.m.Y') }}</td>
                    <td>{{ $row->currency }}</td>
                    <td>{{ $row->exchange_rate ?? '—' }}</td>
                    <td>{{ $row->status }}</td>
                    <td><button type="button" wire:click="selectFile({{ $row->id }})">Aç</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>

    <section class="space-y-3">
        <h2>{{ $file?->number ?? 'Yeni İthalat Dosyası' }}</h2>
        @if($file?->source_period_id)
            <div>
                Devir kaynağı: dönem #{{ $file->source_period_id }} ·
                {{ $file->source_number }} (#{{ $file->source_import_file_id }})
            </div>
        @endif
        <label>Tedarikçi
            <select wire:model="supplierContactId">
                <option value="">Seçin</option>
                @foreach($contacts as $contact)
                    <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                @endforeach
            </select>
        </label>
        <label>Ülke <input wire:model="country"></label>
        <label>Teslim Şekli <input wire:model="incoterm"></label>
        <label>Para Birimi <input wire:model="currency" maxlength="3"></label>
        <label>Kur <input wire:model="exchangeRate" @disabled($file?->exchange_rate_locked_at)></label>
        <label>ETD <input type="date" wire:model="etd"></label>
        <label>ETA <input type="date" wire:model="eta"></label>
        <label>Varsayılan Kabul Lokasyonu
            <select wire:model="receivingLocationId">
                <option value="">Seçin</option>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                @endforeach
            </select>
        </label>
        @if(!$file || !in_array($file->status, ['received','closed']))
            <label>Durum
                <select wire:model="fileStatus">
                    <option value="draft">Taslak</option>
                    <option value="in_transit">Yolda</option>
                    <option value="customs">Gümrükte</option>
                </select>
            </label>
            <label>Not <textarea wire:model="fileNotes"></textarea></label>
            @canany(['import_shipments.create','import_shipments.update'])
                <button type="button" wire:click="saveFile">Dosyayı Kaydet</button>
            @endcanany
        @else
            <div>Kur tarihi: {{ $file->exchange_rate_date?->format('d.m.Y') }} · Kilitli kur: {{ $file->exchange_rate }}</div>
        @endif
    </section>

    @if($file)
        <section class="space-y-3">
            <h2>Konteynerler</h2>
            @if(!$file->isLocked())
                @can('import_shipments.update')
                    <div>
                        <input wire:model="containerNo" placeholder="Konteyner No">
                        <input wire:model="containerType" placeholder="Tip">
                        <input wire:model="sealNo" placeholder="Mühür No">
                        <input wire:model="containerWeight" placeholder="Brüt kg">
                        <input wire:model="containerVolume" placeholder="m³">
                        <input type="date" wire:model="containerEtd">
                        <input type="date" wire:model="containerEta">
                        <button type="button" wire:click="saveContainer">{{ $containerEditId ? 'Güncelle' : 'Ekle' }}</button>
                    </div>
                @endcan
            @endif
            <table>
                <thead><tr><th>No</th><th>Tip</th><th>Mühür</th><th>Ağırlık</th><th>Hacim</th><th>ETA</th><th>Durum</th><th></th></tr></thead>
                <tbody>
                @foreach($file->containers as $container)
                    <tr>
                        <td>{{ $container->container_no }}</td>
                        <td>{{ $container->container_type }}</td>
                        <td>{{ $container->seal_no }}</td>
                        <td>{{ $container->gross_weight_kg }}</td>
                        <td>{{ $container->volume_cbm }}</td>
                        <td>{{ $container->eta?->format('d.m.Y') }}</td>
                        <td>{{ $container->status }}</td>
                        <td>
                            @if(!$file->isLocked())
                                @can('import_shipments.update')
                                    <button type="button" wire:click="editContainer({{ $container->id }})">Düzenle</button>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>

        <section class="space-y-3">
            <h2>Koli / Parça Eşleştirme</h2>
            @if(!$file->isLocked())
                @can('import_shipments.update')
                    <div>
                        <select wire:model="packageContainerId">
                            <option value="">Konteyner</option>
                            @foreach($file->containers as $container)
                                <option value="{{ $container->id }}">{{ $container->container_no }}</option>
                            @endforeach
                        </select>
                        <input wire:model="cartonNo" placeholder="Koli">
                        <input wire:model="componentName" placeholder="Parça / açıklama">
                        <select wire:model="packageProductId">
                            <option value="">Ürün eşleşmemiş</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }}</option>
                            @endforeach
                        </select>
                        <select wire:model="packageLocationId">
                            <option value="">Lokasyon</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                        <input wire:model="packageQuantity" placeholder="Miktar">
                        <input wire:model="packageUnitPrice" placeholder="Birim fiyat {{ $file->currency }}">
                        <input wire:model="packageWeight" placeholder="kg">
                        <input wire:model="packageVolume" placeholder="m³">
                        <button type="button" wire:click="savePackage">{{ $packageEditId ? 'Güncelle' : 'Eşleştir / Ekle' }}</button>
                    </div>
                @endcan
            @endif
            <table>
                <thead><tr><th>Konteyner</th><th>Koli</th><th>Parça</th><th>Ürün</th><th>Miktar</th><th>Değer</th><th>kg</th><th>m³</th><th>Landed/Adet</th><th>Durum</th><th></th></tr></thead>
                <tbody>
                @foreach($file->packages as $package)
                    <tr>
                        <td>{{ $package->container?->container_no ?? $package->container_id }}</td>
                        <td>{{ $package->carton_no }}</td>
                        <td>{{ $package->component_name }}</td>
                        <td>{{ $package->product?->code ?? 'Eşleşmedi' }}</td>
                        <td>{{ $package->quantity }}</td>
                        <td>{{ $package->unit_price }} {{ $file->currency }}</td>
                        <td>{{ $package->weight_kg }}</td>
                        <td>{{ $package->volume_cbm }}</td>
                        <td>{{ $package->landed_unit_cost_try ?? '—' }}</td>
                        <td>{{ $package->status }}</td>
                        <td>
                            @if(!$file->isLocked())
                                @can('import_shipments.update')
                                    <button type="button" wire:click="editPackage({{ $package->id }})">Düzenle</button>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>

        <section class="space-y-3">
            <h2>Maliyet Kalemleri</h2>
            @if(!$file->isLocked())
                @can('import_shipments.update')
                    <div>
                        <input wire:model="costName" placeholder="Maliyet kalemi">
                        <input wire:model="costAmount" placeholder="Tutar">
                        <input wire:model="costCurrency" maxlength="3" placeholder="PB">
                        <input wire:model="costExchangeRate" placeholder="Farklı PB ise kur">
                        <select wire:model="costBasis">
                            <option value="value">Ürün Değeri</option>
                            <option value="weight">Ağırlık</option>
                            <option value="volume">Hacim</option>
                        </select>
                        <button type="button" wire:click="saveCost">{{ $costEditId ? 'Güncelle' : 'Gider Ekle' }}</button>
                    </div>
                @endcan
            @endif
            <table>
                <thead><tr><th>Kalem</th><th>Tutar</th><th>PB</th><th>Kur</th><th>TRY</th><th>Dağıtım</th><th></th></tr></thead>
                <tbody>
                @foreach($file->costItems as $item)
                    <tr>
                        <td>{{ $item->name }}</td>
                        <td>{{ $item->amount }}</td>
                        <td>{{ $item->currency }}</td>
                        <td>{{ $item->exchange_rate ?? '—' }}</td>
                        <td>{{ $item->amount_try ?? '—' }}</td>
                        <td>{{ $item->allocation_basis }}</td>
                        <td>
                            @if(!$file->isLocked())
                                @can('import_shipments.update')
                                    <button type="button" wire:click="editCost({{ $item->id }})">Düzenle</button>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            @if(!$file->isLocked())
                @can('import_shipments.update')
                    <button type="button" wire:click="recalculate">Landed Cost Yeniden Hesapla</button>
                @endcan
                @can('import_shipments.receive')
                    <label>Stoğa Giriş Tarihi <input type="date" wire:model="receivingDate"></label>
                    <button type="button" wire:click="receive">Stoğa Al ve Kuru Sabitle</button>
                @endcan
            @elseif($file->status === 'received')
                @can('import_shipments.close')
                    <button type="button" wire:click="close">Dosyayı Kapat ve Ürün Maliyetini İşle</button>
                @endcan
            @endif
        </section>

        @if($fileProfitability)
            <section class="space-y-3">
                <h2>Dosya Kârlılığı / Satış Sonucu</h2>
                <div>
                    Satış: {{ $fileProfitability['sales_try'] }} TRY ·
                    Maliyet: {{ $fileProfitability['cost_try'] }} TRY ·
                    Brüt Kâr: {{ $fileProfitability['profit_try'] }} TRY ·
                    Marj: %{{ $fileProfitability['margin_rate'] }}
                </div>
                <p>{{ $fileProfitability['note'] }}</p>
                <table>
                    <thead><tr><th>Ürün ID</th><th>İthal Miktar</th><th>Atfedilen Satış Miktarı</th><th>Satış</th><th>Maliyet</th><th>Kâr</th><th>Marj</th></tr></thead>
                    <tbody>
                    @foreach($fileProfitability['rows'] as $row)
                        <tr>
                            <td>{{ $row['product_id'] }}</td>
                            <td>{{ $row['import_quantity'] }}</td>
                            <td>{{ $row['attributable_sold_quantity'] }}</td>
                            <td>{{ $row['sales_try'] }}</td>
                            <td>{{ $row['cost_try'] }}</td>
                            <td>{{ $row['profit_try'] }}</td>
                            <td>%{{ $row['margin_rate'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </section>

            <section>
                <h2>Konteyner Kârlılığı</h2>
                <table>
                    <thead><tr><th>Konteyner</th><th>Satış</th><th>Maliyet</th><th>Kâr</th><th>Marj</th><th>Atıf Esası</th></tr></thead>
                    <tbody>
                    @foreach($containerProfitability as $row)
                        <tr>
                            <td>{{ $row['container_no'] }}</td>
                            <td>{{ $row['sales_try'] }}</td>
                            <td>{{ $row['cost_try'] }}</td>
                            <td>{{ $row['profit_try'] }}</td>
                            <td>%{{ $row['margin_rate'] }}</td>
                            <td>{{ $row['allocation_basis'] === 'matched_quantity' ? 'Koli eşleşmesi / gerçek miktar' : 'Hacim payı' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </section>
        @endif
    @endif
</div>
