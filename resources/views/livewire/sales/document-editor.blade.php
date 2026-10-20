<div class="document-workspace stack v65-reference-workspace">
    @php
        $documentIndexRoute = match (true) {
            $this instanceof \App\Livewire\Sales\QuoteEditor => 'sales.quotes.index',
            $this instanceof \App\Livewire\Sales\SalesOrderEditor => 'sales.orders.index',
            $this instanceof \App\Livewire\Sales\DispatchEditor => 'sales.dispatches.index',
            default => 'sales.invoices.index',
        };
        $screenType = match (true) {
            $this instanceof \App\Livewire\Sales\QuoteEditor => 'quote',
            $this instanceof \App\Livewire\Sales\SalesOrderEditor => 'sales_order',
            $this instanceof \App\Livewire\Sales\DispatchEditor => 'dispatch',
            default => 'sales_invoice',
        };
        $screen = config('document_prototype.'.$screenType);
        $formId = 'v65-reference-form-'.$screenType;
        $lineCount = collect($lines)->filter(fn ($line) => ! empty($line['product_id'])
            || (in_array(($line['line_kind'] ?? 'stock'), ['service', 'custom'], true)
                && trim((string) ($line['description'] ?? '')) !== ''))->count();
    @endphp
    <x-v65.document-prototype-heading :document="$document" :screen-type="$screenType"
        :index-route="$documentIndexRoute" :dirty="$documentDirty" :form-id="$formId"
            :can-copy="$this->canCreateEditableCopy()" :edit-permission="$this->permissionPrefix()" />

    @if($this instanceof \App\Livewire\Sales\QuoteEditor && $document && $document->number)
        @php $quoteRevisions = $this->quoteRevisions(); @endphp
        @if($quoteRevisions->count() > 1)
            <section class="v65-quote-revisions" aria-label="Teklif revizyon geçmişi">
                <details open>
                    <summary>Revizyon Geçmişi <span>({{ $quoteRevisions->count() }} sürüm)</span></summary>
                    <div class="table-scroll v65-grid-scroll">
                        <table class="data-table">
                            <thead>
                                <tr><th>Revizyon</th><th>Tarih</th><th>Durum</th><th class="align-end">Tutar</th><th>İşlem</th></tr>
                            </thead>
                            <tbody>
                                @foreach($quoteRevisions as $revision)
                                    <tr wire:key="quote-revision-{{ $revision->id }}"
                                        @class(['v65-quote-revision-current' => $revision->id === $document->id])>
                                        <td><strong>Rev.{{ $revision->revision_no }}</strong></td>
                                        <td>{{ $revision->document_date?->format('d.m.Y') ?? '—' }}</td>
                                        <td>{{ \App\Support\Formatting\BusinessLabelFormatter::status($revision->status) }}</td>
                                        <td class="align-end tabular">{{ \App\Support\Formatting\TrFormatter::money((string) $revision->grand_total) }} {{ $revision->currency }}</td>
                                        <td>
                                            @if($revision->id === $document->id)
                                                <span class="v65-quote-revision-current-label">Bu sürüm</span>
                                            @else
                                                <a class="v65-quote-revision-link" href="{{ route('sales.quotes.edit', ['id' => $revision->id]) }}">Görüntüle</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            </section>
        @endif
    @endif
    @if($document && $document->status !== 'draft' && !($this instanceof \App\Livewire\Purchases\GoodsReceiptEditor))
        <div class="v65-reference-edit-notice" role="status">
            <strong>Bu sürüm kayıtlıdır.</strong>
            @if($this instanceof \App\Livewire\Sales\SalesInvoiceEditor)
                Faturayı değiştirmek için <strong>Düzenle</strong> seçin.
                Önceki fatura, yeni kayıt kaydedilene kadar geçerlidir.
                <strong>Sil</strong> işlemi muhasebe geçmişini koruyarak faturayı listeden kaldırır.
            @elseif($document->status === 'posted' && ! $this->canCreateEditableCopy())
                Kesinleşmiş belge üzerinde tutar veya stok değişikliği yapılamaz.
                Bu belge için mevcut kaydı değiştiren güvenli düzenleme işlemi kullanılır; mali geçmiş korunur.
            @elseif($this instanceof \App\Livewire\Sales\QuoteEditor && $document->number)
                Eski teklif değişmez; düzenleme yeni bir teklif revizyonunda yapılır.
            @elseif($this->canCreateEditableCopy())
                Yeni bir düzenleme taslağı açabilirsiniz. Önceki belge geçerli kalır;
                bu işlem eski siparişi veya irsaliyeyi otomatik iptal etmez.
            @else
                Bu belge için düzenleme kopyası oluşturma yetkisi veya uygun işlem durumu bulunmuyor.
                Kayıt geçmişi ve ilgili ters kayıt belgeleri değiştirilemez.
            @endif
        </div>
    @endif
    @if($errors->any())
        <x-v65.notice tone="danger" title="Belge kontrolü" data-v65-document-errors tabindex="-1">
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
        <form wire:submit="save" id="{{ $formId }}" class="document-editor-form v65-doc-editor v65-reference-form {{ $screenType === 'sales_invoice' ? 'v65-invoice-tabbed-form' : '' }}">
            @if($screenType === 'sales_invoice')
                <section class="v65-invoice-meta" aria-label="Fatura üst bilgileri" x-data="{ invoiceTab: 'bilgiler' }">
                    <nav class="v65-invoice-tabs" role="tablist" aria-label="Satış faturası üst bilgi sekmeleri">
                        <button type="button" id="v65-invoice-tab-bilgiler" role="tab"
                            x-on:click="invoiceTab = 'bilgiler'"
                            x-bind:aria-selected="invoiceTab === 'bilgiler' ? 'true' : 'false'"
                            x-bind:class="{ 'is-active': invoiceTab === 'bilgiler' }"
                            aria-controls="v65-invoice-panel-bilgiler">Bilgiler</button>
                        <button type="button" id="v65-invoice-tab-notlar" role="tab"
                            x-on:click="invoiceTab = 'notlar'"
                            x-bind:aria-selected="invoiceTab === 'notlar' ? 'true' : 'false'"
                            x-bind:class="{ 'is-active': invoiceTab === 'notlar' }"
                            aria-controls="v65-invoice-panel-notlar">Notlar</button>
                        <button type="button" id="v65-invoice-tab-diger" role="tab"
                            x-on:click="invoiceTab = 'diger'"
                            x-bind:aria-selected="invoiceTab === 'diger' ? 'true' : 'false'"
                            x-bind:class="{ 'is-active': invoiceTab === 'diger' }"
                            aria-controls="v65-invoice-panel-diger">Diğer</button>
                    </nav>

                    <div id="v65-invoice-panel-bilgiler" class="v65-invoice-tab-panel"
                        role="tabpanel" aria-labelledby="v65-invoice-tab-bilgiler"
                        x-show="invoiceTab === 'bilgiler'">
                        <section class="v65-invoice-info-group" aria-label="Belge bilgileri">
                            <header class="v65-invoice-group-title"><strong>Belge Bilgileri</strong>
                                <small>Belge No: {{ $document?->number ?? 'Kaydedildiğinde oluşturulur' }}</small>
                            </header>
                            <div class="v65-invoice-info-grid">
                                <div class="v65-doc-contact-picker v65-invoice-contact-picker">
                                    <div class="v65-invoice-contact-controls">
                                        <label class="field">Müşteri / Cari Ara
                                            <input type="search" wire:model.live.debounce.300ms="contactSearch"
                                                placeholder="Kod veya unvan ile ara" autocomplete="off">
                                            <small class="v65-doc-search-loading" wire:loading wire:target="contactSearch">Aranıyor…</small>
                                        </label>
                                        <x-v65.select label="Cari" wire:model.live="contactId" placeholder="Cari seçin" class="v65-doc-contact">
                                            @forelse($this->contactOptions() as $contact)
                                                <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                                            @empty
                                                <option value="" disabled>Aramaya uygun cari bulunamadı</option>
                                            @endforelse
                                        </x-v65.select>
                                    </div>
                                    <x-v65.input-error field="contactId" />
                                    <details class="v65-reference-contact-prices">
                                        <summary>Cari fiyat listesi</summary>
                                        <div class="v65-doc-contact-actions">
                                            <x-v65.button variant="secondary" size="sm" wire:click="applyContactPrices"
                                                wire:loading.attr="disabled" wire:target="applyContactPrices"
                                                wire:confirm="Manuel satır fiyatları da seçili carinin fiyat listesine göre değiştirilsin mi?">Cari Fiyatlarını Uygula</x-v65.button>
                                            <small>Manuel fiyatlar yalnız bu işlemle yeniden hesaplanır.</small>
                                        </div>
                                    </details>
                                </div>
                                <label class="field">Fatura Tarihi
                                    <input type="date" wire:model="documentDate">
                                    <x-v65.input-error field="documentDate" />
                                </label>
                                <label class="field">Vade Tarihi
                                    <input type="date" wire:model="dueDate">
                                    <x-v65.input-error field="dueDate" />
                                </label>
                                <label class="field">Ödeme Şekli
                                    <select wire:model="uiMeta.payment_method">
                                        <option value="">Seçin</option>
                                        <option value="Peşin">Peşin</option>
                                        <option value="Vadeli">Vadeli</option>
                                        <option value="Banka">Banka</option>
                                    </select>
                                </label>
                                <label class="field">Para Birimi
                                    <input readonly value="TRY" title="Satış muhasebesi TRY bazlıdır">
                                </label>
                                <label class="field">Varsayılan Depo
                                    <select wire:model.live="uiMeta.default_location_id">
                                        <option value="">Satır bazında seç</option>
                                        @foreach($locations as $location)
                                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="field v65-invoice-discount-field">Belge İskonto %
                                    <input wire:model.blur="discountRate" data-tr-decimal data-v65-discount-display
                                        inputmode="decimal" title="Cari kartından gelir; değiştirilebilir."
                                        aria-label="Satış faturası belge iskonto yüzdesi">
                                    <x-v65.input-error field="discountRate" />
                                </label>
                            </div>
                        </section>

                        @php
                            $invoiceContact = $this->invoiceContactDetails();
                        @endphp
                        <div class="v65-invoice-contact-panels">
                            <section class="v65-invoice-info-group" aria-label="Fatura bilgileri">
                                <header class="v65-invoice-group-title">
                                    <strong>Fatura Bilgileri</strong>
                                    <small>Cari kartından alınır</small>
                                </header>
                                <dl class="v65-invoice-readonly-grid">
                                    <div class="v65-invoice-readonly-item v65-invoice-readonly-wide">
                                        <dt>Ünvan</dt><dd>{{ $invoiceContact['title'] ?? '—' }}</dd>
                                    </div>
                                    <div class="v65-invoice-readonly-item">
                                        <dt>Vergi Dairesi</dt><dd>{{ $invoiceContact['tax_office'] ?? '—' }}</dd>
                                    </div>
                                    <div class="v65-invoice-readonly-item">
                                        <dt>Vergi Numarası</dt><dd>{{ $invoiceContact['tax_number'] ?? '—' }}</dd>
                                    </div>
                                    <div class="v65-invoice-readonly-item v65-invoice-readonly-wide">
                                        <dt>Fatura Adresi</dt><dd>{{ ($invoiceContact['billing_address'] ?? '') ?: '—' }}</dd>
                                    </div>
                                    <div class="v65-invoice-readonly-item v65-invoice-readonly-wide">
                                        <dt>İl / İlçe</dt>
                                        <dd>{{ collect([$invoiceContact['billing_city'] ?? null, $invoiceContact['billing_district'] ?? null])->filter()->implode(' / ') ?: '—' }}</dd>
                                    </div>
                                </dl>
                                @if(! empty($invoiceContact['categories']))
                                    <div class="v65-invoice-category-list" aria-label="Cari kategorileri">
                                        @foreach($invoiceContact['categories'] as $categoryName)
                                            <span>{{ $categoryName }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </section>
                            <section class="v65-invoice-info-group" aria-label="Sevk bilgileri">
                                <header class="v65-invoice-group-title">
                                    <strong>Sevk Bilgileri</strong>
                                    <small>Teslimat noktasını seçin</small>
                                </header>
                                <div class="v65-invoice-delivery-controls">
                                    <label class="field">Teslimat Şekli
                                        <select wire:model.live="uiMeta.delivery_mode">
                                            <option value="customer">Fatura müşterisine sevk</option>
                                            <option value="saved">Müşterinin müşterisine / Kayıtlı sevk noktası</option>
                                            <option value="manual">Tek seferlik farklı adrese sevk</option>
                                        </select>
                                    </label>
                                    @if(($uiMeta['delivery_mode'] ?? 'customer') === 'saved')
                                        <label class="field">Sevk Edilecek Firma / Adres
                                            <select wire:model.live="uiMeta.delivery_address_id">
                                                <option value="">Sevk noktası seçin</option>
                                                @foreach($this->invoiceDeliveryAddresses() as $deliveryAddress)
                                                    <option value="{{ $deliveryAddress->id }}">{{ $deliveryAddress->title ?: $deliveryAddress->address }} · {{ $deliveryAddress->city }}</option>
                                                @endforeach
                                            </select>
                                            <x-v65.input-error field="uiMeta.delivery_address_id" />
                                        </label>
                                    @endif
                                    @if(in_array(($uiMeta['delivery_mode'] ?? 'customer'), ['saved', 'manual'], true))
                                        <label class="field">Teslim Alacak Firma / Kişi
                                            <input wire:model.blur="uiMeta.delivery_company" maxlength="180">
                                            <x-v65.input-error field="uiMeta.delivery_company" />
                                        </label>
                                        <label class="field">Yetkili Kişi
                                            <input wire:model.blur="uiMeta.delivery_person" maxlength="120">
                                        </label>
                                        <label class="field">Telefon
                                            <input wire:model.blur="uiMeta.delivery_phone" maxlength="40">
                                            <x-v65.input-error field="uiMeta.delivery_phone" />
                                        </label>
                                        <label class="field">İl
                                            <input wire:model.blur="uiMeta.delivery_city" maxlength="100">
                                            <x-v65.input-error field="uiMeta.delivery_city" />
                                        </label>
                                        <label class="field">İlçe
                                            <input wire:model.blur="uiMeta.delivery_district" maxlength="100">
                                            <x-v65.input-error field="uiMeta.delivery_district" />
                                        </label>
                                        <label class="field v65-invoice-delivery-address">Teslimat Adresi
                                            <textarea wire:model.blur="uiMeta.delivery_address" rows="2" maxlength="4000"></textarea>
                                            <x-v65.input-error field="uiMeta.delivery_address" />
                                        </label>
                                        <small class="v65-invoice-delivery-note">Bu bilgiler faturaya kaydedilir ve ambar fişi ile koli etiketine aktarılır.</small>
                                    @endif
                                    @if($contactId && auth()->user()?->can('contacts.update'))
                                        <a class="v65-invoice-address-link" target="_blank" rel="noopener"
                                            href="{{ route('contacts.edit', ['contact' => $contactId]) }}">Cari kartında sevk adreslerini yönet</a>
                                    @endif
                                </div>

                                @if(in_array(($uiMeta['delivery_mode'] ?? 'customer'), ['saved', 'manual'], true)
                                    && ! empty($uiMeta['delivery_company']))
                                    <dl class="v65-invoice-readonly-grid">
                                        <div class="v65-invoice-readonly-item v65-invoice-readonly-wide">
                                            <dt>Teslim Edilecek Firma</dt><dd>{{ $uiMeta['delivery_company'] }}</dd>
                                        </div>
                                        <div class="v65-invoice-readonly-item v65-invoice-readonly-wide">
                                            <dt>Sevk Adresi</dt><dd>{{ $uiMeta['delivery_address'] ?? '—' }}</dd>
                                        </div>
                                        <div class="v65-invoice-readonly-item v65-invoice-readonly-wide">
                                            <dt>İl / İlçe</dt><dd>{{ collect([$uiMeta['delivery_city'] ?? null, $uiMeta['delivery_district'] ?? null])->filter()->implode(' / ') ?: '—' }}</dd>
                                        </div>
                                    </dl>
                                @elseif(! empty($invoiceContact['shipping_defined']))
                                    <dl class="v65-invoice-readonly-grid">
                                        @if(! empty($invoiceContact['shipping_title']))
                                            <div class="v65-invoice-readonly-item v65-invoice-readonly-wide">
                                                <dt>Adres Başlığı</dt><dd>{{ $invoiceContact['shipping_title'] }}</dd>
                                            </div>
                                        @endif
                                        <div class="v65-invoice-readonly-item v65-invoice-readonly-wide">
                                            <dt>Sevk Adresi</dt><dd>{{ ($invoiceContact['shipping_address'] ?? '') ?: '—' }}</dd>
                                        </div>
                                        <div class="v65-invoice-readonly-item v65-invoice-readonly-wide">
                                            <dt>İl / İlçe</dt>
                                            <dd>{{ collect([$invoiceContact['shipping_city'] ?? null, $invoiceContact['shipping_district'] ?? null])->filter()->implode(' / ') ?: '—' }}</dd>
                                        </div>
                                    </dl>
                                @else
                                    <p class="v65-invoice-empty-info">
                                        {{ $invoiceContact ? 'Cari kartında sevk adresi tanımlı değil.' : 'Cari bilgisi seçildiğinde gösterilir.' }}
                                    </p>
                                @endif
                            </section>
                            <section class="v65-invoice-info-group" aria-label="İletişim bilgileri">
                                <header class="v65-invoice-group-title"><strong>İletişim Bilgileri</strong></header>
                                <dl class="v65-invoice-readonly-grid">
                                    <div class="v65-invoice-readonly-item v65-invoice-readonly-wide">
                                        <dt>Telefon</dt><dd>{{ $invoiceContact['phone'] ?? '—' }}</dd>
                                    </div>
                                    <div class="v65-invoice-readonly-item v65-invoice-readonly-wide">
                                        <dt>E-posta</dt><dd>{{ $invoiceContact['email'] ?? '—' }}</dd>
                                    </div>
                                </dl>
                            </section>
                        </div>
                    </div>

                                        <div id="v65-invoice-panel-notlar" class="v65-invoice-tab-panel" role="tabpanel"
                        aria-labelledby="v65-invoice-tab-notlar" x-show="invoiceTab === 'notlar'" x-cloak>
                        <section class="v65-invoice-info-group">
                            <header class="v65-invoice-group-title"><strong>Not Bilgileri</strong></header>
                            <label class="field v65-invoice-note-label">Fatura Notu
                                <textarea wire:model.blur="notes" rows="7"
                                    placeholder="Faturaya ait notları ve açıklamaları yazın..."></textarea>
                            </label>
                        </section>
                    </div>

                    <div id="v65-invoice-panel-diger" class="v65-invoice-tab-panel" role="tabpanel"
                        aria-labelledby="v65-invoice-tab-diger" x-show="invoiceTab === 'diger'" x-cloak>
                        <section class="v65-invoice-info-group">
                            <header class="v65-invoice-group-title"><strong>Diğer Belge Seçenekleri</strong></header>
                            <div class="v65-invoice-info-grid">
                                <label class="field">Müşteri Referans No
                                    <input wire:model.blur="uiMeta.customer_reference_no" maxlength="140">
                                </label>
                            </div>
                            <div class="v65-invoice-source-detail">
                                <strong>Kaynak Sipariş / İrsaliye</strong>
                                @if($document && $document->incomingRelations->isNotEmpty())
                                    <x-v65.document-sources :document="$document" />
                                @else
                                    <p>Kaynak sipariş ve irsaliye bağlantıları kayıtlı belgeler üzerinden yönetilir.</p>
                                @endif
                            </div>
                        </section>
                    </div>
                </section>
            @else
            <section class="v65-reference-info-card" aria-label="Belge bilgileri">
                <header class="v65-reference-info-head">
                    <strong>Belge Bilgileri</strong>
                    <small>Bu alanlar yalnız {{ $screen['title'] }} için düzenlenmiştir</small>
                </header>
                <div class="v65-reference-info-fields">
            <div class="v65-doc-contact-picker">
                <label class="field">{{ $screen['party'] }}
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
                <details class="v65-reference-contact-prices">
                    <summary>Cari fiyat listesi</summary>
                <div class="v65-doc-contact-actions">
                    <x-v65.button variant="secondary" size="sm" wire:click="applyContactPrices" wire:loading.attr="disabled" wire:target="applyContactPrices" wire:confirm="Manuel girdiğiniz satır fiyatları da seçili carinin fiyat listesine göre değiştirilecek. Devam edilsin mi?">Cari Fiyatlarını Uygula</x-v65.button>
                    <small>Cari değişince otomatik fiyatlar güncellenir; manuel fiyatlar yalnızca bu butonla yeniden hesaplanır.</small>
                </div>
                </details>
            </div>

            <label class="field">{{ $screen['date'] }} <input type="date" wire:model="documentDate"><x-v65.input-error field="documentDate" /></label>
            <label class="field"><span>Para Birimi</span><input readonly value="TRY" title="Satış muhasebesi mevcut aşamada TRY bazlıdır"></label>
            <label class="field"><span>Varsayılan Depo</span>
                <select wire:model.live="uiMeta.default_location_id"><option value="">Satır bazında seç</option>
                    @foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach
                </select>
            </label>
            @if($this instanceof \App\Livewire\Sales\QuoteEditor)
                <label class="field">Teklif Geçerlilik Tarihi <input type="date" wire:model="validUntil"><x-v65.input-error field="validUntil" /></label>
                <label class="field">Teslim Süresi <input wire:model.blur="uiMeta.delivery_terms" maxlength="140" placeholder="Örn. 7 iş günü"></label>
                <label class="field">Ödeme Şartı <select wire:model.live="uiMeta.payment_terms"><option value="">Seçin</option>
                    <option value="Peşin">Peşin</option><option value="15 Gün">15 Gün</option>
                    <option value="30 Gün">30 Gün</option><option value="60 Gün">60 Gün</option>
                </select></label>
            @elseif($this instanceof \App\Livewire\Sales\SalesOrderEditor)
                <label class="field">Planlanan Teslim <input type="date" wire:model="uiMeta.planned_delivery" title="Beklenen Sevk"></label>
                <label class="field">Sevkiyat Deposu
                    <select wire:model.live="uiMeta.shipment_location_id"><option value="">Satırdan seç</option>
                        @foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach
                    </select>
                </label>
                <label class="field">Müşteri Sipariş No <input wire:model.blur="uiMeta.customer_order_no" maxlength="140"></label>
            @elseif($this instanceof \App\Livewire\Sales\DispatchEditor)
                <div class="field v65-reference-source-field">
                    <strong>Kaynak Satış Siparişi</strong>
                    @if($document && $document->incomingRelations->isNotEmpty())
                        <x-v65.document-sources :document="$document" />
                    @else
                        <span>Onaylı siparişten sevk işlemiyle oluşturulur</span>
                    @endif
                </div>
                <label class="field">Çıkış Deposu
                    <select wire:model.live="uiMeta.shipment_location_id"><option value="">Satırdan seç</option>
                        @foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach
                    </select>
                </label>
                <label class="field">Araç / Plaka <input wire:model.blur="uiMeta.vehicle_plate" maxlength="140"></label>
            @elseif($this instanceof \App\Livewire\Sales\SalesInvoiceEditor)
                <label class="field">Vade Tarihi <input type="date" wire:model="dueDate"><x-v65.input-error field="dueDate" /></label>
                <div class="field v65-reference-source-field">
                    <strong>Kaynak Sevk / İrsaliye</strong>
                    @if($document && $document->incomingRelations->isNotEmpty())
                        <x-v65.document-sources :document="$document" />
                    @else
                        <span>Kaynak sipariş / irsaliye kayıtlarıyla ilişkilendirilir</span>
                    @endif
                </div>
                @if($this instanceof \App\Livewire\Sales\SalesInvoiceEditor)
                    <label class="field">Ödeme Şekli <select wire:model="uiMeta.payment_method">
                        <option value="">Seçin</option><option value="Peşin">Peşin</option>
                        <option value="Vadeli">Vadeli</option><option value="Banka">Banka</option>
                    </select></label>
                @endif
            @endif
            <details class="v65-reference-more-options">
                <summary>Belge indirimi, teslim ve ek seçenekler</summary>
                <div class="v65-reference-more-fields">
                    @if($this instanceof \App\Livewire\Sales\QuoteEditor)
                    <label class="field">Teslim Yeri
                        <select wire:model="uiMeta.delivery_location_id"><option value="">Teslim yeri seçin</option>
                            @foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach
                        </select>
                    </label>
                    <label class="field">Müşteri Referans No <input wire:model.blur="uiMeta.customer_reference_no" maxlength="140"></label>
                    @elseif($this instanceof \App\Livewire\Sales\SalesInvoiceEditor)
                        <label class="field">Müşteri Referans No <input wire:model.blur="uiMeta.customer_reference_no" maxlength="140"></label>
                    @elseif($this instanceof \App\Livewire\Sales\SalesOrderEditor)
                        <label class="field">Vade <input type="date" wire:model="dueDate"><x-v65.input-error field="dueDate" /></label>
                    @endif
                    <label class="field">Belge İskonto % <input wire:model.blur="discountRate" data-tr-decimal inputmode="decimal"><x-v65.input-error field="discountRate" /></label>
                    <label class="field v65-doc-notes">Not <textarea wire:model="notes" rows="3"></textarea></label>
                </div>
            </details>
                </div>
                <div class="v65-reference-document-sources">
                    @if($document && $document->incomingRelations->isNotEmpty())
                        <x-v65.document-sources :document="$document" />
                    @endif
                    @if($this instanceof \App\Livewire\Sales\DispatchEditor || $this instanceof \App\Livewire\Sales\SalesInvoiceEditor)
                        <small>Kaynak sipariş ve irsaliye bağlantıları kayıtlı belge üzerinden yönetilir.</small>
                    @endif
                </div>
                <x-v65.document-prototype-stage :document="$document" :screen-type="$screenType" />
            </section>



            @endif

            <section class="v65-doc-lines v65-reference-products-card" aria-label="Ürün ve hizmetler" x-data="{ fastEntry: true }">
                <x-v65.document-prototype-toolbar :screen-type="$screenType"
                    :products="$this->quickProductOptions()" :quick-search="$quickProductSearch" />
                <details class="v65-reference-advanced">
                    <summary>Özel KDV / Gelişmiş Vergi İşlemleri</summary>
            <div class="v65-doc-vat-toolbar" role="group" aria-label="Satırlara toplu KDV uygula">
                <label class="field">Toplu KDV % <input wire:model="bulkVatRate" inputmode="decimal" data-tr-decimal aria-invalid="{{ $errors->has('bulkVatRate') ? 'true' : 'false' }}"><x-v65.input-error field="bulkVatRate" /></label>
                <x-v65.button variant="secondary" size="sm" wire:click="applyVatToAll">Tüm Satırlara Uygula</x-v65.button>
                <x-v65.button variant="secondary" size="sm" wire:click="clearVat">KDV'yi Temizle</x-v65.button>
            </div>
                </details>
                <small class="v65-doc-mobile-scroll-hint">Tablodaki diğer satır alanlarını görmek için yatay kaydırın.</small>

                <div class="table-scroll document-lines v65-doc-table-scroll"><table class="data-table v65-doc-table v65-prototype-lines">
                    <x-v65.document-product-table-head :price-label="$screen['price']" />
                    <tbody>
                    @foreach($lines as $index => $line)
                        @php
                            $service = ($line['line_kind'] ?? 'stock') === 'service';
                            $custom = ($line['line_kind'] ?? 'stock') === 'custom';
                            $rowKey = (string) ($line['row_key'] ?? $index);
                            $isEntryRow = $index === count($lines) - 1 && $this->isPrototypeEntryRow($line);
                            $productChoices = $this->productOptionsForLine($rowKey, (int) ($line['product_id'] ?? 0));
                            $selectedProduct = $productChoices->firstWhere('id', (int) ($line['product_id'] ?? 0));
                            $previewLine = $draftPreview?->lines[$index] ?? null;
                            $lineNet = $previewLine?->lineTotal ?? (!$documentDirty ? ($line['line_total'] ?? null) : null);
                            $lineVat = $lineNet !== null && is_numeric((string) ($line['vat_rate'] ?? 0))
                                ? \App\Support\Money\Money::of((string) $lineNet)->percent((string) ($line['vat_rate'] ?? 0))->amount : null;
                            $lineWithVat = $lineVat !== null ? \App\Support\Money\Money::of((string) $lineNet)->plus(\App\Support\Money\Money::of($lineVat))->amount : null;
                        @endphp
                        @if(! $isEntryRow)
                        <tr wire:key="sales-line-{{ $line['row_key'] ?? $index }}">
                            <td>{{ $index + 1 }}</td>
                            <td class="v65-doc-product-combined">
                                <div class="v65-doc-product-cell">
                                @if($service)<span class="v65-doc-muted">HİZMET</span>
                                @elseif($custom)
                                    <div class="v65-custom-quote-code">
                                        <strong>{{ $line['configuration']['custom_code'] ?? 'Kayıt sonrası özel kod' }}</strong>
                                        @if($this instanceof \App\Livewire\Sales\QuoteEditor)
                                            <button type="button" wire:click="selectCustomRow('{{ $rowKey }}')" data-v65-custom-quote-launch>Görseller</button>
                                        @endif
                                    </div>
                                @else
                                    @if(!empty($line['product_id']))<details class="v65-reference-change-product"><summary>Ürünü değiştir</summary>@endif
                                    <input type="search" class="v65-doc-product-search" data-v65-fast-product-search
                                        wire:model.live.debounce.300ms="productSearches.{{ $line['row_key'] ?? $index }}"
                                        placeholder="Kod / barkod / ad ara" autocomplete="off" aria-label="Satır {{ $index + 1 }} ürün ara">
                                    <select data-v65-fast-product-select wire:model.live="lines.{{ $index }}.product_id" aria-label="Satır {{ $index + 1 }} ürün seç">
                                        <option value="">Ürün seçin</option>
                                        @forelse($productChoices as $product)
                                            <option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }} {{ $product->barcode ? '(' . $product->barcode . ')' : '' }}</option>
                                        @empty
                                            <option disabled value="">Ürün bulunamadı</option>
                                        @endforelse
                                    </select>
                                    <x-v65.input-error :field="'lines.'.$index.'.product_id'"/>
                                    @if(!empty($line['product_id']))</details>@endif
                                @endif
                            </div>
                                <div class="v65-doc-product-detail">
                                @if($service || $custom)
                                    <input wire:model.blur="lines.{{ $index }}.description" maxlength="255"
                                        placeholder="{{ $custom ? 'Özel üretim ürün adı' : 'Hizmet açıklaması' }}">
                                    @if($custom)
                                        <small class="v65-doc-muted">Ana stok sistemine eklenmez</small>
                                    @endif
                                    <x-v65.input-error :field="'lines.'.$index.'.description'"/>
                                @else
                                    <strong>{{ $line['description'] ?? 'Ürün seçin' }}</strong>
                                    @if($selectedProduct)<small class="v65-reference-sku">{{ $selectedProduct->code }}</small>@endif
                                @endif

                            </div>
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
                            <td class="align-end"><input wire:model.blur="lines.{{ $index }}.unit_price" inputmode="decimal" data-tr-decimal data-v65-money-display aria-invalid="{{ $errors->has('lines.'.$index.'.unit_price') ? 'true' : 'false' }}" aria-label="Satır fiyatı"></td>
                            <td class="align-end"><input wire:model.blur="lines.{{ $index }}.line_discount_rate" inputmode="decimal" data-tr-decimal aria-invalid="{{ $errors->has('lines.'.$index.'.line_discount_rate') ? 'true' : 'false' }}" aria-label="Satır iskonto oranı"></td>
                            <td class="align-end"><input wire:model.blur="lines.{{ $index }}.vat_rate" inputmode="decimal" data-tr-decimal aria-invalid="{{ $errors->has('lines.'.$index.'.vat_rate') ? 'true' : 'false' }}" aria-label="Satır KDV oranı"></td>
                            <td class="align-end v65-doc-line-net">{{ $lineNet !== null ? \App\Support\Formatting\TrFormatter::money((string) $lineNet) : '—' }}</td>
                            <td class="align-end v65-doc-line-incl">{{ $lineWithVat !== null ? \App\Support\Formatting\TrFormatter::money($lineWithVat) : '—' }}</td>
                            <td class="v65-reference-actions-cell"><x-v65.document-line-actions :index="$index">
                                <div class="v65-doc-line-details">
                                    @if($custom)
                                        <strong>Özel Üretim · Stok Dışı</strong>
                                        <label>Teknik Özellikler / Ölçüler
                                            <textarea wire:model.blur="lines.{{ $index }}.configuration.technical_notes"
                                                rows="3" maxlength="1500" placeholder="Malzeme, renk, ebat ve üretim notları"></textarea>
                                        </label>
                                    @else
                                        <label>Tür <select wire:model.live="lines.{{ $index }}.line_kind" aria-label="Satır türü">
                                            <option value="stock">Stok</option><option value="service">Hizmet</option>
                                        </select></label>
                                    @endif
                                    @if(! $service && ! $custom)
                                    <label>Depo <select wire:model="lines.{{ $index }}.location_id">
                                        <option value="">Depo seçin</option>
                                        @foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach
                                    </select></label>
                                    <label>Satır Notu <input wire:model.blur="lines.{{ $index }}.description" placeholder="İsteğe bağlı not"></label>
                                    @endif
                                    <small>İskonto tutarı: {{ $previewLine ? \App\Support\Formatting\TrFormatter::money((string) $previewLine->discountAmount) : '—' }}</small>
                                </div>
                            </x-v65.document-line-actions></td>
                        </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table></div>
                @if($this instanceof \App\Livewire\Sales\QuoteEditor)
                    @php
                        $customSelection = $this->selectedCustomRow();
                        $customPhotos = $this->selectedCustomImages();
                        $pendingPhotos = $this->selectedPendingCustomImages();
                    @endphp
                    @if($customSelection)
                        <x-v65.modal id="v65-custom-quote-dialog" title="Özel Üretim Ürün Detayı"
                            size="lg" class="v65-custom-quote-dialog">
                        <section class="v65-custom-quote-panel" aria-label="Özel üretim görselleri ve teknik detaylar">
                            <header class="v65-custom-quote-panel-head">
                                <div>
                                    <strong>Seçili Özel Üretim</strong>
                                    <small>{{ $customSelection['line']['configuration']['custom_code'] ?? 'Yeni özel üretim kalemi' }}
                                        · {{ $customSelection['line']['description'] ?? '' }}</small>
                                </div>
                                <span class="v65-custom-quote-nostock">Ana stoktan bağımsız</span>
                            </header>
                            <div class="v65-custom-quote-detail v65-custom-quote-workspace">
                                <div class="v65-custom-quote-left">
                                <label>Ürün Adı
                                    <input wire:model.blur="lines.{{ $customSelection['index'] }}.description"
                                        maxlength="255" placeholder="Müşteriye özel ürün adı">
                                </label>
                                <label>Teknik Özellikler / Ölçüler
                                    <textarea wire:model.blur="lines.{{ $customSelection['index'] }}.configuration.technical_notes"
                                        rows="3" maxlength="1500" placeholder="Malzeme, renk, ölçü ve üretim açıklaması"></textarea>
                                </label>
                                <strong>Ürün Görselleri (en fazla 10 adet)</strong>
                                @if(!$document || $document->status === 'draft')
                                    <div class="v65-custom-image-drop" tabindex="0" role="group"
                                         aria-label="Görsel yükleme, ekran görüntüsü yapıştırma veya bırakma"
                                         x-on:dragover.prevent
                                         x-on:drop.prevent="
                                            const files = Array.from($event.dataTransfer.files).filter(f => ['image/png','image/jpeg','image/webp'].includes(f.type)).slice(0,8);
                                            if (files.length) $wire.uploadMultiple('customImageUploads', files, () => $wire.uploadCustomImages());
                                         "
                                         x-on:paste="
                                            const files = Array.from($event.clipboardData.files).filter(f => ['image/png','image/jpeg','image/webp'].includes(f.type)).slice(0,8);
                                            if (files.length) {
                                                $event.preventDefault();
                                                $wire.uploadMultiple('customImageUploads', files, () => $wire.uploadCustomImages());
                                            }
                                         ">
                                        <strong>Ctrl + V ile ekran görüntüsü yapıştırın veya sürükleyip bırakın</strong>
                                        <label>Görsel seç
                                            <input type="file" wire:model="customImageUploads"
                                                accept="image/png,image/jpeg,image/webp" multiple>
                                        </label>
                                        <button type="button" class="v65-btn v65-btn--secondary v65-btn--sm"
                                            wire:click="uploadCustomImages" wire:loading.attr="disabled"
                                            wire:target="customImageUploads,uploadCustomImages">Görselleri Ekle</button>
                                    </div>
                                    <x-v65.input-error field="customImageUploads"/>
                                    <x-v65.input-error field="customImageUploads.0"/>
                                @else
                                    <small>Kesinleşmiş teklifte görseller değiştirilemez.</small>
                                @endif
                                @if($customPhotos->isNotEmpty() || $pendingPhotos !== [])
                                    <div class="v65-custom-quote-gallery">
                                        @foreach($customPhotos as $image)
                                            @php
                                                $versions = $image->editedVersions()->orderBy('id')->get();
                                                $latest = $versions->last();
                                                $stored = $latest?->instructions;
                                                $scene = is_array($stored) && ($stored['version'] ?? null) === 2
                                                    ? $stored
                                                    : ['version' => 2, 'base_index' => $versions->count() - 1, 'ops' => [], 'objects' => []];
                                            @endphp
                                            <div class="v65-custom-quote-image" wire:key="custom-quote-image-{{ $image->id }}">
                                                <img src="{{ route('sales.quotes.custom-image', ['document' => $document->id, 'attachment' => $image->id]) }}"
                                                     alt="{{ $image->original_name }}" loading="lazy">
                                                <small>{{ $image->sort_order === $customPhotos->min('sort_order') ? 'Kapak Resmi' : 'Görsel' }}</small>
                                                @if($document->status === 'draft')
                                                    <button type="button" class="v65-custom-quote-edit-select"
                                                        data-v65-image-select data-image-id="{{ $image->id }}"
                                                        data-image-url="{{ route('sales.quotes.custom-image', ['document' => $document->id, 'attachment' => $image->id]) }}"
                                                        data-image-original="{{ route('sales.quotes.custom-image', ['document' => $document->id, 'attachment' => $image->id, 'original' => 1]) }}"
                                                        data-image-base="{{ route('sales.quotes.custom-image', ['document' => $document->id, 'attachment' => $image->id, 'base' => 1]) }}"
                                                        data-image-base-index="{{ $scene['base_index'] }}"
                                                        data-image-scene="{{ json_encode($scene, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                                                        data-image-name="{{ $image->original_name }}">Düzenle</button>
                                                    <div class="v65-custom-image-actions">
                                                        <button type="button" class="v65-custom-image-action v65-custom-image-action--cover" wire:click="setCustomCover({{ $image->id }})"
                                                            title="Kapak resmi seç">Kapak</button>
                                                        <button type="button" class="v65-custom-image-action v65-custom-image-action--delete" wire:click="deleteCustomImage({{ $image->id }})"
                                                            wire:confirm="Bu görsel kaldırılsın mı?" title="Görseli kaldır">Sil</button>
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                        @foreach($pendingPhotos as $index => $photo)
                                            @php
                                                $pendingFile = $photo['edited'] ?? $photo['original'];
                                            @endphp
                                            <div class="v65-custom-quote-image" wire:key="pending-custom-image-{{ $customSelection['line']['row_key'] }}-{{ $index }}">
                                                <img src="{{ $pendingFile->temporaryUrl() }}"
                                                    alt="{{ $photo['original']->getClientOriginalName() }}" loading="lazy">
                                                <small>{{ $index === 0 && $customPhotos->isEmpty() ? 'Kapak Resmi' : 'Geçici Görsel' }}</small>
                                                <button type="button" class="v65-custom-quote-edit-select"
                                                    data-v65-image-select data-image-id="staged-{{ $index }}"
                                                    data-image-url="{{ $pendingFile->temporaryUrl() }}"
                                                    data-image-original="{{ $photo['original']->temporaryUrl() }}"
                                                    data-image-base="{{ $photo['original']->temporaryUrl() }}"
                                                    data-image-base-index="-1"
                                                    data-image-scene="{{ json_encode($photo['instructions'] ?: ['version' => 2, 'base_index' => -1, 'ops' => [], 'objects' => []], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                                                    data-image-name="{{ $photo['original']->getClientOriginalName() }}">Düzenle</button>
                                                <div class="v65-custom-image-actions">
                                                    <button type="button" class="v65-custom-image-action v65-custom-image-action--cover" wire:click="setPendingCustomCover({{ $index }})">Kapak</button>
                                                    <button type="button" class="v65-custom-image-action v65-custom-image-action--delete" wire:click="removePendingCustomImage({{ $index }})"
                                                        wire:confirm="Geçici görsel kaldırılsın mı?">Sil</button>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                </div>
                                @if((!$document || $document->status === 'draft')
                                    && ($customPhotos->isNotEmpty() || $pendingPhotos !== []))
                                    <x-v65.quote-image-editor />
                                @else
                                    <div class="v65-custom-photo-editor-placeholder">
                                        Görsel eklediğiniz anda düzenleme araçları açılır. Teklifi kaydetmeniz gerekmez.
                                    </div>
                                @endif
                            </div>
                        </section>
                        <x-v65.input-error field="customImageEditUpload"/>
                        <x-slot:footer>
                            <x-v65.button variant="secondary" data-v65-modal-close>İşlemi Tamamla / Kapat</x-v65.button>
                        </x-slot:footer>
                        </x-v65.modal>
                    @endif
                @endif
                <div class="v65-reference-new-line">
                    <button type="button" class="v65-reference-new-product-button" data-v65-add-product-focus x-on:click="fastEntry = true">+ Yeni ürün satırı</button>
                    <small>Yeni ürün eklemek için üstteki ürün arama alanını kullanın. Enter ile seçin, Tab ile ilerleyin.</small>
                </div>
                @if($screenType !== 'sales_invoice')
                <div class="v65-reference-totals-layout">
                    <div class="v65-reference-totals-help">
                        <strong>Hızlı giriş:</strong> Ürün kodu veya barkodu üstteki arama alanına yazıp Enter ile ekleyin.
                        <p>Depo, satır notu ve birim bilgileri satır detayından düzenlenebilir.
                        Ek iskonto KDV hariç net tutardan düşülür; geçerli matrah ve KDV sunucuda hesaplanır.</p>
                        <small>* Satır KDV dahil tutarı belge genel iskontoları öncesi bilgi amaçlıdır.</small>
                    </div>
                    <x-v65.document-summary class="v65-reference-inline-summary" :compact="true" :editable="true"
                        :document="$document" :preview="$draftPreview" :additional-discount="$additionalDiscountAmount" />
                </div>
                @endif
            </section>
            @if($this instanceof \App\Livewire\Sales\SalesInvoiceEditor)
                @php
                    $invoiceRisk = $this->invoiceRiskProjection();
                    $draftRiskOverview = $this->contactRiskOverview();
                @endphp
                @if($invoiceRisk?->knownLimitExceeded || ($draftRiskOverview['exceeded'] ?? false))
                    <div class="alert alert-warning v65-simple-invoice-warning" role="alert">
                        <strong>Cari risk limiti aşılıyor.</strong>
                        @if($invoiceRisk)
                            <span>Mevcut cari bakiyesi: {{ \App\Support\Formatting\TrFormatter::money($invoiceRisk->currentBalance) }} ₺;
                                fatura: {{ \App\Support\Formatting\TrFormatter::money($invoiceRisk->orderTotal) }} ₺;
                                tahmini risk: {{ \App\Support\Formatting\TrFormatter::money($invoiceRisk->knownExposure) }} ₺;
                                limit: {{ \App\Support\Formatting\TrFormatter::money($invoiceRisk->riskLimit) }} ₺.</span>
                        @else
                            <span>Önizlenen tutar seçili carinin tanımlı risk limitini aşabilir.</span>
                        @endif
                        <label><input type="checkbox" wire:model="riskAccepted"> Risk limiti aşımını kabul ediyorum.</label>
                    </div>
                @endif
                <div class="v65-invoice-bottom-zone" aria-label="Fatura alt özeti">
                    @if($draftRiskOverview)
                        <details class="v65-invoice-risk-details {{ ($draftRiskOverview['exceeded'] ?? false) ? 'is-exceeded' : '' }}"
                            {{ ($draftRiskOverview['exceeded'] ?? false) ? 'open' : '' }}>
                            <summary>
                                <strong>Cari Finansal Durum (TRY)</strong>
                                <span>Bilinen risk:
                                    <b>{{ \App\Support\Formatting\TrFormatter::money($draftRiskOverview['projected']) }} ₺</b>
                                </span>
                                <span>Risk limiti:
                                    <b>{{ $draftRiskOverview['configured'] ? \App\Support\Formatting\TrFormatter::money($draftRiskOverview['limit']).' ₺' : 'Tanımlanmamış' }}</b>
                                </span>
                                <small>Risk ayrıntıları</small>
                            </summary>
                            <x-v65.contact-risk-overview :risk="$draftRiskOverview" />
                        </details>
                    @endif
                    <section class="v65-invoice-bottom-totals" aria-label="Fatura toplamları">
                        <header class="v65-invoice-totals-toolbar">
                            <strong>Belge Toplamları</strong>
                            <span class="v65-invoice-line-count">
                                <i class="v65-reference-count-dot" aria-hidden="true"></i>
                                {{ $lineCount }} kalem · Sunucu hesaplaması
                            </span>
                            <small>Faturayı Kaydet, muhasebe kaydını da oluşturur. Sonradan Düzenle kullanılabilir.</small>
                            @if(!$document)
                                <x-v65.button variant="secondary" size="sm"
                                    wire:click="resetNewDraft" wire:loading.attr="disabled" wire:target="resetNewDraft">
                                    Girişi Sıfırla
                                </x-v65.button>
                            @endif
                        </header>
                        <x-v65.document-summary class="v65-invoice-summary" :compact="true" :editable="true"
                            :document="$document" :preview="$draftPreview" :additional-discount="$additionalDiscountAmount" />
                    </section>
                </div>
            @endif
            @if($screenType !== 'sales_invoice')
                <x-v65.document-prototype-footer :document="$document" :screen-type="$screenType" :item-count="$lineCount" />
            @endif
        </form>
    @elseif($document)
        <x-v65.document-readonly :document="$document" />
    @endif

    @if($this instanceof \App\Livewire\Sales\QuoteEditor
        && $document && $document->status !== 'draft'
        && $document->lines->contains('line_kind', 'custom'))
        <button type="button" class="v65-btn v65-btn--secondary v65-btn--sm v65-custom-quote-readonly-launch"
            data-v65-modal-open="v65-custom-quote-readonly-dialog">Özel Üretim Görselleri</button>
        <x-v65.modal id="v65-custom-quote-readonly-dialog" title="Özel Üretim Görselleri"
            size="lg" class="v65-custom-quote-dialog">
        <section class="v65-custom-quote-panel" aria-label="Kayıtlı özel üretim görselleri">
            <header class="v65-custom-quote-panel-head"><strong>Teklif Görselleri</strong></header>
            <div class="v65-custom-quote-detail">
                @foreach($document->lines->where('line_kind', 'custom') as $customLine)
                    @php
                        $savedCode = (string) ($customLine->configuration['custom_code'] ?? '');
                        $savedPhotos = $document->attachments()
                            ->where('collection', 'quote_custom_'.$savedCode)
                            ->orderBy('sort_order')->get();
                    @endphp
                    <div><strong>{{ $savedCode }} · {{ $customLine->description }}</strong></div>
                    @if($savedPhotos->isNotEmpty())
                        <div class="v65-custom-quote-gallery">
                            @foreach($savedPhotos as $photo)
                                <div class="v65-custom-quote-image">
                                    <img src="{{ route('sales.quotes.custom-image', ['document' => $document->id, 'attachment' => $photo->id]) }}"
                                        alt="{{ $photo->original_name }}" loading="lazy">
                                    <small>{{ $photo->original_name }}</small>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <small>Görsel eklenmemiş.</small>
                    @endif
                @endforeach
            </div>
        </section>
        <x-slot:footer>
            <x-v65.button variant="secondary" data-v65-modal-close>Kapat</x-v65.button>
        </x-slot:footer>
        </x-v65.modal>
    @endif

    @if($document && $document->status === 'draft')
        <div class="v65-doc-print-only">
            <x-v65.document-readonly :document="$document" />
            <x-v65.document-summary :document="$document" />
        </div>
    @endif

    @if($document && $document->status !== 'draft')
        <x-v65.document-summary :editable="false" class="v65-doc-screen-summary" :document="$document" />
    @endif
    @if($this instanceof \App\Livewire\Sales\SalesInvoiceEditor && $document && $document->status !== 'draft')
        <x-v65.contact-risk-overview :risk="$this->contactRiskOverview()" />
    @endif

    @if($document && ! ($this instanceof \App\Livewire\Sales\SalesInvoiceEditor))
        <section id="v65-reference-workflow" class="document-actions panel v65-document-actions v65-doc-workflow" role="group" aria-label="Belge işlemleri">
            <h3 class="v65-doc-workflow-title">Belge İşlemleri</h3>
            @if(method_exists($this, 'internalReview') && $document->status === 'draft')
                <x-v65.button variant="secondary" wire:click="internalReview" wire:loading.attr="disabled" wire:target="internalReview">İç Onaya Gönder</x-v65.button>
                <x-v65.button variant="secondary" wire:click="customerReview" wire:loading.attr="disabled" wire:target="customerReview">Müşteri İncelemesine Gönder</x-v65.button>
            @endif
            @if(method_exists($this, 'approve') && in_array($document->status, ['internal_review','customer_review']))
                <x-v65.button wire:click="approve" wire:loading.attr="disabled" wire:target="approve" :disabled="$documentDirty">Onayla</x-v65.button>
            @endif
            @if(method_exists($this, 'revision') && $document->number)
                <x-v65.button variant="secondary" wire:click="revision" wire:loading.attr="disabled" wire:target="revision">Revizyon Oluştur</x-v65.button>
            @endif
            @if(method_exists($this, 'toOrder') && $document->status === 'approved')
                <x-v65.button wire:click="toOrder" wire:loading.attr="disabled" wire:target="toOrder">Sipariş Oluştur</x-v65.button>
            @endif
            @if(method_exists($this, 'confirm') && $document->status === 'draft')
                <label><input type="checkbox" wire:model="riskAccepted"> Risk uyarısını kabul ediyorum</label>
                <x-v65.button wire:click="confirm" wire:loading.attr="disabled" wire:target="confirm" :disabled="$documentDirty">Siparişi Onayla</x-v65.button>
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
                <x-v65.button wire:click="post" wire:loading.attr="disabled" wire:target="post" :disabled="$documentDirty">Kaydet ve İşle</x-v65.button>
            @endif
        </section>
    @endif

    @if($this instanceof \App\Livewire\Sales\SalesInvoiceEditor && $document
        && $document->status === 'posted' && auth()->user()?->can('warehouse_slips.create')
        && auth()->user()?->can('warehouse_slips.view')
        && auth()->user()?->can('sales_invoices.view')
        && $document->lines()->where('line_kind', 'stock')->whereNotNull('product_id')->exists())
        <div class="v65-invoice-warehouse-link">
            <span>Sevk bilgileri ve stok satırları kayıtlı faturadan alınacaktır. Ambar fişi kaydedilmeden oluşturulmaz.</span>
            <a class="button-link" href="{{ route('warehouse.transport.create', ['invoice' => $document->id]) }}">
                Ambar Fişi Oluştur
            </a>
        </div>
    @endif
    @if($this instanceof \App\Livewire\Sales\SalesInvoiceEditor && $document
        && auth()->user()?->can('sales_invoices.cancel')
        && ($document->status === 'draft' || ! \App\Support\Documents\DocumentEditCatalog::hasReversal($document)))
        <x-v65.record-delete-footer>
            <x-v65.button variant="danger" size="sm" wire:click="requestRecordDeletion('deleteInvoice')"
                wire:loading.attr="disabled" wire:target="requestRecordDeletion">Sil</x-v65.button>
        </x-v65.record-delete-footer>
    @endif
    @include('livewire.partials.record-delete-confirmation')
</div>
