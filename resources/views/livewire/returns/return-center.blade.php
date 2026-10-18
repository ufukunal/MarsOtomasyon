@php
    $isSalesReturn = $type === \App\Enums\DocumentType::SalesReturn->value;
    $returnTitle = $isSalesReturn ? 'Satış İadesi' : 'Alış İadesi';
    $returnPrice = $isSalesReturn ? 'Satış Fiyatı' : 'Alış Fiyatı';
    $editableLines = ! $selectedReturn || ($selectedReturn->status === 'draft' && $editingReturnDraft);
    $money = \App\Support\Formatting\TrFormatter::class;
@endphp
<div class="v65-reference-workspace v65-ref-returns" x-data="{ productFilter: '' }">
    <header class="v65-reference-heading">
        <div class="v65-reference-heading-main">
            <h1>{{ $returnTitle }}</h1>
            <p>{{ $isSalesReturn ? 'Müşteriden gelen ürünleri kaynak fatura üzerinden iade alın.' : 'Tedarikçiye iade edilecek ürünleri kaynak alış faturasından seçin.' }}</p>
        </div>
        <div class="v65-reference-heading-actions">
            <span class="v65-reference-status">{{ $selectedReturn?->number ?? ($selectedReturn ? 'Taslak #'.$selectedReturn->id : 'Yeni İade') }}</span>
            @can('returns.create')
                <x-v65.button variant="secondary" size="sm" wire:click="startNewReturn">+ Yeni İade</x-v65.button>
            @endcan
        </div>
    </header>

    <nav class="v65-ref-returns-tabs" aria-label="İade türü">
        <a href="{{ route('returns.sales') }}" class="{{ $isSalesReturn ? 'is-active' : '' }}"
            aria-current="{{ $isSalesReturn ? 'page' : 'false' }}">Satış İadesi</a>
        <a href="{{ route('returns.purchases') }}" class="{{ ! $isSalesReturn ? 'is-active' : '' }}"
            aria-current="{{ ! $isSalesReturn ? 'page' : 'false' }}">Alış İadesi</a>
    </nav>

    @if($errors->any())
        <x-v65.notice tone="danger" title="İade kontrolü" data-v65-document-errors tabindex="-1">
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </x-v65.notice>
    @endif
    @if($editingReturnDraft)
        <x-v65.notice tone="info">İade taslağı düzenleniyor. Kaynak fatura ve cari sabittir; iade miktarı, lokasyon, tarih ve açıklama düzenlenebilir.</x-v65.notice>
    @endif

    <section class="v65-reference-info-card" aria-label="İade belge bilgileri">
        <header class="v65-reference-info-head">
            <strong>Belge Bilgileri</strong>
            <small>{{ $isSalesReturn ? 'Müşteri iadesi' : 'Tedarikçiye iade' }} · Kaynak fatura zorunlu</small>
        </header>
        <div class="v65-ref-returns-info-grid">
            <label class="field v65-ref-returns-source">Kaynak {{ $isSalesReturn ? 'Satış' : 'Alış' }} Faturası *
                <select wire:model.live="sourceInvoiceId" @disabled($selectedReturn !== null)>
                    <option value="">Kaynak fatura seçin</option>
                    @foreach($sourceInvoices as $invoice)
                        <option value="{{ $invoice->id }}">
                            {{ $invoice->number ?? '#'.$invoice->id }}
                            @if(auth()->user()?->can('contacts.view')) · {{ $invoice->contact?->title }} @endif
                            · {{ $money::money((string) $invoice->grand_total) }} {{ $invoice->currency }}
                        </option>
                    @endforeach
                </select>
                <small>Yalnız geçerli, kesinleşmiş ve terslenmemiş faturalar listelenir.</small>
            </label>
            <label class="field">İade Tarihi *
                <input type="date" wire:model="documentDate" @disabled($selectedReturn && ! $editingReturnDraft)>
            </label>
            <label class="field">Cari Hesap
                <input type="text" readonly value="{{ $sourceInvoice && auth()->user()?->can('contacts.view') ? ($sourceInvoice->contact?->title ?? '—') : ($sourceInvoice ? 'Görüntüleme yetkisi yok' : 'Fatura seçin') }}">
            </label>
            <label class="field">Para Birimi
                <input type="text" readonly value="{{ $sourceInvoice?->currency ?? 'TRY' }}">
            </label>
            <label class="field">İade No
                <input type="text" readonly value="{{ $selectedReturn?->number ?? ($selectedReturn ? 'Taslak #'.$selectedReturn->id : 'Kaydedilince atanır') }}">
            </label>
            <label class="field v65-ref-returns-note">Açıklama / İade Nedeni
                <textarea wire:model.blur="note" rows="2" maxlength="3000"
                    placeholder="İade gerekçesi veya referans" @disabled($selectedReturn && ! $editingReturnDraft)></textarea>
            </label>
        </div>
    </section>

    <section class="v65-reference-products-card v65-ref-returns-products" aria-label="İade ürün ve hizmet satırları">
        <header class="v65-reference-products-bar">
            <div class="v65-reference-products-title">
                <div>
                    <h2>Ürün ve Hizmetler</h2>
                    <small>Fatura satırlarından iade miktarı seçilir. Birim fiyat, iskonto ve KDV kaynak faturaya bağlıdır.</small>
                </div>
                <span class="v65-reference-fast-indicator v65-reference-source-indicator">Kaynak faturadan seçim</span>
            </div>
            <div class="v65-reference-products-tools">
                <div class="v65-reference-quick-product">
                    <label for="return-product-filter" class="sr-only">İade ürünlerini filtrele</label>
                    <input id="return-product-filter" type="search" x-model="productFilter"
                        placeholder="Ürün kodu, barkod veya ürün adını arayın..." autocomplete="off"
                        aria-label="Kaynak fatura ürünlerinde ara" @disabled(!$sourceInvoice)>
                </div>
                <span class="v65-ref-returns-source-tip">Hizmet iadeleri yalnız kaynak faturadaki hizmet satırlarından seçilir</span>
                <div class="v65-reference-vat-select"><label for="return-vat-source">Toplu KDV:</label>
                    <select id="return-vat-source" disabled aria-label="İade KDV oranı kaynak faturadan alınır">
                        <option>Kaynaktan sabit</option>
                    </select>
                </div>
            </div>
        </header>

        @if($sourceInvoice)
            @if($returnPreview['error'])
                <x-v65.notice tone="warning">{{ $returnPreview['error'] }}</x-v65.notice>
            @endif
            <small class="v65-doc-mobile-scroll-hint">Tüm alanları görmek için tabloyu yatay kaydırın.</small>
            <div class="table-scroll document-lines v65-doc-table-scroll">
                <table class="data-table v65-doc-table v65-prototype-lines">
                    <x-v65.document-product-table-head :price-label="$returnPrice" />
                    <tbody>
                        @foreach($sourceInvoice->lines as $index => $line)
                            @php
                                $lineId = (int) $line->id;
                                $selected = (string) ($quantities[$lineId] ?? '0');
                                $amounts = $returnPreview['rows'][$lineId] ?? null;
                                $remaining = $returnPreview['remaining'][$lineId] ?? '0.000';
                                $lineCode = $line->product?->code ?? ($line->line_kind === 'service' ? 'HİZMET' : '');
                            @endphp
                            <tr wire:key="return-source-{{ $lineId }}"
                                data-return-search="{{ mb_strtolower($lineCode.' '.($line->description ?? ''), 'UTF-8') }}"
                                x-show="!productFilter || $el.dataset.returnSearch.includes(productFilter.toLocaleLowerCase('tr').trim())">
                                <td>{{ $index + 1 }}</td>
                                <td class="v65-ref-returns-product">
                                    <strong>{{ $line->description ?? 'Ürün / Hizmet' }}</strong>
                                    <small>{{ $lineCode }} · Kalan {{ $remaining }} {{ $line->unit?->code ?? '' }}</small>
                                </td>
                                <td class="align-end">
                                    <input type="text" inputmode="decimal" data-tr-decimal
                                        wire:model.live.debounce.350ms="quantities.{{ $lineId }}"
                                        aria-label="{{ $index + 1 }}. satır iade miktarı" @disabled(! $editableLines)>
                                </td>
                                <td>{{ $line->unit?->code ?? '—' }}</td>
                                <td class="align-end">{{ $money::money((string) $line->unit_price) }}</td>
                                <td class="align-end">{{ $money::money((string) $line->line_discount_rate) }}</td>
                                <td class="align-end">{{ $money::money((string) $line->vat_rate) }}</td>
                                <td class="align-end v65-doc-line-net">{{ $amounts ? $money::money($amounts['net']) : '—' }}</td>
                                <td class="align-end v65-doc-line-incl">{{ $amounts ? $money::money($amounts['with_vat']) : '—' }}</td>
                                <td class="v65-reference-actions-cell">
                                    @if($editableLines)
                                        <div class="v65-reference-line-actions">
                                            @if($line->line_kind === 'stock')
                                                <button type="button" class="v65-reference-line-menu-button"
                                                    data-v65-modal-open="v65-return-location-{{ $lineId }}"
                                                    title="İade lokasyonu" aria-label="İade lokasyonu seç">⌄</button>
                                            @endif
                                            <button type="button" class="v65-reference-line-delete"
                                                wire:click="clearReturnLine({{ $lineId }})"
                                                wire:loading.attr="disabled" wire:target="clearReturnLine"
                                                title="İadeden çıkar" aria-label="Satırı iadeden çıkar">×</button>
                                        </div>
                                        @if($line->line_kind === 'stock')
                                            <x-v65.modal :id="'v65-return-location-'.$lineId" title="İade Lokasyonu" size="sm">
                                                <label class="field">{{ $isSalesReturn ? 'Kabul / Karantina Lokasyonu' : 'Çıkış Lokasyonu' }}
                                                    <select wire:model="locationIds.{{ $lineId }}">
                                                        <option value="">Lokasyon seçin</option>
                                                        @foreach($locations as $location)
                                                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </label>
                                                <x-slot:footer>
                                                    <x-v65.button variant="secondary" data-v65-modal-close>Kapat</x-v65.button>
                                                </x-slot:footer>
                                            </x-v65.modal>
                                        @endif
                                    @else
                                        <span class="v65-reference-source-label">Kaynak Satır</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="v65-reference-new-line">
                <span class="v65-ref-returns-select-note">+ İade edilecek ürünleri kaynak faturadan seçin; miktarı <b>0</b> olan satır işleme alınmaz.</span>
                <small>Enter ve Tab ile miktar alanlarında ilerleyebilirsiniz.</small>
            </div>
            <div class="v65-reference-totals-layout">
                <div class="v65-reference-totals-help">
                    <strong>{{ $returnPreview['count'] }} satır iade için seçildi.</strong>
                    <p>Fiyat, iskonto ve KDV kaynak faturadaki sabit değerlerden hesaplanır. Belgede ek iskonto varsa seçilen miktara orantılı dağıtılır.</p>
                    <small>* Satır KDV dahil tutarı belge düzeyi indirimlerden önce gösterilir; toplamlar sunucu hesap motorundan gelir.</small>
                    <small>{{ $isSalesReturn ? 'Satış iadesi stok girişini karantinaya alır; kullanılabilir stok kendiliğinden artmaz.' : 'Alış iadesi stok çıkışı ve tedarikçi cari borç hareketi oluşturur.' }}</small>
                </div>
                <x-v65.document-summary class="v65-reference-inline-summary" :compact="true"
                    :document="$selectedReturn" :preview="$editableLines ? $returnPreview['totals'] : null"
                    :currency="$sourceInvoice->currency" :additional-discount="$returnPreview['additional']" />
            </div>
        @else
            <div class="v65-ref-returns-empty">
                <strong>İade satırlarını görmek için kaynak fatura seçin.</strong>
                <p>Kaynak faturanın ürünleri, fiyatları ve kalan iade miktarları burada gösterilir.</p>
            </div>
        @endif
    </section>

    <footer class="v65-reference-document-footer v65-ref-returns-footer">
        <p><span class="v65-reference-count-dot"></span>
            {{ $returnPreview['count'] }} iade satırı · Sunucu hesaplaması · Kaynak fatura bağlantısı
        </p>
        <div class="v65-reference-footer-actions">
            @if($editingReturnDraft)
                <x-v65.button variant="secondary" size="sm" wire:click="selectReturn({{ $selectedReturnId }})">Vazgeç</x-v65.button>
                @can('returns.update')
                    <x-v65.button size="sm" wire:click="saveDraft"
                        wire:loading.attr="disabled" wire:target="saveDraft">Değişiklikleri Kaydet</x-v65.button>
                @endcan
            @elseif(! $selectedReturn)
                @can('returns.create')
                    <x-v65.button variant="secondary" size="sm" wire:click="startNewReturn">Formu Temizle</x-v65.button>
                    <x-v65.button size="sm" wire:click="create"
                        wire:loading.attr="disabled" wire:target="create"
                        :disabled="! $sourceInvoice || ! $returnPreview['count'] || $returnPreview['error'] !== null">İade Taslağını Kaydet →</x-v65.button>
                @endcan
            @elseif($selectedReturn->status === 'draft')
                @can('returns.update')
                    <x-v65.button variant="secondary" size="sm" wire:click="editDraft({{ $selectedReturn->id }})">Düzenle</x-v65.button>
                    <x-v65.button size="sm" wire:click="post"
                        wire:loading.attr="disabled" wire:target="post"
                        >Kaydet ve İşle</x-v65.button>
                @endcan
            @else
                <x-v65.button variant="secondary" size="sm" :href="route('documents.history', ['id' => $selectedReturn->id])">Kayıt Geçmişi</x-v65.button>
            @endif
        </div>
    </footer>

    @if($selectedReturn)
        <section class="v65-ref-returns-selected" aria-label="Seçili iade" tabindex="-1" x-init="$nextTick(() => $el.scrollIntoView({ block: 'start', behavior: 'smooth' }))">
            <strong>{{ $selectedReturn->number ?? 'Taslak #'.$selectedReturn->id }}</strong>
            <span>{{ \App\Support\Formatting\BusinessLabelFormatter::status($selectedReturn->status) }}</span>
            <span>{{ $money::money((string) $selectedReturn->grand_total) }} {{ $selectedReturn->currency }}</span>
            @if($selectedReturn->document_type === \App\Enums\DocumentType::SalesReturn && $selectedReturn->status === 'posted')
                <small>Stok karantina kontrolü ayrıca yapılır.</small>
            @endif
        </section>
        @if($selectedReturn->status === 'posted' && auth()->user()?->can('returns.cancel'))
            <x-v65.record-delete-footer>
                <x-v65.button variant="danger" size="sm" wire:click="requestRecordDeletion('deleteReturn', [{{ $selectedReturn->id }}])"
                    wire:loading.attr="disabled" wire:target="requestRecordDeletion">Sil</x-v65.button>
            </x-v65.record-delete-footer>
        @endif
    @endif

    <section class="v65-ref-returns-history" aria-label="İade geçmişi">
        <x-v65.list-heading title="İade Geçmişi" />
        <div class="table-scroll v65-list-table"><table class="data-table">
            <thead><tr><th>No</th><th>Tür</th><th>Cari</th><th>Tarih</th><th class="align-end">Tutar</th><th>Durum</th><th>İşlem</th></tr></thead>
            <tbody>
            @forelse($returns as $return)
                <tr>
                    <td><button type="button" class="link-button v65-record-link" wire:click="selectReturn({{ $return->id }})">{{ $return->number ?? 'Taslak #'.$return->id }}</button></td>
                    <td>{{ $return->document_type === \App\Enums\DocumentType::SalesReturn ? 'Satış' : 'Alış' }}</td>
                    <td>@if(auth()->user()?->can('contacts.view') && $return->contact)
                            <a href="{{ route('contacts.show', ['contact' => $return->contact_id]) }}">{{ $return->contact->title }}</a>
                        @else Görüntüleme yetkisi yok @endif</td>
                    <td>{{ $return->document_date?->format('d.m.Y') }}</td>
                    <td class="align-end">{{ $money::money((string) $return->grand_total) }} {{ $return->currency }}</td>
                    <td><x-v65.status-badge :tone="$return->status === 'posted' ? 'success' : 'neutral'">{{ \App\Support\Formatting\BusinessLabelFormatter::status($return->status) }}</x-v65.status-badge></td>
                    <td><x-v65.button size="xs" variant="secondary" wire:click="selectReturn({{ $return->id }})">Aç</x-v65.button></td>
                </tr>
            @empty
                <tr><td colspan="7" class="v65-ref-returns-empty-row">Bu türde iade kaydı bulunamadı.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
    @include('livewire.partials.record-delete-confirmation')
</div>
