<div class="document-workspace stack">
    @php
        $documentIndexRoute = match (true) {
            $this instanceof \App\Livewire\Sales\QuoteEditor => 'sales.quotes.index',
            $this instanceof \App\Livewire\Sales\SalesOrderEditor => 'sales.orders.index',
            $this instanceof \App\Livewire\Sales\DispatchEditor => 'sales.dispatches.index',
            default => 'sales.invoices.index',
        };
    @endphp
    <x-v65.document-heading :document="$document" :title="$title" section="Satış Yönetimi" :index-route="$documentIndexRoute" :dirty="$documentDirty" />
    @if($errors->any())
        <x-v65.notice tone="danger" title="Belge kontrolü" data-v65-document-errors tabindex="-1">
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </x-v65.notice>
    @endif
    @if($documentDirty)
        <x-v65.notice tone="warning">Kaydedilmemiş değişiklikler var. Belge işlemine devam etmeden önce Kaydet'i kullanın.</x-v65.notice>
    @endif
    @if($document && $document->status === 'draft')
        <x-v65.document-sources class="v65-doc-draft-sources" :document="$document" />
    @endif

    @if($this instanceof \App\Livewire\Sales\SalesInvoiceEditor)
        <x-v65.contact-risk-overview :risk="$this->contactRiskOverview()" />
    @endif

    @php
        $draftPreview = $this->draftTotalsPreview();
    @endphp

    @if(!$document || $document->status === 'draft')
        <form wire:submit="save" class="document-editor-form v65-doc-editor">
            <div class="v65-doc-contact-picker">
                <label class="field">Cari Ara
                    <input type="search" wire:model.live.debounce.300ms="contactSearch" placeholder="Kod veya unvan ile ara" autocomplete="off">
                    <small class="v65-doc-search-hint">Kod veya unvan yazarak arayın. En fazla 35 eşleşme listelenir.</small>
                    <small class="v65-doc-search-loading" wire:loading wire:target="contactSearch">Aranıyor…</small>
                </label>
                <x-v65.select label="Cari" wire:model.live="contactId" placeholder="Cari seçin" class="v65-doc-contact">
                    @forelse($this->contactOptions() as $contact)
                        <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                    @empty
                        <option value="" disabled>Aramaya uygun cari bulunamadı</option>
                    @endforelse
                </x-v65.select>
                <x-v65.input-error field="contactId" />
                <div class="v65-doc-contact-actions">
                    <x-v65.button variant="secondary" size="sm" wire:click="applyContactPrices" wire:loading.attr="disabled" wire:target="applyContactPrices" wire:confirm="Manuel girdiğiniz satır fiyatları da seçili carinin fiyat listesine göre değiştirilecek. Devam edilsin mi?">Cari Fiyatlarını Uygula</x-v65.button>
                    <small>Cari değişince otomatik fiyatlar güncellenir; manuel fiyatlar yalnızca bu butonla yeniden hesaplanır.</small>
                </div>
            </div>

            <label class="field">Belge Tarihi <input type="date" wire:model="documentDate"><x-v65.input-error field="documentDate" /></label>
            @unless($this instanceof \App\Livewire\Sales\DispatchEditor)
                <label class="field">Vade <input type="date" wire:model="dueDate"><x-v65.input-error field="dueDate" /></label>
            @endunless
            @if($this instanceof \App\Livewire\Sales\QuoteEditor)
                <label class="field">Teklif Geçerlilik Tarihi <input type="date" wire:model="validUntil"><x-v65.input-error field="validUntil" /></label>
            @endif
            <label class="field">Belge İskonto % <input wire:model.blur="discountRate" data-tr-decimal inputmode="decimal"><x-v65.input-error field="discountRate" /></label>
            <label class="field v65-doc-notes">Not <textarea wire:model="notes" rows="3"></textarea></label>

            <div class="v65-doc-vat-toolbar" role="group" aria-label="Satırlara toplu KDV uygula">
                <label class="field">Toplu KDV % <input wire:model="bulkVatRate" inputmode="decimal" data-tr-decimal aria-invalid="{{ $errors->has('bulkVatRate') ? 'true' : 'false' }}"><x-v65.input-error field="bulkVatRate" /></label>
                <x-v65.button variant="secondary" size="sm" wire:click="applyVatToAll">Tüm Satırlara Uygula</x-v65.button>
                <x-v65.button variant="secondary" size="sm" wire:click="clearVat">KDV'yi Temizle</x-v65.button>
            </div>

            <section class="v65-doc-lines" aria-label="Belge kalemleri">
                <div class="v65-doc-lines-head">
                    <strong>Belge Kalemleri</strong>
                    <small>Miktar, fiyat ve vergi değişince taslak toplamları güncellenir.</small>
                </div>
                <small class="v65-doc-mobile-scroll-hint">Tablodaki diğer satır alanlarını görmek için yatay kaydırın.</small>
                <div class="table-scroll document-lines v65-doc-table-scroll"><table class="data-table v65-doc-table">
                <thead>
                    <tr>
                        <th scope="col">#</th><th scope="col">Tür</th><th scope="col">Ürün / Açıklama</th><th scope="col">Birim</th><th scope="col" class="align-end">Miktar</th><th scope="col">Lokasyon</th>
                        <th scope="col" class="align-end">Fiyat</th><th scope="col" class="align-end">İsk. %</th><th scope="col" class="align-end">KDV %</th><th scope="col" class="align-end">İskonto Tutarı</th><th scope="col" class="align-end">Satır KDV*</th><th scope="col" class="align-end">Satır Net / Matrah</th><th scope="col">İşlem</th>
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
                                    <input wire:model="lines.{{ $index }}.description" placeholder="Hizmet açıklaması" aria-invalid="{{ $errors->has('lines.'.$index.'.description') ? 'true' : 'false' }}" aria-label="Satır {{ $index + 1 }} hizmet açıklaması"><x-v65.input-error :field="'lines.'.$index.'.description'" />
                                @else
                                    <input type="search" class="v65-doc-product-search"
                                        wire:model.live.debounce.300ms="productSearches.{{ $line['row_key'] ?? $index }}"
                                        placeholder="Ürün kodu veya adıyla ara"
                                        aria-label="Satır {{ $index + 1 }} ürün ara" autocomplete="off">
                                    <small class="v65-doc-search-loading" wire:loading wire:target="productSearches.{{ $line['row_key'] ?? $index }}">Aranıyor…</small>
                                    <select wire:model.live="lines.{{ $index }}.product_id" aria-invalid="{{ $errors->has('lines.'.$index.'.product_id') ? 'true' : 'false' }}" aria-label="Satır {{ $index + 1 }} ürün">
                                        <option value="">Ürün seçin</option>
                                        @forelse($this->productOptionsForLine((string) ($line['row_key'] ?? $index), (int) ($line['product_id'] ?? 0)) as $product)
                                            <option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }}</option>
                                        @empty
                                            <option value="" disabled>Ürün bulunamadı</option>
                                        @endforelse
                                    </select>
                                    <x-v65.input-error :field="'lines.'.$index.'.product_id'" />
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
                            <td class="align-end"><input wire:model.blur="lines.{{ $index }}.quantity" aria-invalid="{{ $errors->has('lines.'.$index.'.quantity') ? 'true' : 'false' }}" inputmode="decimal" data-tr-decimal aria-label="Satır {{ $index + 1 }} miktar"><x-v65.input-error :field="'lines.'.$index.'.quantity'" /></td>
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
                            <td class="align-end"><input wire:model.blur="lines.{{ $index }}.unit_price" aria-invalid="{{ $errors->has('lines.'.$index.'.unit_price') ? 'true' : 'false' }}" inputmode="decimal" data-tr-decimal aria-label="Satır {{ $index + 1 }} birim fiyat"><x-v65.input-error :field="'lines.'.$index.'.unit_price'" /></td>
                            <td class="align-end"><input wire:model.blur="lines.{{ $index }}.line_discount_rate" aria-invalid="{{ $errors->has('lines.'.$index.'.line_discount_rate') ? 'true' : 'false' }}" inputmode="decimal" data-tr-decimal aria-label="Satır {{ $index + 1 }} iskonto"><x-v65.input-error :field="'lines.'.$index.'.line_discount_rate'" /></td>
                            <td class="align-end"><input wire:model.blur="lines.{{ $index }}.vat_rate" aria-invalid="{{ $errors->has('lines.'.$index.'.vat_rate') ? 'true' : 'false' }}" inputmode="decimal" data-tr-decimal aria-label="Satır {{ $index + 1 }} KDV"><x-v65.input-error :field="'lines.'.$index.'.vat_rate'" /></td>
                            <x-v65.document-line-amounts :calculation="$draftPreview?->lines[$index] ?? null" :line="$line" :show-stored="!$documentDirty" />
                            <td class="align-end v65-doc-line-net">
                                <span>@if($draftPreview && isset($draftPreview->lines[$index])){{ \App\Support\Formatting\TrFormatter::money((string) $draftPreview->lines[$index]->lineTotal) }}@elseif(!$documentDirty && isset($line['line_total'])){{ \App\Support\Formatting\TrFormatter::money((string) $line['line_total']) }}@else—@endif</span>
                            </td>
                            <td><x-v65.button variant="danger" size="xs" wire:click="removeLine({{ $index }})" aria-label="Satır {{ $index + 1 }} sil">Sil</x-v65.button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
                <small class="v65-doc-line-tax-note">* Satır KDV tutarı, belge genel iskontosu uygulanmadan önceki bilgi amaçlı hesaplamadır. Geçerli toplam KDV, Belge Toplamları panelindeki sunucu hesaplamasıdır.</small>
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
                <x-v65.document-confirm id="v65-sales-approve" title="Teklifi Onayla" label="Onayla" action="approve" :document="$document" message="Teklif son kaydedilen haliyle onaylanacaktır. Devam edilsin mi?" :dirty="$documentDirty" />
            @endif
            @if(method_exists($this, 'revision') && $document->number)
                <x-v65.button variant="secondary" wire:click="revision" wire:loading.attr="disabled" wire:target="revision">Revizyon Oluştur</x-v65.button>
            @endif
            @if(method_exists($this, 'toOrder') && $document->status === 'approved')
                <x-v65.button wire:click="toOrder" wire:loading.attr="disabled" wire:target="toOrder">Sipariş Oluştur</x-v65.button>
            @endif
            @if(method_exists($this, 'confirm') && $document->status === 'draft')
                <label><input type="checkbox" wire:model="riskAccepted"> Risk uyarısını kabul ediyorum</label>
                <x-v65.document-confirm id="v65-sales-confirm" title="Sipariş Onayı" label="Siparişi Onayla" action="confirm" :document="$document" message="Stok ve cari risk kontrolleri sunucuda tekrar uygulanacaktır. Son kaydedilen siparişi onaylamak istiyor musunuz?" :dirty="$documentDirty" />
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
                            <x-v65.button variant="secondary" wire:click="reserve" wire:loading.attr="disabled" wire:target="reserve">Rezerv Et</x-v65.button>
                        @else
                            <small class="v65-reservation-permission">Stok rezervasyonu oluşturma yetkiniz yok.</small>
                        @endcan
                        <x-v65.button wire:click="createDispatch" wire:loading.attr="disabled" wire:target="createDispatch">İrsaliye Oluştur</x-v65.button>
                        <x-v65.button wire:click="createInvoice" wire:loading.attr="disabled" wire:target="createInvoice">Fatura Oluştur</x-v65.button>
                        <x-v65.button variant="secondary" wire:click="issueProforma" wire:loading.attr="disabled" wire:target="issueProforma">Proforma Oluştur</x-v65.button>
                        <x-v65.document-confirm id="v65-order-cancel-remaining" title="Kalan Sipariş Miktarını İptal Et" label="Kalanı İptal Et" action="cancelRemaining" variant="danger" :document="$document" :dirty="$documentDirty" message="Siparişin teslim edilmemiş kalan miktarı iptal edilecektir. Bu işlem için kaydedilmiş sipariş esas alınır." />
                    </div>
                </section>
            @elseif(method_exists($this, 'issueProforma') && in_array($document->status, ['approved','confirmed']))
                <x-v65.button variant="secondary" wire:click="issueProforma" wire:loading.attr="disabled" wire:target="issueProforma">Proforma Oluştur</x-v65.button>
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
                <x-v65.document-confirm id="v65-sales-post" title="Belgeyi Kesinleştir" label="Kesinleştir" action="post" :document="$document" message="Son kaydedilen satırlar, KDV ve genel toplam kesinleştirmeye esas alınacaktır. Risk kontrolleri sunucuda tekrar yapılır." :dirty="$documentDirty" />
            @endif
            @if(method_exists($this, 'reverse') && $document->status === 'posted')
                <label class="field"><span class="field-label">Ters Kayıt Tarihi</span><input type="date" wire:model="reversalDate"></label>
                <label class="field"><span class="field-label">Ters Kayıt Gerekçesi</span><input wire:model="reversalReason"></label>
                <x-v65.document-confirm id="v65-sales-reverse" title="Ters Kayıt Onayı" label="Ters Kayıt" action="reverse" variant="danger" :document="$document" message="Kesinleşmiş belgeye ters kayıt oluşturulur. Tarih ve gerekçeyi kontrol ederek onaylayın." :dirty="$documentDirty" />
            @endif
        </section>
    @endif
</div>
