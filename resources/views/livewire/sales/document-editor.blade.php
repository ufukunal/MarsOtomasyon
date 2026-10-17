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

            <label class="field"><span>Para Birimi</span><input readonly value="TRY" title="Satış muhasebesi mevcut aşamada TRY bazlıdır"></label>
            <label class="field"><span>Varsayılan Depo</span>
                <select wire:model.live="uiMeta.default_location_id"><option value="">Satır bazında seç</option>
                    @foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach
                </select>
            </label>
            @if($this instanceof \App\Livewire\Sales\QuoteEditor)
                <label class="field">Teslim Süresi <input wire:model.blur="uiMeta.delivery_terms" maxlength="140" placeholder="Örn. 7 iş günü"></label>
                <label class="field">Ödeme Şartı
                    <select wire:model.live="uiMeta.payment_terms"><option value="">Seçin</option>
                        <option value="Peşin">Peşin</option><option value="15 Gün">15 Gün</option>
                        <option value="30 Gün">30 Gün</option><option value="60 Gün">60 Gün</option>
                    </select>
                </label>
            @elseif($this instanceof \App\Livewire\Sales\SalesOrderEditor)
                <label class="field">Planlanan Teslim <input type="date" wire:model="uiMeta.planned_delivery"></label>
                <label class="field">Sevkiyat Deposu
                    <select wire:model.live="uiMeta.shipment_location_id"><option value="">Satırdan seç</option>
                        @foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach
                    </select>
                </label>
                <label class="field">Müşteri Sipariş No <input wire:model.blur="uiMeta.customer_order_no" maxlength="140"></label>
            @elseif($this instanceof \App\Livewire\Sales\DispatchEditor)
                <label class="field">Çıkış Deposu
                    <select wire:model.live="uiMeta.shipment_location_id"><option value="">Satırdan seç</option>
                        @foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach
                    </select>
                </label>
                <label class="field">Araç / Plaka <input wire:model.blur="uiMeta.vehicle_plate" maxlength="140"></label>
            @elseif($this instanceof \App\Livewire\Sales\SalesInvoiceEditor)
                <label class="field">Ödeme Şekli
                    <select wire:model="uiMeta.payment_method"><option value="">Seçin</option>
                        <option value="Peşin">Peşin</option><option value="Vadeli">Vadeli</option><option value="Banka">Banka</option>
                    </select>
                </label>
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
                    <small>Ürün seçtiğinizde sonraki boş satır otomatik hazırlanır. Boş satırlar kaydedilmez.</small>
                </div>
                <small class="v65-doc-mobile-scroll-hint">Tablodaki diğer satır alanlarını görmek için yatay kaydırın.</small>

                <div class="table-scroll document-lines v65-doc-table-scroll"><table class="data-table v65-doc-table v65-prototype-lines">
                    <thead><tr>
                        <th>#</th><th>Ürün Kodu / Barkod</th><th>Ürün Adı ve Detay</th><th class="align-end">Miktar</th>
                        <th>Birim</th><th class="align-end">Fiyat</th><th class="align-end">İsk. %</th><th class="align-end">KDV %</th>
                        <th class="align-end">Net Tutar</th><th class="align-end">KDV Dahil*</th><th>İşlem</th>
                    </tr></thead>
                    <tbody>
                    @foreach($lines as $index => $line)
                        @php
                            $service = ($line['line_kind'] ?? 'stock') === 'service';
                            $previewLine = $draftPreview?->lines[$index] ?? null;
                            $lineNet = $previewLine?->lineTotal ?? (!$documentDirty ? ($line['line_total'] ?? null) : null);
                            $lineVat = $lineNet !== null && is_numeric((string) ($line['vat_rate'] ?? 0))
                                ? \App\Support\Money\Money::of((string) $lineNet)->percent((string) ($line['vat_rate'] ?? 0))->amount : null;
                            $lineWithVat = $lineVat !== null ? \App\Support\Money\Money::of((string) $lineNet)->plus(\App\Support\Money\Money::of($lineVat))->amount : null;
                        @endphp
                        <tr wire:key="sales-line-{{ $line['row_key'] ?? $index }}">
                            <td>{{ $index + 1 }}</td>
                            <td class="v65-doc-product-cell">
                                @if($service)<span class="v65-doc-muted">HİZMET</span>
                                @else
                                    <input type="search" class="v65-doc-product-search" data-v65-fast-product-search
                                        wire:model.live.debounce.300ms="productSearches.{{ $line['row_key'] ?? $index }}"
                                        placeholder="Kod / barkod / ad ara" autocomplete="off" aria-label="Satır {{ $index + 1 }} ürün ara">
                                    <select data-v65-fast-product-select wire:model.live="lines.{{ $index }}.product_id" aria-label="Satır {{ $index + 1 }} ürün seç">
                                        <option value="">Ürün seçin</option>
                                        @forelse($this->productOptionsForLine((string) ($line['row_key'] ?? $index), (int) ($line['product_id'] ?? 0)) as $product)
                                            <option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }} {{ $product->barcode ? '(' . $product->barcode . ')' : '' }}</option>
                                        @empty
                                            <option disabled value="">Ürün bulunamadı</option>
                                        @endforelse
                                    </select>
                                    <x-v65.input-error :field="'lines.'.$index.'.product_id'"/>
                                @endif
                            </td>
                            <td class="v65-doc-product-detail">
                                @if($service)
                                    <input wire:model.blur="lines.{{ $index }}.description" placeholder="Hizmet açıklaması">
                                    <x-v65.input-error :field="'lines.'.$index.'.description'"/>
                                @else <strong>{{ $line['description'] ?? 'Ürün seçin' }}</strong>
                                @endif
                                <details class="v65-doc-line-details"><summary>Satır detayı</summary>
                                    <label>Tür <select wire:model.live="lines.{{ $index }}.line_kind" aria-label="Satır türü">
                                        <option value="stock">Stok</option><option value="service">Hizmet</option>
                                    </select></label>
                                    @unless($service)
                                    <label>Depo <select wire:model="lines.{{ $index }}.location_id">
                                        <option value="">Depo seçin</option>
                                        @foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach
                                    </select></label>
                                    <label>Satır Notu <input wire:model.blur="lines.{{ $index }}.description" placeholder="İsteğe bağlı not"></label>
                                    @endunless
                                    <small>İskonto tutarı: {{ $previewLine ? \App\Support\Formatting\TrFormatter::money((string) $previewLine->discountAmount) : '—' }}</small>
                                </details>
                            </td>
                            <td class="align-end">
                                <input data-v65-fast-quantity wire:model.blur="lines.{{ $index }}.quantity" inputmode="decimal" data-tr-decimal aria-label="Satır {{ $index + 1 }} miktar">
                                <x-v65.input-error :field="'lines.'.$index.'.quantity'"/>
                            </td>
                            <td>
                                @if($service)<span>—</span>@else
                                    <select wire:model="lines.{{ $index }}.unit_id" aria-label="Satır {{ $index + 1 }} birim">
                                        <option value="">Birim</option>@foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->code }}</option>@endforeach
                                    </select>
                                @endif
                            </td>
                            <td class="align-end"><input wire:model.blur="lines.{{ $index }}.unit_price" inputmode="decimal" data-tr-decimal aria-invalid="{{ $errors->has('lines.'.$index.'.unit_price') ? 'true' : 'false' }}" aria-label="Satır fiyatı"></td>
                            <td class="align-end"><input wire:model.blur="lines.{{ $index }}.line_discount_rate" inputmode="decimal" data-tr-decimal aria-invalid="{{ $errors->has('lines.'.$index.'.line_discount_rate') ? 'true' : 'false' }}" aria-label="Satır iskonto oranı"></td>
                            <td class="align-end"><input wire:model.blur="lines.{{ $index }}.vat_rate" inputmode="decimal" data-tr-decimal aria-invalid="{{ $errors->has('lines.'.$index.'.vat_rate') ? 'true' : 'false' }}" aria-label="Satır KDV oranı"></td>
                            <td class="align-end v65-doc-line-net">{{ $lineNet !== null ? \App\Support\Formatting\TrFormatter::money((string) $lineNet) : '—' }}</td>
                            <td class="align-end v65-doc-line-incl">{{ $lineWithVat !== null ? \App\Support\Formatting\TrFormatter::money($lineWithVat) : '—' }}</td>
                            <td><x-v65.button size="xs" variant="danger" wire:click="removeLine({{ $index }})" aria-label="Satır sil">Sil</x-v65.button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                <small class="v65-doc-line-tax-note">* KDV Dahil satır tutarı belge genel/ek iskontoları öncesi bilgi amaçlıdır; geçerli KDV ve genel toplam sağdaki sunucu hesaplamalı özettir.</small>
            </section>

            <x-v65.form-actions align="between" class="v65-doc-form-actions">
                <div class="v65-line-add-actions"><x-v65.button variant="secondary" wire:click="addServiceLine">+ Hizmet Satırı</x-v65.button><x-v65.button variant="secondary" wire:click="addLine">+ Yeni Ürün Satırı</x-v65.button></div>
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

    <x-v65.document-summary :editable="!$document || $document->status === 'draft'" :additional-discount="$additionalDiscountAmount" class="v65-doc-screen-summary" :document="$document" :preview="$draftPreview" />

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
