<div class="wsl-shell stack">
    <header class="wsl-head">
        <div>
            <h2>{{ $slip ? 'Ambar Fişi · '.$slip->number : 'Yeni Ambar Fişi' }}</h2>
            <p class="wsl-sub">Bağımsız taşıma fişi. Sipariş ve irsaliye referansları opsiyoneldir.</p>
        </div>
        <div class="form-row">
            <span class="wsl-badge">{{ $slip ? ($statusLabels[$slip->status] ?? $slip->status) : 'Taslak' }}</span>
            <a class="button-secondary" href="{{ route('warehouse.transport.index') }}">Listeye Dön</a>
        </div>
    </header>

    <div class="wsl-info"><strong>Bağımsız belge:</strong> Ambar taşıma fişi satış irsaliyesi veya stok düzeltme fişi değildir. Kaydedilmesi stok hareketi oluşturmaz.</div>

    <form wire:submit="save" class="stack">
        <section class="wsl-card panel">
            <div class="wsl-card-head">Fiş Bilgileri</div>
            <div class="wsl-card-body wsl-grid">
                <label class="field"><span class="field-label">Ambar Fişi No</span><input value="{{ $slip?->number ?? 'Otomatik' }}" readonly></label>
                <label class="field"><span class="field-label">Tarih *</span><input type="date" wire:model="date" @disabled($readonly)></label>
                <label class="field"><span class="field-label">Firma / Cari *</span>
                    <select wire:model.live="contactId" @disabled($readonly)>
                        <option value="">Seçiniz</option>
                        @foreach($contacts as $contact)<option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>@endforeach
                    </select>
                </label>
                <label class="field"><span class="field-label">Cari Kodu</span>
                    <input readonly value="{{ $contacts->firstWhere('id', $contactId)?->code ?? '' }}">
                </label>
                <label class="field"><span class="field-label">Sipariş Referansı</span>
                    <input wire:model="orderReference" placeholder="Opsiyonel" @disabled($readonly)>
                </label>
                <label class="field"><span class="field-label">İrsaliye Referansı</span>
                    <input wire:model="dispatchReference" placeholder="Opsiyonel" @disabled($readonly)>
                </label>
            </div>
        </section>

        <section class="wsl-card panel">
            <div class="wsl-card-head">Taşıma / Gideceği Ambar</div>
            <div class="wsl-card-body">
                <div class="wsl-grid">
                    <label class="field"><span class="field-label">Gönderim Tipi *</span>
                        <select wire:model="shippingType" @disabled($readonly)>
                            @foreach(['Ambar', 'Nakliye', 'Kargo', 'Elden Teslim'] as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                        </select>
                    </label>
                    <label class="field"><span class="field-label">Taşıyıcı / Ambar Firması *</span>
                        <input wire:model.blur="carrier" placeholder="Taşıyıcı veya ambar adı" @disabled($readonly)>
                    </label>
                    <label class="field"><span class="field-label">Koli Adedi *</span><input type="number" min="1" max="500" step="1" wire:model.live="packageCount" @disabled($readonly)></label>
                    <label class="field"><span class="field-label">Ambar Takip / Teslim No</span><input wire:model="trackingNumber" @disabled($readonly)></label>
                    <label class="field"><span class="field-label">Gideceği Ambar *</span><input wire:model="destinationWarehouse" @disabled($readonly)></label>
                    <label class="field"><span class="field-label">Toplam Ağırlık (kg)</span><input type="number" min="0" step="0.01" wire:model="weightKg" @disabled($readonly)></label>
                    <label class="field"><span class="field-label">Çıkış Deposu</span>
                        <select wire:model="locationId" @disabled($readonly)>
                            <option value="">Seçiniz (opsiyonel)</option>
                            @foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->code }} · {{ $location->name }}</option>@endforeach
                        </select>
                    </label>
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
                <label class="field"><span class="field-label">Teslim Edilecek Firma *</span><input wire:model="deliveryCompany" @disabled($readonly)></label>
                <label class="field"><span class="field-label">Yetkili Kişi</span><input wire:model="deliveryPerson" @disabled($readonly)></label>
                <label class="field"><span class="field-label">Telefon *</span><input wire:model="deliveryPhone" @disabled($readonly)></label>
                <label class="field"><span class="field-label">İl *</span><input wire:model="deliveryCity" @disabled($readonly)></label>
                <label class="field"><span class="field-label">İlçe *</span><input wire:model="deliveryDistrict" @disabled($readonly)></label>
                <label class="field wsl-wide"><span class="field-label">Teslimat Adresi *</span><textarea wire:model="deliveryAddress" rows="2" @disabled($readonly)></textarea></label>
                <label class="field wsl-wide"><span class="field-label">Sevk / Taşıma Notu</span><textarea wire:model="note" rows="2" @disabled($readonly)></textarea></label>
            </div>
        </section>

        <div class="wsl-summary">
            <div><small>Koli Adedi</small><strong>{{ $packageCount }}</strong></div>
            <div><small>Cam</small><strong>{{ $containsGlass ? 'VAR' : 'YOK' }}</strong></div>
            <div><small>Kırılabilir</small><strong>{{ $fragile ? 'EVET' : 'HAYIR' }}</strong></div>
            <div><small>Belge Durumu</small><strong>{{ $slip ? ($statusLabels[$slip->status] ?? $slip->status) : 'Taslak' }}</strong></div>
        </div>

        <div class="wsl-warn"><strong>Baskı:</strong> A4 dikey sayfada aynı fişin iki A5 kopyası, koli etiketi baskısında ise numaralandırılmış {{ $packageCount }} etiket hazırlanır.</div>

        @if($errors->any())<div class="alert alert-warning" role="alert">
            <strong>Eksik veya geçersiz alanlar</strong>
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>@endif

        <div class="wsl-footer">
            <a class="button-secondary" href="{{ route('warehouse.transport.index') }}">← Listeye Dön</a>
            <div class="wsl-grow"></div>
            @if($slip)
                <a class="button-secondary" target="_blank" rel="noopener" href="{{ route('warehouse.transport.print', ['slip' => $slip->id, 'format' => 'slip']) }}">Fiş Önizle / Yazdır / PDF</a>
                <a class="button-secondary" target="_blank" rel="noopener" href="{{ route('warehouse.transport.print', ['slip' => $slip->id, 'format' => 'labels']) }}">Etiket Önizle / Yazdır</a>
                @can('warehouse_slips.create')<button type="button" wire:click="duplicate" class="button-secondary">Kopyala</button>@endcan
                @if(in_array($slip->status, ['draft', 'ready', 'handed_over'], true))
                    @can('warehouse_slips.update')
                        <button type="button" class="button-link" wire:click="advance">{{ ['draft' => 'Hazır', 'ready' => 'Ambara Teslim', 'handed_over' => 'Tamamlandı'][$slip->status] }}</button>
                    @endcan
                    @can('warehouse_slips.cancel')<button type="button" class="button-secondary" wire:confirm="Bu fişi iptal etmek istiyor musunuz?" wire:click="cancel">İptal</button>@endcan
                @endif
            @endif
            @unless($readonly)<button type="submit" class="button-link">Kaydet</button>@endunless
        </div>
    </form>
</div>
