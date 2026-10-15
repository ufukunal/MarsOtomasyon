<div class="document-workspace stack">
    @php
        $documentIndexRoute = match (true) {
            $this instanceof \App\Livewire\Sales\QuoteEditor => 'sales.quotes.index',
            $this instanceof \App\Livewire\Sales\SalesOrderEditor => 'sales.orders.index',
            $this instanceof \App\Livewire\Sales\DispatchEditor => 'sales.dispatches.index',
            default => 'sales.invoices.index',
        };
    @endphp
    <x-v65.document-heading :document="$document" :title="$title" section="Satış Yönetimi" :index-route="$documentIndexRoute" />
    @if($errors->any())
        <x-v65.notice tone="danger" title="Belge kontrolü">
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </x-v65.notice>
    @endif
    @if($documentDirty)
        <x-v65.notice tone="warning">Kaydedilmemiş değişiklikler var. Belge işlemine devam etmeden önce Kaydet'i kullanın.</x-v65.notice>
    @endif

    @php
        $draftPreview = $this->draftTotalsPreview();
    @endphp

    @if(!$document || $document->status === 'draft')
        <form wire:submit="save" class="document-editor-form v65-doc-editor">
            <div class="v65-doc-contact-picker">
                <label class="field">Cari Ara
                    <input type="search" wire:model.live.debounce.300ms="contactSearch" placeholder="Kod veya unvan ile ara" autocomplete="off">
                </label>
                <x-v65.select label="Cari" wire:model.live="contactId" placeholder="Cari seçin" class="v65-doc-contact">
                    @foreach($this->contactOptions() as $contact)
                        <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                    @endforeach
                </x-v65.select>
                <div class="v65-doc-contact-actions">
                    <x-v65.button variant="secondary" size="sm" wire:click="applyContactPrices" wire:loading.attr="disabled" wire:target="applyContactPrices" wire:confirm="Manuel girdiğiniz satır fiyatları da seçili carinin fiyat listesine göre değiştirilecek. Devam edilsin mi?">Cari Fiyatlarını Uygula</x-v65.button>
                    <small>Cari değişince otomatik fiyatlar güncellenir; manuel fiyatlar yalnızca bu butonla yeniden hesaplanır.</small>
                </div>
            </div>

            <label class="field">Belge Tarihi <input type="date" wire:model="documentDate"></label>
            @unless($this instanceof \App\Livewire\Sales\DispatchEditor)
                <label class="field">Vade <input type="date" wire:model="dueDate"></label>
            @endunless
            @if($this instanceof \App\Livewire\Sales\QuoteEditor)
                <label class="field">Teklif Geçerlilik Tarihi <input type="date" wire:model="validUntil"></label>
            @endif
            <label class="field">Belge İskonto % <input wire:model.blur="discountRate" data-tr-decimal inputmode="decimal"></label>
            <label class="field v65-doc-notes">Not <textarea wire:model="notes" rows="3"></textarea></label>

            <div class="v65-doc-vat-toolbar" role="group" aria-label="Satırlara toplu KDV uygula">
                <label class="field">Toplu KDV % <input wire:model="bulkVatRate" inputmode="decimal" data-tr-decimal></label>
                <x-v65.button variant="secondary" size="sm" wire:click="applyVatToAll">Tüm Satırlara Uygula</x-v65.button>
                <x-v65.button variant="secondary" size="sm" wire:click="clearVat">KDV'yi Temizle</x-v65.button>
            </div>

            <section class="v65-doc-lines" aria-label="Belge kalemleri">
                <div class="v65-doc-lines-head">
                    <strong>Belge Kalemleri</strong>
                    <small>Miktar, fiyat ve vergi değişince taslak toplamları güncellenir.</small>
                </div>
                <div class="table-scroll document-lines v65-doc-table-scroll"><table class="data-table v65-doc-table">
                <thead>
                    <tr>
                        <th scope="col">#</th><th scope="col">Tür</th><th scope="col">Ürün / Açıklama</th><th scope="col">Birim</th><th scope="col" class="align-end">Miktar</th><th scope="col">Lokasyon</th>
                        <th scope="col" class="align-end">Fiyat</th><th scope="col" class="align-end">İsk. %</th><th scope="col" class="align-end">KDV %</th><th scope="col" class="align-end">Satır Net</th><th scope="col">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lines as $index => $line)
                        <tr wire:key="sales-line-{{ $line['row_key'] ?? $index }}">
                            <td>{{ $index + 1 }}</td>
                            <td>
                                <select wire:model.live="lines.{{ $index }}.line_kind" aria-label="Satır {{ $index + 1 }} tür">
                                    <option value="stock">Stok</option>
                                    <option value="service">Hizmet</option>
                                </select>
                            </td>
                            <td class="v65-doc-product-cell">
                                @if(($line['line_kind'] ?? 'stock') === 'service')
                                    <input wire:model="lines.{{ $index }}.description" placeholder="Hizmet açıklaması" aria-label="Satır {{ $index + 1 }} hizmet açıklaması">
                                @else
                                    <input type="search" class="v65-doc-product-search"
                                        wire:model.live.debounce.300ms="productSearches.{{ $line['row_key'] ?? $index }}"
                                        placeholder="Ürün kodu veya adıyla ara"
                                        aria-label="Satır {{ $index + 1 }} ürün ara" autocomplete="off">
                                    <select wire:model.live="lines.{{ $index }}.product_id" aria-label="Satır {{ $index + 1 }} ürün">
                                        <option value="">Ürün seçin</option>
                                        @foreach($this->productOptionsForLine((string) ($line['row_key'] ?? $index), (int) ($line['product_id'] ?? 0)) as $product)
                                            <option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }}</option>
                                        @endforeach
                                    </select>
                                    <input class="v65-doc-line-description" wire:model="lines.{{ $index }}.description" placeholder="Satır açıklaması (isteğe bağlı)" aria-label="Satır {{ $index + 1 }} açıklama">
                                @endif
                            </td>
                            <td>
                                @if(($line['line_kind'] ?? 'stock') === 'service')
                                    <span class="v65-doc-muted">—</span>
                                @else
                                    <select wire:model="lines.{{ $index }}.unit_id" aria-label="Satır {{ $index + 1 }} birim">
                                        <option value="">Birim</option>
                                        @foreach($units as $unit)
                                            <option value="{{ $unit->id }}">{{ $unit->code }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </td>
                            <td class="align-end"><input wire:model.blur="lines.{{ $index }}.quantity" inputmode="decimal" data-tr-decimal aria-label="Satır {{ $index + 1 }} miktar"></td>
                            <td>
                                @if(($line['line_kind'] ?? 'stock') === 'service')
                                    <span class="v65-doc-muted">—</span>
                                @else
                                    <select wire:model="lines.{{ $index }}.location_id" aria-label="Satır {{ $index + 1 }} depo">
                                        <option value="">Depo seçin</option>
                                        @foreach($locations as $location)
                                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </td>
                            <td class="align-end"><input wire:model.blur="lines.{{ $index }}.unit_price" inputmode="decimal" data-tr-decimal aria-label="Satır {{ $index + 1 }} birim fiyat"></td>
                            <td class="align-end"><input wire:model.blur="lines.{{ $index }}.line_discount_rate" inputmode="decimal" data-tr-decimal aria-label="Satır {{ $index + 1 }} iskonto"></td>
                            <td class="align-end"><input wire:model.blur="lines.{{ $index }}.vat_rate" inputmode="decimal" data-tr-decimal aria-label="Satır {{ $index + 1 }} KDV"></td>
                            <td class="v65-doc-line-actions">
                                <span class="v65-doc-line-net align-end">@if($draftPreview && isset($draftPreview->lines[$index])){{ \App\Support\Formatting\TrFormatter::money((string) $draftPreview->lines[$index]->lineTotal) }}@elseif(!$documentDirty && isset($line['line_total'])){{ \App\Support\Formatting\TrFormatter::money((string) $line['line_total']) }}@else—@endif</span>
                            </td>
                            <td><x-v65.button variant="danger" size="xs" wire:click="removeLine({{ $index }})" aria-label="Satır {{ $index + 1 }} sil">Sil</x-v65.button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
            </section>

            <x-v65.form-actions align="between" class="v65-doc-form-actions">
                <x-v65.button variant="secondary" wire:click="addLine">+ Satır Ekle</x-v65.button>
                <x-v65.button type="submit" wire:loading.attr="disabled" wire:target="save">Kaydet</x-v65.button>
            </x-v65.form-actions>
        </form>
    @elseif($document)
        <x-v65.document-readonly :document="$document" />
    @endif

    @if($document && $document->status === 'draft')
        <div class="v65-doc-print-only">
            <x-v65.document-readonly :document="$document" />
            <x-v65.document-summary :document="$document" />
        </div>
    @endif

    <x-v65.document-summary class="v65-doc-screen-summary" :document="$document" :preview="$draftPreview" />

    @if($document)
        <section class="document-actions panel v65-document-actions v65-doc-workflow" role="group" aria-label="Belge işlemleri">
            <h3 class="v65-doc-workflow-title">Belge İşlemleri</h3>
            @if(method_exists($this, 'internalReview') && $document->status === 'draft')
                <x-v65.button variant="secondary" wire:click="internalReview" wire:loading.attr="disabled" wire:target="internalReview">İç Onaya Gönder</x-v65.button>
                <x-v65.button variant="secondary" wire:click="customerReview" wire:loading.attr="disabled" wire:target="customerReview">Müşteri İncelemesine Gönder</x-v65.button>
            @endif
            @if(method_exists($this, 'approve') && in_array($document->status, ['internal_review','customer_review']))
                <button type="button" wire:click="approve">Onayla</button>
            @endif
            @if(method_exists($this, 'revision') && $document->number)
                <button type="button" wire:click="revision">Revizyon Oluştur</button>
            @endif
            @if(method_exists($this, 'toOrder') && $document->status === 'approved')
                <button type="button" wire:click="toOrder">Sipariş Oluştur</button>
            @endif
            @if(method_exists($this, 'confirm') && $document->status === 'draft')
                <label><input type="checkbox" wire:model="riskAccepted"> Risk uyarısını kabul ediyorum</label>
                <x-v65.button wire:click="confirm" wire:loading.attr="disabled" wire:target="confirm" wire:confirm="Kaydedilmiş siparişi onaylamak istiyor musunuz?">Siparişi Onayla</x-v65.button>
            @endif
            @if(method_exists($this, 'reserve') && $document->status === 'confirmed')
                <section class="v65-order-fulfillment" aria-label="Sipariş rezervasyon ve sevkiyat işlemleri">
                    <header class="v65-order-fulfillment-head">
                        <strong>Stok Rezervasyonu ve Sevkiyat</strong>
                        <small>Rezervasyon deposu ile sevk/fatura deposu birbirinden bağımsızdır.</small>
                    </header>
                    <div class="v65-order-fulfillment-lines">
                        @foreach($document->lines as $line)
                            <div class="v65-order-fulfillment-line" wire:key="order-fulfillment-{{ $line->id }}">
                                <div class="v65-order-fulfillment-item">
                                    <strong>{{ $line->line_no }} · {{ $line->description ?? 'Sipariş satırı' }}</strong>
                                    <span>Sipariş miktarı: {{ $line->quantity }} · Aktif rezervasyon (temel birim): <b>{{ $reservedQuantities[$line->id] ?? '0.000' }}</b></span>
                                </div>
                                <div class="v65-order-fulfillment-inputs">
                                    <label class="field">
                                        <span class="field-label">Sevk / Fatura Miktarı</span>
                                        <input wire:model="fulfillmentQuantities.{{ $line->id }}" inputmode="decimal" aria-label="Satır {{ $line->line_no }} sevk veya fatura miktarı">
                                    </label>
                                    <label class="field">
                                        <span class="field-label">Sevk / Fatura Deposu</span>
                                        <select wire:model="fulfillmentLocationIds.{{ $line->id }}" aria-label="Satır {{ $line->line_no }} sevk/fatura deposu">
                                            <option value="">Depo seçin</option>
                                            @foreach($locations as $location)
                                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    @if($line->line_kind === 'stock')
                                        <label class="field">
                                            <span class="field-label">Rezervasyon Deposu</span>
                                            <select wire:model="reservationLocationIds.{{ $line->id }}" aria-label="Satır {{ $line->line_no }} rezervasyon deposu">
                                                <option value="">Depo seçin</option>
                                                @foreach($locations as $location)
                                                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if($reservationNotice !== '')
                        <div class="v65-reservation-notice {{ $reservationNoticeLevel === 'success' ? 'is-success' : 'is-warning' }}" role="status" aria-live="polite">{{ $reservationNotice }}</div>
                    @endif
                    <div class="v65-order-fulfillment-actions">
                        @can('reservations.create')
                            <button type="button" wire:click="reserve" wire:loading.attr="disabled" wire:target="reserve">Rezerv Et</button>
                        @else
                            <small class="v65-reservation-permission">Stok rezervasyonu oluşturma yetkiniz yok.</small>
                        @endcan
                        <button type="button" wire:click="createDispatch" wire:loading.attr="disabled" wire:target="createDispatch">İrsaliye Oluştur</button>
                        <button type="button" wire:click="createInvoice" wire:loading.attr="disabled" wire:target="createInvoice">Fatura Oluştur</button>
                        <button type="button" wire:click="issueProforma" wire:loading.attr="disabled" wire:target="issueProforma">Proforma Oluştur</button>
                        <button class="button-danger" type="button" wire:click="cancelRemaining" wire:loading.attr="disabled" wire:target="cancelRemaining" wire:confirm="Siparişin kalan miktarını iptal etmek istediğinize emin misiniz?">Kalanı İptal Et</button>
                    </div>
                </section>
            @elseif(method_exists($this, 'issueProforma') && in_array($document->status, ['approved','confirmed']))
                <button type="button" wire:click="issueProforma">Proforma Oluştur</button>
            @endif
            @if(method_exists($this, 'post') && $document->status === 'draft')
                @if($this instanceof \App\Livewire\Sales\SalesInvoiceEditor)
                    @php
                        $invoiceRisk = $this->invoiceRiskProjection();
                    @endphp
                    @if($invoiceRisk?->knownLimitExceeded)
                        <div class="alert alert-warning" role="alert">
                            <strong>Cari risk limiti aşılıyor.</strong>
                            Mevcut cari net bakiyesi: {{ \App\Support\Formatting\TrFormatter::money($invoiceRisk->currentBalance) }} ₺;
                            fatura: {{ \App\Support\Formatting\TrFormatter::money($invoiceRisk->orderTotal) }} ₺;
                            tahmini risk: {{ \App\Support\Formatting\TrFormatter::money($invoiceRisk->knownExposure) }} ₺;
                            limit: {{ \App\Support\Formatting\TrFormatter::money($invoiceRisk->riskLimit) }} ₺.
                            <label><input type="checkbox" wire:model="riskAccepted"> Risk limitinin aşıldığını kabul ediyorum.</label>
                        </div>
                    @elseif($document->currency !== 'TRY')
                        <div class="alert alert-warning" role="status">
                            Fatura TRY dışındaki para birimindedir. TRY risk limiti olan carilerde doğrulanmış kur dönüşümü bulunmadığı için kesinleştirme engellenir.
                        </div>
                    @endif
                @endif
                <x-v65.button wire:click="post" wire:loading.attr="disabled" wire:target="post" wire:confirm="Son kaydedilen belge tutarlarını kontrol ettiniz mi? Belge kesinleştirilecek.">Kesinleştir</x-v65.button>
            @endif
            @if(method_exists($this, 'reverse') && $document->status === 'posted')
                <label class="field"><span class="field-label">Ters Kayıt Tarihi</span><input type="date" wire:model="reversalDate"></label>
                <label class="field"><span class="field-label">Ters Kayıt Gerekçesi</span><input wire:model="reversalReason"></label>
                <x-v65.button variant="danger" wire:click="reverse" wire:loading.attr="disabled" wire:target="reverse" wire:confirm="Kaydedilmiş belge için ters kayıt oluşturulacak. Devam edilsin mi?">Ters Kayıt</x-v65.button>
            @endif
        </section>
    @endif
</div>
