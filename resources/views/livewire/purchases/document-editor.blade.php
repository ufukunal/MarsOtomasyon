<div class="document-workspace stack v65-reference-workspace">
    @php
        $isGoodsReceipt = $this instanceof \App\Livewire\Purchases\GoodsReceiptEditor
            || $document?->document_type === \App\Enums\DocumentType::GoodsReceipt;
    @endphp

    @php
        $documentIndexRoute = match (true) {
            $this instanceof \App\Livewire\Purchases\PurchaseQuoteEditor => 'purchases.quotes.index',
            $this instanceof \App\Livewire\Purchases\PurchaseOrderEditor => 'purchases.orders.index',
            $this instanceof \App\Livewire\Purchases\PurchaseDispatchEditor => 'purchases.dispatches.index',
            $this instanceof \App\Livewire\Purchases\GoodsReceiptEditor => 'purchases.receipts.index',
            default => 'purchases.invoices.index',
        };
        $screenType = match (true) {
            $this instanceof \App\Livewire\Purchases\PurchaseQuoteEditor => 'purchase_quote',
            $this instanceof \App\Livewire\Purchases\PurchaseOrderEditor => 'purchase_order',
            $this instanceof \App\Livewire\Purchases\PurchaseDispatchEditor => 'purchase_dispatch',
            $this instanceof \App\Livewire\Purchases\SupplierInvoiceEditor => 'supplier_invoice',
            default => 'goods_receipt',
        };
        $screen = config('document_prototype.'.$screenType, []);
        $formId = 'v65-reference-form-'.$screenType;
        $lineCount = collect($lines)->filter(fn ($line) => ! empty($line['product_id'])
            || (($line['line_kind'] ?? 'stock') === 'service' && trim((string) ($line['description'] ?? '')) !== ''))->count();
    @endphp
    @if($isGoodsReceipt)
        <x-v65.document-heading :document="$document" :title="$title" section="Satınalma Yönetimi" :index-route="$documentIndexRoute" :dirty="$documentDirty" />
    @else
        <x-v65.document-prototype-heading :document="$document" :screen-type="$screenType"
            :index-route="$documentIndexRoute" :dirty="$documentDirty" :form-id="$formId"
            :can-copy="$this->canCreateEditableCopy()"
            :can-reopen="$this instanceof \App\Livewire\Purchases\PurchaseDispatchEditor && $this->canReopenForEditing()"
            :edit-permission="$this->permissionPrefix()" />
    @endif
    @if($document && $document->status !== 'draft' && !($this instanceof \App\Livewire\Purchases\GoodsReceiptEditor))
        <div class="v65-reference-edit-notice" role="status">
            <strong>Bu sürüm kayıtlıdır.</strong>
            @if($document->status === 'posted' && ! $this->canCreateEditableCopy())
                Kesinleşmiş belge üzerinde tutar veya stok değişikliği yapılamaz.
                Düzeltme için önce Belge İşlemleri bölümündeki Ters Kayıt uygulanmalıdır.
                Ters kayıt oluştuktan sonra bu ekrandan yeni düzenleme taslağı açılabilir.
            @elseif($this instanceof \App\Livewire\Sales\QuoteEditor && $document->number)
                Eski teklif değişmez; düzenleme yeni bir teklif revizyonunda yapılır.
            @elseif($this instanceof \App\Livewire\Purchases\PurchaseDispatchEditor && $document->status === 'confirmed')
                @if($this->canReopenForEditing())
                    Alış irsaliyesi Mal Kabul görmediği için aynı numarayla taslağa döndürülüp düzenlenebilir.
                    Değişikliklerden sonra yeniden onaylanması gerekir.
                @else
                    Bağlı Mal Kabul veya başka kaynak işlemi bulunduğundan irsaliye güvenle yeniden açılamaz.
                    İlgili kayıtların düzeltilmesi gerekir.
                @endif
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
    @if($isGoodsReceipt && $document && $document->status === 'draft')
        <x-v65.document-sources class="v65-doc-draft-sources" :document="$document" />
    @endif

    @php
        $draftPreview = $this->draftTotalsPreview();
    @endphp

    @if(!$document || $document->status === 'draft')
        <form wire:submit="save" id="{{ $formId }}" class="document-editor-form v65-doc-editor v65-reference-form {{ $isGoodsReceipt ? 'v65-goods-receipt-reference' : '' }}">
            <section class="v65-reference-info-card" aria-label="Belge bilgileri">
                <header class="v65-reference-info-head">
                    <strong>Belge Bilgileri</strong>
                    <small>{{ $isGoodsReceipt ? 'Mal Kabul' : ($screen['title'] ?? 'Satınalma Belgesi') }} bilgileri</small>
                </header>
                <div class="v65-reference-info-fields">
            <div class="v65-doc-contact-picker">
                <label class="field">{{ $isGoodsReceipt ? 'Tedarikçi / Cari' : $screen['party'] }}
                    <input type="search" wire:model.live.debounce.300ms="contactSearch" placeholder="Kod veya unvan ile ara" autocomplete="off">
                    <small class="v65-doc-search-hint">Kod veya unvan yazarak arayın. En fazla 35 eşleşme listelenir.</small>
                    <small class="v65-doc-search-loading" wire:loading wire:target="contactSearch">Aranıyor…</small>
                </label>
                <x-v65.select label="Tedarikçi" wire:model.live="contactId" placeholder="Tedarikçi seçin" class="v65-doc-contact">
                    @forelse($this->contactOptions() as $contact)
                        <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                    @empty
                        <option value="" disabled>Aramaya uygun cari bulunamadı</option>
                    @endforelse
                </x-v65.select>
                <x-v65.input-error field="contactId" />
            </div>
            <label class="field">{{ $isGoodsReceipt ? 'Kabul Tarihi' : $screen['date'] }} <input type="date" wire:model="documentDate"><x-v65.input-error field="documentDate" /></label>
            @if(!$isGoodsReceipt)
                <label class="field v65-reference-currency">Para Birimi
                    <input wire:model.blur="currency" maxlength="3" list="v65-reference-currency-options" autocomplete="off" aria-label="Para birimi">
                    <datalist id="v65-reference-currency-options">
                        <option value="TRY"></option><option value="USD"></option><option value="EUR"></option><option value="GBP"></option>
                    </datalist>
                    <x-v65.input-error field="currency" />
                </label>
            @else
                <label class="field">Para Birimi <input readonly value="{{ $currency }}" title="Mal kabulde kaynak belge para birimi"></label>
            @endif

            <label class="field">Varsayılan Depo <select wire:model.live="uiMeta.default_location_id">
                <option value="">Satır bazında seç</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select>
            </label>
            @if($this instanceof \App\Livewire\Purchases\PurchaseQuoteEditor)
                <label class="field">Teklif Geçerlilik <input type="date" wire:model="uiMeta.quote_valid_until"></label>
                <label class="field">Teslim Yeri <select wire:model="uiMeta.delivery_location_id"><option value="">Seçin</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
                <label class="field">Tedarikçi Teklif No <input wire:model.blur="uiMeta.supplier_quote_no" maxlength="140"></label>
            @elseif($this instanceof \App\Livewire\Purchases\PurchaseOrderEditor)
                <label class="field">Beklenen Teslim <input type="date" wire:model="uiMeta.expected_delivery"></label>
                <label class="field">Kabul Deposu <select wire:model="uiMeta.receipt_location_id"><option value="">Satır bazında seç</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
                <label class="field">Tedarikçi Teklif No <input wire:model.blur="uiMeta.supplier_quote_no" maxlength="140"></label>
            @elseif($this instanceof \App\Livewire\Purchases\PurchaseDispatchEditor)
                <div class="field v65-reference-source-field">
                    <strong>Kaynak Satınalma Siparişi</strong>
                    @if($document && $document->incomingRelations->isNotEmpty())
                        <x-v65.document-sources :document="$document" />
                    @else
                        <span>Onaylı satınalma siparişinden oluşturulur</span>
                    @endif
                </div>
                <label class="field">Planlanan Kabul Deposu <select wire:model="uiMeta.receipt_location_id"><option value="">Satır bazında seç</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
                <label class="field">Tedarikçi İrsaliye No <input wire:model.blur="uiMeta.supplier_dispatch_no" maxlength="140"></label>
            @elseif($this instanceof \App\Livewire\Purchases\GoodsReceiptEditor)
                <div class="field v65-reference-source-field">
                    <strong>Kaynak Sipariş</strong>
                    @if($document && $document->incomingRelations->isNotEmpty())
                        <x-v65.document-sources :document="$document" />
                    @else
                        <span>Kaynak sipariş/irsaliye üzerinden oluşturulur</span>
                    @endif
                </div>
                <label class="field">Kabul Deposu <select wire:model="uiMeta.receipt_location_id"><option value="">Satır bazında seç</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
                <label class="field">İrsaliye No <input wire:model.blur="uiMeta.goods_receipt_reference" maxlength="140"></label>
            @elseif($this instanceof \App\Livewire\Purchases\SupplierInvoiceEditor)
                <label class="field">Tedarikçi Fatura No <input wire:model.blur="uiMeta.supplier_invoice_no" maxlength="140"></label>
                <label class="field">Vade Tarihi <input type="date" wire:model="dueDate"><x-v65.input-error field="dueDate" /></label>
                <div class="field v65-reference-source-field">
                    <strong>Kaynak Mal Kabul</strong>
                    @if($document && $document->incomingRelations->isNotEmpty())
                        <x-v65.document-sources :document="$document" />
                    @else
                        <span>Kesinleşmiş mal kabul üzerinden ilişkilendirilir</span>
                    @endif
                </div>
            @endif
            @if($isGoodsReceipt)
                <label class="field v65-doc-notes">Not <textarea wire:model="notes" rows="3"></textarea></label>
            @else
                <details class="v65-reference-more-options">
                    <summary>Vade, kur, belge indirimi ve notlar</summary>
                    <div class="v65-reference-more-fields">
                        @unless($this instanceof \App\Livewire\Purchases\SupplierInvoiceEditor)
                            <label class="field">Vade <input type="date" wire:model="dueDate"><x-v65.input-error field="dueDate" /></label>
                        @endunless
                        <label class="field">Kur <input wire:model.blur="exchangeRate" inputmode="decimal" data-tr-decimal><x-v65.input-error field="exchangeRate" /></label>
                        <label class="field">Belge İskonto % <input wire:model.blur="discountRate" inputmode="decimal" data-tr-decimal><x-v65.input-error field="discountRate" /></label>
                        @if($this instanceof \App\Livewire\Purchases\SupplierInvoiceEditor)
                            <label class="field">Ödeme Şekli <select wire:model="uiMeta.payment_method"><option value="">Seçin</option>
                                <option value="Peşin">Peşin</option><option value="Vadeli">Vadeli</option><option value="Banka">Banka</option></select></label>
                        @endif
                        <label class="field v65-doc-notes">Not <textarea wire:model="notes" rows="3"></textarea></label>
                    </div>
                </details>
            @endif
                    </div>
                    @unless($isGoodsReceipt)
                    <div class="v65-reference-document-sources">
                        @if($document && $document->incomingRelations->isNotEmpty())
                            <x-v65.document-sources :document="$document" />
                        @endif
                        @if($this instanceof \App\Livewire\Purchases\PurchaseDispatchEditor)
                            <small>Kaynak Satınalma Siparişi, onaylı siparişten irsaliye oluşturulurken bağlanır.</small>
                        @elseif($this instanceof \App\Livewire\Purchases\SupplierInvoiceEditor)
                            <small>Kaynak Mal Kabul, mal kabulden fatura oluşturulurken bağlanır.</small>
                        @endif
                    </div>
                    <x-v65.document-prototype-stage :document="$document" :screen-type="$screenType" />
                    @endunless
                </section>



            <section class="v65-doc-lines v65-reference-products-card {{ $isGoodsReceipt ? 'v65-goods-receipt-lines' : '' }}" aria-label="Belge kalemleri">
                @if($isGoodsReceipt)
                    <div class="v65-doc-lines-head">
                        <strong>Mal Kabul Kalemleri</strong>
                        <small>Ürün satırları bağlı kaynak belgeye göre oluşturulur.</small>
                    </div>
                @else
                    <x-v65.document-prototype-toolbar :screen-type="$screenType"
                        :products="$this->quickProductOptions()" :quick-search="$quickProductSearch" />
                    <details class="v65-reference-advanced">
                        <summary>Özel KDV / Gelişmiş Vergi İşlemleri</summary>
            @if(!$isGoodsReceipt)
                <div class="v65-doc-vat-toolbar" role="group" aria-label="Satırlara toplu KDV uygula">
                    <label class="field">Toplu KDV % <input wire:model="bulkVatRate" inputmode="decimal" data-tr-decimal aria-invalid="{{ $errors->has('bulkVatRate') ? 'true' : 'false' }}"><x-v65.input-error field="bulkVatRate" /></label>
                    <x-v65.button variant="secondary" size="sm" wire:click="applyVatToAll">Tüm Satırlara Uygula</x-v65.button>
                    <x-v65.button variant="secondary" size="sm" wire:click="clearVat">KDV'yi Temizle</x-v65.button>
                </div>
            @endif
                    </details>
                @endif
                <small class="v65-doc-mobile-scroll-hint">Tablodaki diğer satır alanlarını görmek için yatay kaydırın.</small>
                <div class="table-scroll document-lines v65-doc-table-scroll"><table class="data-table v65-doc-table v65-prototype-lines">
                    <thead><tr>
                        <th>#</th><th>Ürün Kodu / Ürün Adı</th><th class="align-end">Miktar</th>
                        <th>Birim</th><th class="align-end">{{ $isGoodsReceipt ? 'Alış Fiyatı' : $screen['price'] }}</th><th class="align-end">İsk. %</th><th class="align-end">KDV %</th>
                        <th class="align-end">Net Tutar</th><th class="align-end">KDV Dahil*</th><th>İşlem</th>
                    </tr></thead>
                    <tbody>
                    @foreach($lines as $index => $line)
                        @php
                            $service = ($line['line_kind'] ?? 'stock') === 'service';
                            $rowKey = (string) ($line['row_key'] ?? $index);
                            $isEntryRow = !$isGoodsReceipt && $index === count($lines) - 1 && $this->isPrototypeEntryRow($line);
                            $productChoices = $isGoodsReceipt ? collect() : $this->productOptionsForLine($rowKey, (int) ($line['product_id'] ?? 0));
                            $selectedProduct = $productChoices->firstWhere('id', (int) ($line['product_id'] ?? 0));
                            $previewLine = $draftPreview?->lines[$index] ?? null;
                            $lineNet = $previewLine?->lineTotal ?? (!$documentDirty ? ($line['line_total'] ?? null) : null);
                            $lineVat = $lineNet !== null && is_numeric((string) ($line['vat_rate'] ?? 0))
                                ? \App\Support\Money\Money::of((string) $lineNet)->percent((string) ($line['vat_rate'] ?? 0))->amount : null;
                            $lineWithVat = $lineVat !== null ? \App\Support\Money\Money::of((string) $lineNet)->plus(\App\Support\Money\Money::of($lineVat))->amount : null;
                        @endphp
                        @if($isEntryRow)
                            <tr wire:key="purchase-entry-{{ $rowKey }}" class="v65-reference-entry-row">
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
                        <tr wire:key="purchase-line-{{ $line['row_key'] ?? $index }}">
                            <td>{{ $index + 1 }}</td>
                            <td class="v65-doc-product-combined">
                                <div class="v65-doc-product-cell">
                                @if($isGoodsReceipt)<span class="v65-doc-muted">Kaynak satır</span>@elseif($service)<span class="v65-doc-muted">HİZMET</span>
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
                                @if($isGoodsReceipt)<strong>{{ $line['description'] ?? 'Kaynak satır' }}</strong>
                                @elseif($service)
                                    <input wire:model.blur="lines.{{ $index }}.description" placeholder="Hizmet açıklaması">
                                    <x-v65.input-error :field="'lines.'.$index.'.description'"/>
                                @else
                                    <strong>{{ $line['description'] ?? 'Ürün seçin' }}</strong>
                                    @if($selectedProduct)<small class="v65-reference-sku">{{ $selectedProduct->code }}</small>@endif
                                @endif
                                @unless($isGoodsReceipt)
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
                                @endunless
                            </div>
                            </td>
                            <td class="align-end">
                                <input data-v65-fast-quantity wire:model.blur="lines.{{ $index }}.quantity" inputmode="decimal" data-tr-decimal aria-label="Satır {{ $index + 1 }} miktar">
                                <x-v65.input-error :field="'lines.'.$index.'.quantity'"/>
                            </td>
                            <td>
                                @if($isGoodsReceipt)<span>{{ $units->firstWhere('id', (int) ($line['unit_id'] ?? 0))?->code ?? '—' }}</span>@elseif($service)<span>—</span>@else
                                    <select wire:model="lines.{{ $index }}.unit_id" aria-label="Satır {{ $index + 1 }} birim">
                                        <option value="">Birim</option>@foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->code }}</option>@endforeach
                                    </select>
                                @endif
                            </td>
                            <td class="align-end">@if($isGoodsReceipt){{ \App\Support\Formatting\TrFormatter::money((string) ($line['unit_price'] ?? '0')) }}@else<input wire:model.blur="lines.{{ $index }}.unit_price" inputmode="decimal" data-tr-decimal aria-invalid="{{ $errors->has('lines.'.$index.'.unit_price') ? 'true' : 'false' }}" aria-label="Satır fiyatı">@endif</td>
                            <td class="align-end">@if($isGoodsReceipt){{ $line['line_discount_rate'] ?? '0' }}@else<input wire:model.blur="lines.{{ $index }}.line_discount_rate" inputmode="decimal" data-tr-decimal aria-invalid="{{ $errors->has('lines.'.$index.'.line_discount_rate') ? 'true' : 'false' }}" aria-label="Satır iskonto oranı">@endif</td>
                            <td class="align-end">@if($isGoodsReceipt){{ $line['vat_rate'] ?? '0' }}@else<input wire:model.blur="lines.{{ $index }}.vat_rate" inputmode="decimal" data-tr-decimal aria-invalid="{{ $errors->has('lines.'.$index.'.vat_rate') ? 'true' : 'false' }}" aria-label="Satır KDV oranı">@endif</td>
                            <td class="align-end v65-doc-line-net">{{ $lineNet !== null ? \App\Support\Formatting\TrFormatter::money((string) $lineNet) : '—' }}</td>
                            <td class="align-end v65-doc-line-incl">{{ $lineWithVat !== null ? \App\Support\Formatting\TrFormatter::money($lineWithVat) : '—' }}</td>
                            <td>@unless($isGoodsReceipt)<x-v65.button size="xs" variant="danger" wire:click="removeLine({{ $index }})" aria-label="Satır sil">Sil</x-v65.button>@else<span>Kaynak Satır</span>@endunless</td>
                        </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table></div>
                @unless($isGoodsReceipt)
                    <div class="v65-reference-new-line">
                        <x-v65.button variant="secondary" size="sm" wire:click="addLine">+ Yeni ürün satırı</x-v65.button>
                        <small>Aşağıdaki boş satıra da ürün kodu veya barkod yazabilirsiniz. Enter ürün seçer, Tab sonraki alana geçer.</small>
                    </div>
                    <div class="v65-reference-totals-layout">
                        <div class="v65-reference-totals-help">
                            <strong>Hızlı giriş:</strong> Ürün kodunu aratıp Enter ile ekleyin. Sonraki boş satır otomatik hazırlanır.
                            <p>Depo ve satır notunu detaydan açabilirsiniz. Ek iskonto KDV hariç net tutardan düşülür;
                            geçerli matrah ve toplam KDV sunucuda hesaplanır.</p>
                            <small>* Satır KDV dahil tutarı belge genel iskontoları öncesi bilgi amaçlıdır.</small>
                        </div>
                        <x-v65.document-summary class="v65-reference-inline-summary" :compact="true" :editable="true"
                            :document="$document" :preview="$draftPreview" :additional-discount="$additionalDiscountAmount" :currency="$currency" />
                    </div>
                @else
                    <small class="v65-doc-line-tax-note">Mal Kabul miktarları kaynak belgeye göre işlenir. Stok fiili kabulde güncellenir.</small>
                @endunless
            </section>

            @if($isGoodsReceipt)
                <x-v65.form-actions align="between" class="v65-doc-form-actions">
                    <x-v65.button type="submit" wire:loading.attr="disabled" wire:target="save">Mal Kabul Taslağını Kaydet</x-v65.button>
                </x-v65.form-actions>
            @else
                <x-v65.document-prototype-footer :document="$document" :screen-type="$screenType" :item-count="$lineCount" />
            @endif
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

    @if($isGoodsReceipt || ($document && $document->status !== 'draft'))
        <x-v65.document-summary :editable="$isGoodsReceipt && (!$document || $document->status === 'draft')"
            :additional-discount="$additionalDiscountAmount" class="v65-doc-screen-summary"
            :document="$document" :preview="$draftPreview" :currency="$currency" />
    @endif

    @if($isGoodsReceipt && $document && $document->status === 'posted')
        <x-v65.notice tone="info">
            Mal Kabul kesinleştiği için stok hareketleri doğrudan düzenlenemez.
            Önce varsa bağlı alış faturalarını kontrol edin, sonra Ters Kayıt ile
            düzeltin. Yeni Mal Kabul, kaynak satınalma siparişinden veya alış irsaliyesinden oluşturulur.
        </x-v65.notice>
        <x-v65.document-sources :document="$document" />
    @endif

    @if($document)
        <section id="v65-reference-workflow" class="document-actions panel v65-document-actions v65-doc-workflow" role="group" aria-label="Belge işlemleri">
            <h3 class="v65-doc-workflow-title">Belge İşlemleri</h3>
            @if($this instanceof \App\Livewire\Purchases\PurchaseQuoteEditor && $document->status === 'draft')
                @can('purchase_quotes.approve')
                    <x-v65.document-confirm id="v65-purchase-quote-approve" title="Alış Teklifini Onayla" label="Teklifi Onayla" action="approveQuote" :document="$document" message="Teklif onaylanacak; stok veya cari hareket oluşmayacaktır." :dirty="$documentDirty" />
                @endcan
            @endif
            @if($this instanceof \App\Livewire\Purchases\PurchaseQuoteEditor && $document->status === 'approved')
                @can('purchase_quotes.update')
                    @can('purchase_orders.create')
                        <x-v65.document-confirm id="v65-purchase-quote-convert" title="Alış Siparişi Oluştur" label="Siparişe Dönüştür" action="convertToOrder" :document="$document" message="Teklif kalemleri ve iskontolarıyla taslak alış siparişi hazırlanacaktır." :dirty="$documentDirty" />
                    @endcan
                @endcan
            @endif
            @if($this instanceof \App\Livewire\Purchases\PurchaseDispatchEditor && $document->status === 'draft')
                @can('purchase_dispatches.update')
                    <x-v65.document-confirm id="v65-purchase-dispatch-finalize" title="Alış İrsaliyesini Onayla" label="İrsaliyeyi Onayla" action="confirmDispatch" :document="$document" message="Stok ve cari hareketi oluşturulmayacak. Stok ancak Mal Kabul kesinleştirilince artar." :dirty="$documentDirty" />
                @endcan
            @endif
            @if($this instanceof \App\Livewire\Purchases\PurchaseDispatchEditor && $document->status === 'confirmed')
                @can('goods_receipts.create')
                <label class="field">Mal Kabul Tarihi <input type="date" wire:model="receiptDate"></label>
                @foreach($document->lines as $line)
                    <div>
                        <strong>{{ $line->line_no }} · {{ $line->description }}</strong>
                        <input wire:model="receiptQuantities.{{ $line->id }}" aria-label="Mal kabul miktarı">
                        @if($line->line_kind === 'stock')
                            <select wire:model="receiptLocationIds.{{ $line->id }}">
                                <option value="">Depo / Lokasyon</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                @endforeach
                <x-v65.button wire:click="createReceipt" wire:loading.attr="disabled" wire:target="createReceipt">İrsaliyeden Mal Kabul Oluştur</x-v65.button>
                @endcan
            @endif
            @if(method_exists($this, 'submitApproval') && $document->status === 'draft')
                <x-v65.document-confirm id="v65-purchase-submit" title="Onaya Gönder" label="Onaya Gönder" action="submitApproval" variant="secondary" :document="$document" message="Son kaydedilen alış siparişi onay sürecine gönderilecektir." :dirty="$documentDirty" />
            @endif

            @if(method_exists($this, 'approve') && $document->status === 'pending_approval')
                @can('purchase_orders.approve')
                    <x-v65.document-confirm id="v65-purchase-approve" title="Alış Siparişi Onayı" label="Onayla" action="approve" :document="$document" message="Alış siparişi son kaydedilen haliyle onaylanacaktır." :dirty="$documentDirty" />
                @endcan
            @endif

            @if(method_exists($this, 'sendToSupplier') && $document->status === 'approved')
                <x-v65.button variant="secondary" wire:click="sendToSupplier" wire:loading.attr="disabled" wire:target="sendToSupplier">Tedarikçiye Gönder</x-v65.button>
            @endif

            @if(method_exists($this, 'createPurchaseDispatch') && in_array($document->status, ['approved', 'sent']))
                @can('purchase_dispatches.create')
                    <x-v65.button variant="secondary" wire:click="createPurchaseDispatch" wire:loading.attr="disabled" wire:target="createPurchaseDispatch">Alış İrsaliyesi Oluştur</x-v65.button>
                @endcan
            @endif
            @if(method_exists($this, 'createGoodsReceipt') && in_array($document->status, ['approved', 'sent']))
                <label class="field">Mal Kabul Tarihi <input type="date" wire:model="receiptDate"></label>
                @foreach($document->lines as $line)
                    <div>
                        <strong>{{ $line->line_no }} · {{ $line->description }}</strong>
                        <input wire:model="receiptQuantities.{{ $line->id }}" aria-label="Mal kabul miktarı">
                        @if($line->line_kind === 'stock')
                            <select wire:model="receiptLocationIds.{{ $line->id }}">
                                <option value="">Depo / Lokasyon</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                @endforeach
                <x-v65.button wire:click="createGoodsReceipt" wire:loading.attr="disabled" wire:target="createGoodsReceipt">Mal Kabul Oluştur</x-v65.button>
            @endif

            @if(method_exists($this, 'post') && $document->status === 'draft')
                @if(property_exists($this, 'deviationAccepted'))
                    <label>
                        <input type="checkbox" wire:model="deviationAccepted">
                        ±%25 alış fiyat sapması varsa uyarıyı kabul ediyorum
                    </label>
                @endif
                <x-v65.document-confirm id="v65-purchase-post" title="Belgeyi Kesinleştir" label="Kesinleştir" action="post" :document="$document" message="Son kaydedilen alış belgesi sunucuda doğrulanıp kesinleştirilecektir. Fiyat sapması kabulünüz gerektiğinde işlem sırasında kontrol edilir." :dirty="$documentDirty" />
            @endif

            @if(method_exists($this, 'createInvoice') && $document->status === 'posted')
                <label class="field">Fatura Tarihi <input type="date" wire:model="invoiceDate"></label>
                <label class="field">Para Birimi <input wire:model="invoiceCurrency" maxlength="3"></label>
                <label class="field">Kur <input wire:model="invoiceExchangeRate" data-tr-decimal inputmode="decimal"></label>
                @foreach($document->lines as $line)
                    <div>
                        <strong>{{ $line->line_no }} · {{ $line->description }}</strong>
                        <input wire:model="invoiceQuantities.{{ $line->id }}" aria-label="Fatura miktarı">
                    </div>
                @endforeach
                <x-v65.button wire:click="createInvoice" wire:loading.attr="disabled" wire:target="createInvoice">Alış Faturası Oluştur</x-v65.button>
            @endif

            @if(method_exists($this, 'reverse') && $document->status === 'posted')
                <label class="field"><span class="field-label">Ters Kayıt Tarihi</span><input type="date" wire:model="reversalDate"></label>
                <label class="field"><span class="field-label">Ters Kayıt Gerekçesi</span><input wire:model="reversalReason"></label>
                <x-v65.document-confirm id="v65-purchase-reverse" title="Alış Ters Kayıt Onayı" label="Ters Kayıt" action="reverse" variant="danger" :document="$document" message="Kesinleşmiş alış belgesine ters kayıt oluşturulur. Tarih ve gerekçeyi kontrol edin." :dirty="$documentDirty" />
            @endif

            @if(isset($purchaseMatches) && $purchaseMatches->isNotEmpty())
                <h2>Üçlü Eşleştirme</h2>
                <div class="table-scroll v65-doc-matching-table"><table class="data-table">
                    <thead>
                    <tr>
                        <th>Fatura Satırı</th>
                        <th>Sipariş Fiyatı</th>
                        <th>Fatura Fiyatı</th>
                        <th>Fiyat Sapması %</th>
                        <th>TRY Maliyet</th>
                        <th>Yeni Ort.</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($purchaseMatches as $match)
                        <tr>
                            <td>{{ $match->supplier_invoice_line_id }}</td>
                            <td>{{ $match->order_unit_price }}</td>
                            <td>{{ $match->invoice_unit_price }}</td>
                            <td>{{ $match->price_variance_rate }}</td>
                            <td>{{ $match->cost_unit_try ?? '—' }}</td>
                            <td>{{ $match->new_moving_average ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </section>
    @endif
</div>
