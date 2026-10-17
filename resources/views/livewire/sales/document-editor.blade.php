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
            || (($line['line_kind'] ?? 'stock') === 'service' && trim((string) ($line['description'] ?? '')) !== ''))->count();
    @endphp
    <x-v65.document-prototype-heading :document="$document" :screen-type="$screenType"
        :index-route="$documentIndexRoute" :dirty="$documentDirty" :form-id="$formId"
            :can-copy="$this->canCreateEditableCopy()" :edit-permission="$this->permissionPrefix()" />
    @if($document && $document->status !== 'draft' && !($this instanceof \App\Livewire\Purchases\GoodsReceiptEditor))
        <div class="v65-reference-edit-notice" role="status">
            <strong>Bu sürüm kayıtlıdır.</strong>
            @if($this instanceof \App\Livewire\Sales\SalesInvoiceEditor)
                Faturayı değiştirmek için <strong>Düzenle</strong> seçin.
                Önceki fatura, yeni kayıt kaydedilene kadar geçerlidir.
                <strong>Sil</strong> işlemi muhasebe geçmişini koruyarak faturayı listeden kaldırır.
            @elseif($document->status === 'posted' && ! $this->canCreateEditableCopy())
                Kesinleşmiş belge üzerinde tutar veya stok değişikliği yapılamaz.
                Düzeltme için önce Belge İşlemleri bölümündeki Ters Kayıt uygulanmalıdır.
                Ters kayıt oluştuktan sonra bu ekrandan yeni düzenleme taslağı açılabilir.
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

    @if($this instanceof \App\Livewire\Sales\SalesInvoiceEditor)
        <x-v65.contact-risk-overview :risk="$this->contactRiskOverview()" />
    @endif

    @php
        $draftPreview = $this->draftTotalsPreview();
    @endphp

    @if(!$document || $document->status === 'draft')
        <form wire:submit="save" id="{{ $formId }}" class="document-editor-form v65-doc-editor v65-reference-form">
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
                        @if($isEntryRow)
                            <tr wire:key="sales-entry-{{ $rowKey }}" class="v65-reference-entry-row" x-show="fastEntry">
                                <td>+</td>
                                <td class="v65-reference-entry-cell">
                                    <input type="search" class="v65-doc-product-search" data-v65-fast-product-search
                                        wire:model.live.debounce.300ms="productSearches.{{ $rowKey }}"
                                        autocomplete="off" placeholder="Ürün kodu, barkod veya ürün adı yazın..."
                                        aria-label="Yeni boş satır ürün ara">
                                    <select data-v65-fast-product-select wire:model.live="lines.{{ $index }}.product_id"
                                        aria-label="Yeni boş satır ürün seç">
                                        <option value="">Ürün seçin</option>
                                        @forelse($productChoices as $product)
                                            <option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }}</option>
                                        @empty
                                            <option value="" disabled>Aramaya uygun ürün bulunamadı</option>
                                        @endforelse
                                    </select>
                                </td>
                                <td colspan="8" class="v65-reference-entry-description">
                                    Ürün seçildiğinde yeni boş satır otomatik açılır · Enter ile ekleyin
                                </td>
                            </tr>
                        @else
                        <tr wire:key="sales-line-{{ $line['row_key'] ?? $index }}">
                            <td>{{ $index + 1 }}</td>
                            <td class="v65-doc-product-combined">
                                <div class="v65-doc-product-cell">
                                @if($service)<span class="v65-doc-muted">HİZMET</span>
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
                                @if($service)
                                    <input wire:model.blur="lines.{{ $index }}.description" placeholder="Hizmet açıklaması">
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
                            <td class="align-end"><input wire:model.blur="lines.{{ $index }}.unit_price" inputmode="decimal" data-tr-decimal aria-invalid="{{ $errors->has('lines.'.$index.'.unit_price') ? 'true' : 'false' }}" aria-label="Satır fiyatı"></td>
                            <td class="align-end"><input wire:model.blur="lines.{{ $index }}.line_discount_rate" inputmode="decimal" data-tr-decimal aria-invalid="{{ $errors->has('lines.'.$index.'.line_discount_rate') ? 'true' : 'false' }}" aria-label="Satır iskonto oranı"></td>
                            <td class="align-end"><input wire:model.blur="lines.{{ $index }}.vat_rate" inputmode="decimal" data-tr-decimal aria-invalid="{{ $errors->has('lines.'.$index.'.vat_rate') ? 'true' : 'false' }}" aria-label="Satır KDV oranı"></td>
                            <td class="align-end v65-doc-line-net">{{ $lineNet !== null ? \App\Support\Formatting\TrFormatter::money((string) $lineNet) : '—' }}</td>
                            <td class="align-end v65-doc-line-incl">{{ $lineWithVat !== null ? \App\Support\Formatting\TrFormatter::money($lineWithVat) : '—' }}</td>
                            <td class="v65-reference-actions-cell"><x-v65.document-line-actions :index="$index">
                                <div class="v65-doc-line-details">
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
                                </div>
                            </x-v65.document-line-actions></td>
                        </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table></div>
                <div class="v65-reference-new-line">
                    <button type="button" class="v65-reference-new-product-button" data-v65-add-product-focus x-on:click="fastEntry = true">+ Yeni ürün satırı</button>
                    <small>Aşağıdaki boş satıra da doğrudan ürün kodu veya barkod yazabilirsiniz. Enter ürün seçer, Tab sonraki alana ilerler.</small>
                </div>
                <div class="v65-reference-totals-layout">
                    <div class="v65-reference-totals-help">
                        <strong>Hızlı giriş:</strong> Ürün kodunu aratıp Enter ile ekleyin. Sonraki boş satır otomatik hazırlanır.
                        <p>Depo, satır notu ve birim bilgileri satır detayından düzenlenebilir.
                        Ek iskonto KDV hariç net tutardan düşülür; geçerli matrah ve KDV sunucuda hesaplanır.</p>
                        <small>* Satır KDV dahil tutarı belge genel iskontoları öncesi bilgi amaçlıdır.</small>
                    </div>
                    <x-v65.document-summary class="v65-reference-inline-summary" :compact="true" :editable="true"
                        :document="$document" :preview="$draftPreview" :additional-discount="$additionalDiscountAmount" />
                </div>
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
                <small class="v65-simple-invoice-note">Faturayı Kaydet, muhasebe kaydını da oluşturur. Sonradan Düzenle kullanılabilir.</small>
            @endif
            <x-v65.document-prototype-footer :document="$document" :screen-type="$screenType" :item-count="$lineCount" />


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

    @if($document && $document->status !== 'draft')
        <x-v65.document-summary :editable="false" class="v65-doc-screen-summary" :document="$document" />
    @endif

    @if($document && ! ($this instanceof \App\Livewire\Sales\SalesInvoiceEditor))
        <section id="v65-reference-workflow" class="document-actions panel v65-document-actions v65-doc-workflow" role="group" aria-label="Belge işlemleri">
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
