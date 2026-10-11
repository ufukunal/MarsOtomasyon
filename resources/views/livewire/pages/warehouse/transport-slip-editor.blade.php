<div class="wsl-shell stack">
    <header class="wsl-head">
        <div>
            <h2>{{ $mode === 'new' ? 'Yeni Ambar Fişi' : ($mode === 'edit' ? 'Ambar Fişini Düzenle' : 'Ambar Fişi') }}</h2>
            <p class="wsl-sub">Bağımsız taşıma fişi. Sipariş ve irsaliye referansları opsiyoneldir.</p>
        </div>
        <div class="wsl-head-actions">
            <span @class(['wsl-badge', 'gray' => ! $slip || in_array($slip->status, ['draft', 'cancelled'], true), 'warn' => $slip?->status === 'ready', 'ok' => $slip?->status === 'completed'])>{{ $slip ? ($statusLabels[$slip->status] ?? $slip->status) : 'Taslak' }}</span>
            @if($mode === 'detail' && $slip && in_array($slip->status, ['draft', 'ready'], true))
                @can('warehouse_slips.update')
                    <a class="button-link" href="{{ route('warehouse.transport.edit', ['id' => $slip->id]) }}">Düzenle</a>
                @endcan
            @elseif(! $readonly)
                <button class="button-link" type="submit" form="wsl-transport-form">Kaydet</button>
            @endif
            <a class="button-secondary" href="{{ route('warehouse.transport.index') }}">Listeye Dön</a>
        </div>
    </header>

    <div class="wsl-info"><strong>Bağımsız belge:</strong> Ambar taşıma fişi satış irsaliyesi veya stok düzeltme fişi değildir. Kaydedilmesi stok hareketi oluşturmaz.</div>

    <form id="wsl-transport-form" wire:submit="save" class="stack">
        <section class="wsl-card panel">
            <div class="wsl-card-head">Fiş Bilgileri</div>
            <div class="wsl-card-body wsl-grid">
                <label class="wsl-field"><span class="field-label">Ambar Fişi No</span><input value="{{ $slip?->number ?? 'Otomatik' }}" readonly></label>
                <label class="wsl-field"><span class="field-label">Tarih *</span><input type="date" wire:model="date" @disabled($readonly)></label>
                <label class="wsl-field"><span class="field-label">Firma / Cari *</span>
                    <select wire:model.live="contactId" @disabled($readonly)>
                        <option value="">Seçiniz</option>
                        @foreach($contacts as $contact)<option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>@endforeach
                    </select>
                </label>
                <label class="wsl-field"><span class="field-label">Cari Kodu</span>
                    <input readonly value="{{ $contacts->firstWhere('id', $contactId)?->code ?? '' }}">
                </label>
                <label class="wsl-field"><span class="field-label">Sipariş Referansı</span>
                    <select wire:model="orderReference" @disabled($readonly)>
                        <option value="">— Opsiyonel —</option>
                        @foreach($orderOptions as $order)<option value="{{ $order->number }}">{{ $order->number }}</option>@endforeach
                        @if($orderReference && ! $orderOptions->contains('number', $orderReference))
                            <option value="{{ $orderReference }}">{{ $orderReference }}</option>
                        @endif
                    </select>
                </label>
                <label class="wsl-field"><span class="field-label">İrsaliye Referansı</span>
                    <select wire:model="dispatchReference" @disabled($readonly)>
                        <option value="">— Opsiyonel —</option>
                        @foreach($dispatchOptions as $dispatch)<option value="{{ $dispatch->number }}">{{ $dispatch->number }}</option>@endforeach
                        @if($dispatchReference && ! $dispatchOptions->contains('number', $dispatchReference))
                            <option value="{{ $dispatchReference }}">{{ $dispatchReference }}</option>
                        @endif
                    </select>
                </label>
            </div>
        </section>

        <section class="wsl-card panel">
            <div class="wsl-card-head">Taşıma / Gideceği Ambar</div>
            <div class="wsl-card-body">
                <div class="wsl-grid">
                    <label class="wsl-field"><span class="field-label">Gönderim Tipi *</span>
                        <select wire:model="shippingType" @disabled($readonly)>
                            @foreach(['Ambar', 'Nakliye', 'Kargo', 'Elden Teslim'] as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                        </select>
                    </label>
                    <label class="wsl-field"><span class="field-label">Taşıyıcı / Ambar Firması *</span>
                        <div class="wsl-carrier-picker">
                            <select wire:model.live="carrierOption" @disabled($readonly)>
                                <option value="">Seçiniz</option>
                                @foreach($carrierOptions as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                                <option value="__other__">+ Yeni taşıyıcı / ambar firması</option>
                            </select>
                            @if($carrierOption === '__other__' || ($carrier !== '' && ! $carrierOptions->contains($carrier)))
                                <input wire:model.blur="carrier" placeholder="Firma / ambar adı" @disabled($readonly)>
                            @endif
                        </div>
                    </label>
                    <label class="wsl-field"><span class="field-label">Koli Adedi *</span><input type="number" min="1" max="500" step="1" wire:model.live="packageCount" @disabled($readonly)></label>
                    <label class="wsl-field"><span class="field-label">Ambar Takip / Teslim No</span><input wire:model="trackingNumber" @disabled($readonly)></label>
                    <label class="wsl-field"><span class="field-label">Gideceği Ambar *</span><input wire:model="destinationWarehouse" @disabled($readonly)></label>
                    <label class="wsl-field"><span class="field-label">Toplam Ağırlık (kg)</span><input type="number" min="0" step="0.01" wire:model="weightKg" @disabled($readonly)></label>

                </div>
                <div class="wsl-checks">
                    <label><input type="checkbox" wire:model.live="containsGlass" @disabled($readonly)> Cam İçeriyor</label>
                    <label><input type="checkbox" wire:model.live="fragile" @disabled($readonly)> Kırılabilir / Hassas</label>
                    <label><input type="checkbox" wire:model.live="keepUpright" @disabled($readonly)> Dik Taşınmalı</label>
                    <label><input type="checkbox" wire:model.live="doNotStack" @disabled($readonly)> Üstüne Yük Konmaz</label>
                </div>
            </div>
        </section>

        <section class="wsl-card panel">
            <div class="wsl-card-head"><strong>Ürünler / Koli İçeriği</strong>
                @unless($readonly)<button class="button-link" type="button" wire:click="addItem">+ Ürün Ekle</button>@endunless
            </div>
            @unless($readonly)
                <div class="wsl-product-search">
                    <label for="wsl-product-search"><strong>Ürün ara</strong></label>
                    <input id="wsl-product-search" type="search" wire:model.live.debounce.300ms="productSearch" placeholder="Ürün kodu veya adına göre ara...">
                    <small>Seçeneklerde ilk 80 eşleşme listelenir. Seçili ürünler arama değişse de korunur.</small>
                </div>
            @endunless
            <div class="table-scroll"><table class="data-table wsl-items">
                <thead><tr><th>Ürün Kodu</th><th>Ürün Adı</th><th>Miktar</th><th>Birim</th>@unless($readonly)<th>İşlem</th>@endunless</tr></thead>
                <tbody>
                    @foreach($items as $i => $row)
                        @php($selectedProduct = $products->firstWhere('id', $row['product_id'] ?? null))
                        <tr wire:key="transport-item-{{ $i }}">
                            <td>
                                @if($readonly)
                                    {{ $selectedProduct?->code ?? '—' }}
                                @else
                                    <select wire:model.live="items.{{ $i }}.product_id">
                                        <option value="">Ürün Seçiniz</option>
                                        @foreach($products as $product)<option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }}</option>@endforeach
                                    </select>
                                @endif
                            </td>
                            <td>{{ $selectedProduct?->name ?? '—' }}</td>
                            <td><input type="number" min="0.001" step="0.001" wire:model="items.{{ $i }}.quantity" @disabled($readonly)></td>
                            <td>{{ $selectedProduct?->unit?->name ?? '—' }}</td>
                            @unless($readonly)<td><button type="button" class="button-secondary" wire:click="removeItem({{ $i }})">Kaldır</button></td>@endunless
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        </section>

        <section class="wsl-card panel">
            <div class="wsl-card-head">Firma / Teslimat İletişimi</div>
            <div class="wsl-card-body wsl-grid">
                <label class="wsl-field"><span class="field-label">Teslim Edilecek Firma *</span><input wire:model="deliveryCompany" @disabled($readonly)></label>
                <label class="wsl-field"><span class="field-label">Yetkili Kişi</span><input wire:model="deliveryPerson" @disabled($readonly)></label>
                <label class="wsl-field"><span class="field-label">Telefon *</span><input wire:model="deliveryPhone" @disabled($readonly)></label>
                <label class="wsl-field"><span class="field-label">İl *</span><input wire:model="deliveryCity" @disabled($readonly)></label>
                <label class="wsl-field"><span class="field-label">İlçe *</span><input wire:model="deliveryDistrict" @disabled($readonly)></label>
                <label class="wsl-field wsl-wide"><span class="field-label">Teslimat Adresi *</span><textarea wire:model="deliveryAddress" rows="2" @disabled($readonly)></textarea></label>
                <label class="wsl-field wsl-wide"><span class="field-label">Sevk / Taşıma Notu</span><textarea wire:model="note" rows="2" @disabled($readonly)></textarea></label>
            </div>
        </section>

        <div class="wsl-summary">
            <div><small>Koli Adedi</small><strong>{{ $packageCount }}</strong></div>
            <div><small>Cam</small><strong>{{ $containsGlass ? 'VAR' : 'YOK' }}</strong></div>
            <div><small>Kırılabilir</small><strong>{{ $fragile ? 'EVET' : 'HAYIR' }}</strong></div>
            <div><small>Belge Durumu</small><strong>{{ $slip ? ($statusLabels[$slip->status] ?? $slip->status) : 'Taslak' }}</strong></div>
        </div>

        <div class="wsl-warn"><strong>Baskı:</strong> Ambar fişi A4 dikeyde aynı kaydın iki A5 kopyasını üretir. Orta çizgiden kesildiğinde iki ayrı A5 fiş elde edilir. Koli etiketi koli adedi kadar üretilir.</div>

        @if($errors->any())<div class="alert alert-warning" role="alert">
            <strong>Eksik veya geçersiz alanlar</strong>
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>@endif

        <div class="wsl-footer">
            <a class="button-secondary" href="{{ route('warehouse.transport.index') }}">← Listeye Dön</a>
            <div class="wsl-grow"></div>
            <button type="button" class="button-secondary" wire:click="openPreview('slip')">Fiş Önizle</button>
            @if($mode === 'detail' && $slip)
                <a class="button-secondary" target="_blank" rel="noopener" href="{{ route('warehouse.transport.print', ['slip' => $slip->id, 'format' => 'slip', 'autoprint' => 1]) }}">Fiş Yazdır / PDF</a>
            @else
                <button type="button" class="button-secondary" wire:click="openPreview('slip', true)">Fiş Yazdır / PDF</button>
            @endif
            <button type="button" class="button-secondary" wire:click="openPreview('labels')">Etiket Önizle</button>
            @if($mode === 'detail' && $slip)
                <a class="button-secondary" target="_blank" rel="noopener" href="{{ route('warehouse.transport.print', ['slip' => $slip->id, 'format' => 'labels', 'autoprint' => 1]) }}">Etiket Yazdır</a>
            @else
                <button type="button" class="button-secondary" wire:click="openPreview('labels', true)">Etiket Yazdır</button>
            @endif
            @if($mode === 'detail' && $slip)
                @can('warehouse_slips.create')
                    <button type="button" wire:click="duplicate" class="button-secondary">Kopyala</button>
                @endcan
                @if(in_array($slip->status, ['draft', 'ready'], true))
                    @can('warehouse_slips.update')
                        <a class="button-link" href="{{ route('warehouse.transport.edit', ['id' => $slip->id]) }}">Düzenle</a>
                    @endcan
                @endif
                @if(in_array($slip->status, ['draft', 'ready', 'handed_over'], true))
                    @can('warehouse_slips.update')
                        <button type="button" class="button-link" wire:click="advance">{{ ['draft' => 'Hazır', 'ready' => 'Ambara Teslim', 'handed_over' => 'Tamamlandı'][$slip->status] }}</button>
                    @endcan
                    @can('warehouse_slips.cancel')
                        <button type="button" class="button-secondary" wire:confirm="Bu ambar fişi iptal edilecek. Devam edilsin mi?" wire:click="cancel">İptal</button>
                    @endcan
                @endif
            @endif
            @unless($readonly)
                <button type="submit" class="button-link">Kaydet</button>
            @endunless
        </div>
    </form>
    @error('preview')<p class="alert alert-warning">{{ $message }}</p>@enderror
    @if($preview && ($slip || $previewHtml))
        <div class="wsl-modal-backdrop" role="presentation" wire:key="transport-preview-{{ $preview }}">
            <section class="wsl-modal" role="dialog" aria-modal="true" aria-label="{{ $preview === 'slip' ? 'Ambar Fişi Önizleme · A4 / 2×A5' : 'Koli Etiketleri Önizleme' }}">
                <header class="wsl-modal-header">
                    <strong>{{ $preview === 'slip' ? 'Ambar Fişi Önizleme · A4 / 2×A5' : 'Koli Etiketleri Önizleme' }}</strong>
                    <div class="form-row">
                        @if($mode === 'detail' && $slip)
                            <a class="button-link" target="_blank" rel="noopener" href="{{ route('warehouse.transport.print', ['slip' => $slip->id, 'format' => $preview, 'autoprint' => 1]) }}">Yazdır / PDF</a>
                        @else
                            <button type="button" class="button-link" onclick="document.getElementById('wsl-preview-frame').contentWindow.print()">Yazdır / PDF</button>
                        @endif
                        <button type="button" class="button-secondary" wire:click="closePreview">Kapat</button>
                    </div>
                </header>
                @if($mode === 'detail' && $slip)
                    <iframe id="wsl-preview-frame" title="Ambar baskı önizlemesi" src="{{ route('warehouse.transport.print', ['slip' => $slip->id, 'format' => $preview]) }}"></iframe>
                @else
                    <iframe id="wsl-preview-frame" title="Ambar baskı önizlemesi" srcdoc="{{ $previewHtml }}" @if($previewPrint) onload="this.contentWindow.print()" @endif></iframe>
                @endif
            </section>
        </div>
    @endif
</div>
